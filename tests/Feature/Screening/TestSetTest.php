<?php

use App\Domain\Audit\Activity;
use App\Domain\Screening\TestSet;
use App\Models\ResearchRecord;
use App\Models\TestPair;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/* Labeled screening test set (CLAUDE.md §7 M5, §17): pairs, blind labels, majority of 3, AI performance. */
beforeEach(function () {
    seedBasics();
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->coords = collect(range(1, 3))->map(fn () => userWithRole(User::ROLE_COORDINATOR));
    $this->records = collect(range(1, 4))->map(fn () => tap(makeRecord())->forceFill(['status' => 'published', 'published_at' => now()])->save());
    $this->records = ResearchRecord::published()->orderBy('id')->get();
    // The analytics service: /related gives the next record, /pair a fixed score per call.
    $scores = [0.91, 0.12, 0.85, 0.30, 0.66, 0.05];
    Http::fake([
        '127.0.0.1:8001/related' => fn ($req) => Http::response(['matches' => [['id' => $this->records->firstWhere('id', '!=', $req['id'])->id, 'score' => 0.8]]]),
        '127.0.0.1:8001/pair' => function () use (&$scores) {
            return Http::response(['score' => array_shift($scores) ?? 0.5, 'shared_terms' => ['flood'], 'vectorizer_version' => 'v1']);
        },
    ]);
});

it('adds sample pairs from the repository, scored, without any labels', function () {
    $this->actingAs($this->urdc)->post('/urdc/test-set/sample', ['n' => 4])->assertSessionHas('status');

    expect(TestPair::count())->toBe(4)
        ->and(TestPair::whereNull('score')->count())->toBe(0)
        ->and(TestPair::where('source', 'sample_neighbour')->count())->toBe(2)
        ->and(TestPair::first()->public_id)->toBe('TP-001')
        ->and(TestSet::stats()['labels'])->toBe(0);
    $this->actingAs($this->urdc)->get('/urdc/test-set')->assertOk()->assertSee('TP-001')->assertSee('Waiting');
});

it('imports prepared pairs from a CSV and reports bad rows', function () {
    $id = $this->records->first()->public_id;
    $csv = "title,abstract,keywords,record\n"
        ."\"Rewritten flood study\",\"A paraphrase of the flood risk study.\",\"flood; risk\",{$id}\n"
        ."\"No record\",\"Text\",\"x\",TUA-1999-9999\n";
    $file = UploadedFile::fake()->createWithContent('pairs.csv', $csv);

    $this->actingAs($this->urdc)->post('/urdc/test-set/import', ['file' => $file])
        ->assertSessionHas('status', '1 pairs were imported.')->assertSessionHas('importErrors', ['Row 3: there is no published record TUA-1999-9999.']);
    expect(TestPair::firstOrFail()->left_keywords)->toBe(['flood', 'risk']);
});

it('lets coordinators label blind, decides by 2 of 3, and never asks a third time', function () {
    $this->actingAs($this->urdc)->post('/urdc/test-set/sample', ['n' => 2]);
    [$a, $b, $c] = $this->coords->all();

    // The labeling page never shows the score.
    $page = $this->actingAs($a)->get('/coordinator/test-set')->assertOk()->assertSee('Stored record');
    foreach (TestPair::pluck('score') as $s) {
        $page->assertDontSee(number_format((float) $s, 3));
    }

    $pair = TestPair::orderBy('id')->firstOrFail();
    $this->actingAs($a)->post("/coordinator/test-set/{$pair->id}", ['label' => 'duplicate'])->assertRedirect(route('coordinator.testset'));
    $this->actingAs($b)->post("/coordinator/test-set/{$pair->id}", ['label' => 'duplicate']);
    expect(TestSet::majority($pair->fresh()->labels))->toBe('duplicate');

    // Decided: the third coordinator is not asked about it.
    expect(TestSet::nextFor($c)?->id)->not->toBe($pair->id)->and(TestSet::waitingFor($c))->toBe(1);
    expect(Activity::where('event', 'reviewed')->where('description', "Test pair {$pair->public_id} labeled duplicate")->count())->toBe(2);

    $this->actingAs($a)->post("/coordinator/test-set/{$pair->id}", ['label' => 'maybe'])->assertSessionHasErrors('label');
});

it('measures screening against the majority labels on AI performance', function () {
    $this->actingAs($this->urdc)->post('/urdc/test-set/sample', ['n' => 2]); // scores 0.91 and 0.12
    [$p1, $p2] = TestPair::orderBy('id')->get()->all();
    foreach ($this->coords->take(2) as $c) {
        TestSet::label($p1, $c, 'duplicate');
        TestSet::label($p2, $c, 'distinct');
    }

    expect(TestSet::decidedPairs())->toHaveCount(2);
    $this->actingAs($this->urdc)->get('/urdc/ai-performance')->assertOk()
        ->assertSee('Labeled test set (2)')->assertSee('2 labeled pairs')->assertSee('1 duplicates');
    $csv = $this->actingAs($this->urdc)->get('/urdc/ai-performance/export?source=test')->streamedContent();
    expect($csv)->toContain('labeled test set (majority of 3 coordinators)')->toContain('"True positives",1')->toContain('"True negatives",1');
});

it('keeps the pages to their roles', function () {
    $this->actingAs($this->coords->first())->get('/urdc/test-set')->assertForbidden();
    $this->actingAs($this->urdc)->get('/coordinator/test-set')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->post('/urdc/test-set/sample', ['n' => 2])->assertForbidden();
});
