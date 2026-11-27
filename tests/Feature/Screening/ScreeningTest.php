<?php

use App\Domain\Audit\Activity;
use App\Domain\Screening\Thresholds;
use App\Http\Controllers\LibraryController;
use App\Models\ResearchCluster;
use App\Models\Setting;
use App\Models\SimilarityCheck;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| M5 similarity screening (CLAUDE.md §7): the Python service scores, Laravel stores the top 5,
| flags at the Settings thresholds, and never rejects anything by itself.
*/
beforeEach(function () {
    Storage::fake('research');
    seedBasics();
    $this->student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId()]);
    $this->adviser = userWithRole(User::ROLE_ADVISER, ['academic_unit_id' => ceisId()]);
    $this->records = collect(range(1, 3))->map(fn ($i) => makeRecord(['title' => "Existing Capstone Study Number {$i}"]))->each(function ($r) {
        $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    });
});

function fakeAnalytics(array $routes): void
{
    Http::fake($routes + [
        '127.0.0.1:8001/fit' => Http::response(['vectorizer_version' => 'v-test', 'n_docs' => 3, 'n_terms' => 120]),
    ]);
}

function screenPayload(): array
{
    $tag = uniqid();

    return [
        'title' => 'Title Screening Using TF-IDF', 'abstract' => 'Screens titles with TF-IDF and cosine similarity.',
        'research_type' => 'capstone_project', 'program_id' => bsitId(), 'adviser_user_id' => test()->adviser->id, 'year' => 2026,
        'authors' => 'A Student', 'keywords' => 'tf-idf, cosine similarity, screening', 'sdgs' => [4],
        'files' => ['abstract' => docxUpload('a.docx', "a{$tag}"), 'full_paper' => pdfUpload('p.pdf', "p{$tag}"), 'imrad' => pdfUpload('i.pdf', "i{$tag}"),
            'toa' => pdfUpload('t.pdf', "t{$tag}"), 'references' => docxUpload('r.docx', "r{$tag}")],
    ];
}

it('screens a submission on submit and stores the top matches with shared terms', function () {
    Notification::fake();
    [$a, $b] = [$this->records[0], $this->records[1]];
    fakeAnalytics(['127.0.0.1:8001/screen' => Http::response(['matches' => [
        ['id' => $a->id, 'score' => 0.7123, 'shared_terms' => ['tf idf', 'cosine']],
        ['id' => $b->id, 'score' => 0.41, 'shared_terms' => ['screening']],
    ], 'top_terms' => ['tf idf'], 'vectorizer_version' => 'v-test'])]);

    $this->actingAs($this->student)->post('/student/submit', screenPayload())->assertSessionHasNoErrors();

    $sub = Submission::firstOrFail();
    expect((float) $sub->max_similarity)->toBe(0.712);
    expect($sub->flag)->toBe('similar');
    expect($sub->status)->toBe('submitted'); // never rejected automatically

    $check = SimilarityCheck::where('submission_id', $sub->id)->with('matches')->firstOrFail();
    expect($check->vectorizer_version)->toBe('v-test');
    expect($check->matches->pluck('record_id')->all())->toBe([$a->id, $b->id]);
    expect($check->matches->first()->shared_terms)->toBe(['tf idf', 'cosine']);
    expect(Activity::where('event', 'service')->where('subject_id', $sub->id)->exists())->toBeTrue();
    Notification::assertSentTo($this->student, WorkflowNotice::class, fn ($n) => str_contains($n->toArray($this->student)['subject'], 'Screening result'));

    // Only title, abstract and keywords are sent.
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/screen')
        && array_diff(array_keys($r->data()), ['title', 'abstract', 'keywords', 'k', 'exclude_id']) === []
        && $r->hasHeader('X-Signature') && $r->hasHeader('X-Timestamp'));

    $this->get('/student/status?id='.$sub->public_id)->assertSee('Similar, needs review')->assertSee($a->title);
});

it('flags at each threshold edge', function (float $score, string $flag) {
    expect(Thresholds::flag($score))->toBe($flag);
})->with([
    [0.80, 'probable_duplicate'], [0.95, 'probable_duplicate'], [0.799, 'similar'],
    [0.60, 'similar'], [0.599, 'original'], [0.0, 'original'],
]);

