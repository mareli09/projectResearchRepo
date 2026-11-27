<?php

use App\Domain\Audit\Activity;
use App\Domain\Clustering\ClusterRunner;
use App\Domain\Site\Corpus;
use App\Jobs\RunClustering;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\OpenAiLlmClient;
use App\Support\Navigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| M6 thematic clustering (CLAUDE.md §7): a queued K-Means run, LLM label suggestions with the
| top-terms fallback, confirmation by the URDC, and label decisions by coordinators.
*/
beforeEach(function () {
    seedBasics();
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->coordinator = userWithRole(User::ROLE_COORDINATOR);
    $this->records = collect(range(1, 4))->map(fn ($i) => makeRecord(['title' => "Clustered Study Number {$i}"]))->each(function ($r) {
        $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    });
});

function fakeClusterService(iterable $records, array $extra = []): void
{
    [$a, $b, $c, $d] = collect($records)->pluck('id')->all();
    Http::fake($extra + [
        '127.0.0.1:8001/fit' => Http::response(['vectorizer_version' => 'v-cl', 'n_docs' => 4, 'n_terms' => 80]),
        '127.0.0.1:8001/cluster' => Http::response([
            'scores' => [['k' => 2, 'inertia' => 3.1, 'silhouette' => 0.21, 'db' => 1.4], ['k' => 3, 'inertia' => 2.2, 'silhouette' => 0.12, 'db' => 1.9]],
            'chosen_k' => 2,
            'assignments' => [['id' => $a, 'cluster' => 1, 'x' => 0.1, 'y' => 0.2], ['id' => $b, 'cluster' => 1, 'x' => 0.15, 'y' => 0.25],
                ['id' => $c, 'cluster' => 2, 'x' => -0.3, 'y' => 0.1], ['id' => $d, 'cluster' => 2, 'x' => -0.35, 'y' => 0.05]],
            'clusters' => [
                ['n' => 1, 'top_terms' => ['nursing', 'patient', 'care', 'community health'], 'size' => 2, 'x' => 0.12, 'y' => 0.22, 'nearest_ids' => [$a, $b]],
                ['n' => 2, 'top_terms' => ['machine', 'learning', 'prediction'], 'size' => 2, 'x' => -0.32, 'y' => 0.08, 'nearest_ids' => [$c, $d]],
            ],
        ]),
    ]);
}

function useOpenAi(): void
{
    app()->instance(LlmClient::class, new OpenAiLlmClient('sk-test', 'gpt-4o-mini'));
}

it('queues a run as a background job from the clustering page', function () {
    Queue::fake();

    $this->actingAs($this->urdc)->post('/urdc/clustering', ['k' => ''])->assertRedirect()->assertSessionHasNoErrors();

    $run = ClusterRun::sole();
    expect($run->state)->toBe('queued')->and($run->public_id)->toBe('RUN-01')->and($run->trigger_label)->toBe($this->urdc->name);
    Queue::assertPushed(RunClustering::class);

    // A second run while one is in progress is refused.
    $this->actingAs($this->urdc)->post('/urdc/clustering')->assertSessionHasErrors('run');
    expect(ClusterRun::count())->toBe(1);
});

it('only the URDC can run or confirm clustering', function () {
    $this->actingAs($this->coordinator)->post('/urdc/clustering')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->get('/urdc/clustering')->assertForbidden();
});

it('runs K-Means, stores clusters, assignments and LLM labels, and notifies', function () {
    Notification::fake();
    useOpenAi();
    fakeClusterService($this->records, ['api.openai.com/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => '{"label": "Nursing and community care"}']]]])
        ->push(['choices' => [['message' => ['content' => '{"label": "Predictive models, with ML!"}']]]])]);

    $run = ClusterRunner::queue($this->urdc);
    (new RunClustering($run))->handle(app(ClusterRunner::class));

    $run->refresh();
    expect($run->state)->toBe('done')->and($run->k)->toBe(2)->and((float) $run->silhouette)->toBe(0.21)
        ->and($run->params['scores'])->toHaveCount(2)->and($run->params['vectorizer_version'])->toBe('v-cl');

    [$c1, $c2] = $run->clusters->all();
    expect($c1->label)->toBe('Nursing and community care')->and($c1->label_status)->toBe('pending')->and($c1->size)->toBe(2);
    // The second reply has punctuation, so it is dropped and the top 3 terms are used.
    expect($c2->label)->toBe('Machine, learning, prediction');
    expect(LlmSuggestion::where('kind', 'theme_label')->pluck('fallback_used')->map(fn ($v) => (bool) $v)->all())->toBe([false, true]);
    expect(DB::table('record_cluster')->count())->toBe(4);

    // Only cluster terms and titles were sent to the LLM, never names or files.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com') && str_contains($r['messages'][1]['content'], 'Top terms: nursing')
        && ! str_contains(json_encode($r->data()), '@tua.edu.ph'));

    expect(Activity::where('event', 'cluster_run')->count())->toBe(1);
    Notification::assertSentTo([$this->urdc, $this->coordinator], WorkflowNotice::class);
});

it('marks the run failed when the analytics service is down', function () {
    Http::fake(['127.0.0.1:8001/*' => Http::response(['error' => 'down'], 503)]);
    $run = ClusterRunner::queue($this->urdc);
    $job = new RunClustering($run);

    try {
        $job->handle(app(ClusterRunner::class));
    } catch (Throwable $e) {
        $job->failed($e);
    }

    expect($run->refresh()->state)->toBe('failed')->and(Cluster::count())->toBe(0);
});