it('uses the thresholds from Settings', function () {
    Setting::updateOrCreate(['key' => 'screening.thresholds'], ['value' => ['duplicate' => 0.9, 'review' => 0.5]]);
    expect(Thresholds::flag(0.85))->toBe('similar');
    expect(Thresholds::flag(0.55))->toBe('similar');
    expect(Thresholds::flag(0.45))->toBe('original');
});

it('builds the index first when the service has none yet', function () {
    $calls = 0;
    Http::fake([
        '127.0.0.1:8001/screen' => function () use (&$calls) {
            return ++$calls === 1
                ? Http::response(['error' => 'not_fitted'], 409)
                : Http::response(['matches' => [['id' => $this->records[0]->id, 'score' => 0.2, 'shared_terms' => []]], 'top_terms' => [], 'vectorizer_version' => 'v2']);
        },
        '127.0.0.1:8001/fit' => Http::response(['vectorizer_version' => 'v2', 'n_docs' => 3, 'n_terms' => 50]),
    ]);

    $this->actingAs($this->student)->post('/student/submit', screenPayload());

    expect(Submission::first()->flag)->toBe('original');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fit') && count($r->data()['records']) === 3);
    expect(Setting::valueOf('screening.index')['vectorizer_version'])->toBe('v2');
});

it('still accepts the submission when the service is down', function () {
    // The default test fake answers 503.
    $this->actingAs($this->student)->post('/student/submit', screenPayload())->assertSessionHasNoErrors();

    $sub = Submission::firstOrFail();
    expect($sub->status)->toBe('submitted');
    expect($sub->max_similarity)->toBeNull();
    expect(SimilarityCheck::count())->toBe(0);
    $this->get('/student/status?id='.$sub->public_id)->assertSee('Similarity screening is queued');
});

it('shows the screening result to the coordinator side by side', function () {
    $coordinator = userWithRole(User::ROLE_COORDINATOR);
    ResearchCluster::where('number', 1)->update(['coordinator_user_id' => $coordinator->id]);
    fakeAnalytics(['127.0.0.1:8001/screen' => Http::response(['matches' => [['id' => $this->records[0]->id, 'score' => 0.85, 'shared_terms' => ['capstone']]], 'top_terms' => [], 'vectorizer_version' => 'v'])]);

    $this->actingAs($this->student)->post('/student/submit', screenPayload());
    $sub = Submission::firstOrFail();
    $this->actingAs($this->adviser)->post('/adviser/review', ['id' => $sub->public_id, 'decision' => 'endorse']);

    $this->actingAs($coordinator)->get('/coordinator/screening?id='.$sub->public_id)
        ->assertSee('Probable duplicate')->assertSee($this->records[0]->title)->assertSee('Record decision');
});

it('fits the index with every published record from the command line', function () {
    fakeAnalytics([]);
    $this->artisan('repository:index')->expectsOutputToContain('Indexed 3 records')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fit')
        && collect($r->data()['records'])->every(fn ($x) => array_keys($x) === ['id', 'title', 'abstract', 'keywords']));
});

it('finds related studies with the service and falls back to shared keywords', function () {
    [$a, $b, $c] = $this->records->all();
    $up = true;
    Http::fake(['127.0.0.1:8001/related' => function () use (&$up, $b, $c) {
        return $up
            ? Http::response(['matches' => [['id' => $c->id, 'score' => 0.66], ['id' => $b->id, 'score' => 0.31]]])
            : Http::response([], 503);
    }]);

    $related = LibraryController::related($a->load('keywords'));
    expect($related->pluck('record.id')->all())->toBe([$c->id, $b->id]);
    expect($related->first()['score'])->toBe(0.66);

    $up = false;
    Cache::forget('analytics.down');
    $fallback = LibraryController::related($a->load('keywords'));
    expect($fallback)->not->toBeEmpty(); // same keywords in the test records
    expect(Cache::has('analytics.down'))->toBeTrue();
});