it('confirms a finished run as current and archives the previous one', function () {
    $old = ClusterRun::create(['public_id' => 'RUN-01', 'k' => 3, 'n_records' => 4, 'params' => [], 'state' => 'current']);
    $new = ClusterRun::create(['public_id' => 'RUN-02', 'k' => 2, 'n_records' => 4, 'params' => [], 'state' => 'done']);
    $cl = Cluster::create(['cluster_run_id' => $new->id, 'number' => 1, 'top_terms' => ['nursing'], 'label' => 'Nursing care', 'size' => 4]);
    DB::table('record_cluster')->insert($this->records->map(fn ($r) => ['record_id' => $r->id, 'cluster_id' => $cl->id, 'x' => 1, 'y' => 2])->all());

    $this->actingAs($this->urdc)->post("/urdc/clustering/{$new->id}/confirm")->assertRedirect();

    expect($new->refresh()->state)->toBe('current')->and($old->refresh()->state)->toBe('archived');
    // Search and dashboards now read the themes of the current run.
    expect(Corpus::themeLabels())->toBe([1 => 'Nursing care'])
        ->and(Corpus::records()->pluck('theme')->unique()->all())->toBe([1]);

    // An archived run cannot be confirmed again.
    $this->actingAs($this->urdc)->post("/urdc/clustering/{$old->id}/confirm")->assertStatus(422);
});

describe('theme labels', function () {
    beforeEach(function () {
        $this->run = ClusterRun::create(['public_id' => 'RUN-01', 'k' => 1, 'n_records' => 4, 'params' => [], 'state' => 'done']);
        $this->cluster = Cluster::create(['cluster_run_id' => $this->run->id, 'number' => 1, 'top_terms' => ['nursing', 'patient', 'care', 'mental health'],
            'label' => 'Community nursing care', 'size' => 4]);
        LlmSuggestion::create(['kind' => 'theme_label', 'subject_type' => $this->cluster->getMorphClass(), 'subject_id' => $this->cluster->id,
            'suggested' => 'Community nursing care', 'action' => 'pending', 'provider' => 'openai:gpt-4o-mini', 'fallback_used' => false]);
    });

    it('shows the labels page to coordinators and the URDC', function () {
        $this->actingAs($this->coordinator)->get('/coordinator/themes')->assertOk()->assertSee('Community nursing care')->assertSee('Accept');
        $this->actingAs($this->urdc)->get('/themes')->assertOk()->assertSee('Community nursing care');
        $this->actingAs($this->urdc)->get('/urdc/clustering')->assertOk()->assertSee('RUN-01')->assertSee('Confirm as current');
        $this->actingAs(userWithRole(User::ROLE_STUDENT))->get('/themes')->assertForbidden();
    });

    it('accepts a label with a rating', function () {
        $this->actingAs($this->coordinator)->post("/themes/{$this->cluster->id}", ['decision' => 'accept', 'rating' => 4])->assertSessionHasNoErrors();

        $s = LlmSuggestion::sole();
        expect($this->cluster->refresh()->label_status)->toBe('confirmed')
            ->and($s->action)->toBe('accepted')->and($s->rating)->toBe(4)->and($s->final)->toBe('Community nursing care')->and($s->acted_by)->toBe($this->coordinator->id);
        expect(Activity::where('event', 'llm')->count())->toBe(1);
    });

    it('saves an edited label and logs the old and new value', function () {
        $this->actingAs($this->coordinator)->post("/themes/{$this->cluster->id}", ['decision' => 'edit', 'label' => 'Nursing and mental health', 'rating' => 3]);

        expect($this->cluster->refresh()->label)->toBe('Nursing and mental health')->and(LlmSuggestion::sole()->action)->toBe('edited');
        $log = Activity::where('event', 'llm')->sole();
        expect($log->properties['old']['label'])->toBe('Community nursing care')->and($log->properties['new']['label'])->toBe('Nursing and mental health');
    });

    it('falls back to the top terms when a label is rejected', function () {
        $this->actingAs($this->coordinator)->post("/themes/{$this->cluster->id}", ['decision' => 'reject', 'rating' => 1]);

        expect($this->cluster->refresh()->label)->toBe('Nursing, patient, care')->and(LlmSuggestion::sole()->action)->toBe('rejected');
    });

    it('validates edited labels and requires a coordinator rating', function () {
        $url = "/themes/{$this->cluster->id}";
        $this->actingAs($this->coordinator)->post($url, ['decision' => 'edit', 'label' => 'Way too many words in this label', 'rating' => 2])->assertSessionHasErrors('label');
        $this->actingAs($this->coordinator)->post($url, ['decision' => 'edit', 'label' => 'Nursing; care!', 'rating' => 2])->assertSessionHasErrors('label');
        $this->actingAs($this->coordinator)->post($url, ['decision' => 'accept'])->assertSessionHasErrors('rating');
        expect($this->cluster->refresh()->label_status)->toBe('pending');
    });

    it('shows the number of labels waiting in the navigation', function () {
        expect(Navigation::pendingLabels())->toBe(1);
        ClusterRunner::decideLabel($this->cluster, $this->coordinator, 'accept', null, 4);
        expect(Navigation::pendingLabels())->toBeNull();
    });
});
