<?php

use App\Domain\Analytics\AiPerformance;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| "AI performance" (CLAUDE.md §7 M5, SOP 3.1–3.4): real labeled pairs, clustering runs and
| LLM suggestion outcomes.
*/
beforeEach(function () {
    seedBasics();
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->student = userWithRole(User::ROLE_STUDENT);
    $this->record = makeRecord();
});

/** A screened submission with a coordinator's calibration label. */
function labeledPair(int $i, float $score, string $label): void
{
    $sub = Submission::create(['public_id' => sprintf('SUB-9%03d', $i), 'student_user_id' => test()->student->id, 'title' => "Pair {$i}", 'abstract' => 'x',
        'research_type' => 'capstone_project', 'year' => 2026, 'keywords' => ['a'], 'sdgs' => [], 'status' => 'reviewed', 'max_similarity' => $score]);
    $check = DB::table('similarity_checks')->insertGetId(['submission_id' => $sub->id, 'run_at' => now(), 'top_score' => $score, 'flag' => 'original', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('similarity_matches')->insert(['check_id' => $check, 'record_id' => test()->record->id, 'score' => $score, 'rank' => 1, 'shared_terms' => '[]']);
    DB::table('screening_decisions')->insert(['submission_id' => $sub->id, 'matched_record_id' => test()->record->id, 'decision' => $label === 'duplicate' ? 'reject' : 'clear',
        'label_for_calibration' => $label, 'created_at' => now(), 'updated_at' => now()]);
}

it('computes the confusion matrix and metrics from labeled pairs', function () {
    labeledPair(1, 0.91, 'duplicate'); // TP
    labeledPair(2, 0.84, 'duplicate'); // TP
    labeledPair(3, 0.72, 'duplicate'); // FN at 0.80
    labeledPair(4, 0.82, 'distinct');  // FP
    labeledPair(5, 0.31, 'distinct');  // TN

    $cm = AiPerformance::confusion(AiPerformance::pairs(), 0.80);
    expect([$cm['tp'], $cm['fp'], $cm['fn'], $cm['tn']])->toBe([2, 1, 1, 1])
        ->and($cm['accuracy'])->toBe(0.6)
        ->and(round($cm['precision'], 3))->toBe(0.667)
        ->and(round($cm['recall'], 3))->toBe(0.667);

    // A lower threshold catches the third duplicate.
    expect(AiPerformance::confusion(AiPerformance::pairs(), 0.70)['tp'])->toBe(3);

    $this->actingAs($this->urdc)->get('/urdc/ai-performance')->assertOk()->assertSee('5 labeled pairs')->assertSee('Try a duplicate threshold')->assertDontSee('data-preview', false);
    $csv = $this->actingAs($this->urdc)->get('/urdc/ai-performance/export')->assertOk()->streamedContent();
    expect($csv)->toContain('"True positives",2')->toContain('SUB-9001');
});

it('reports LLM acceptance, fallback use and label ratings', function () {
    $sub = makeRecord();
    foreach (['accepted', 'accepted', 'edited', 'rejected', 'pending'] as $i => $action) {
        LlmSuggestion::create(['kind' => 'keyword', 'subject_type' => $sub->getMorphClass(), 'subject_id' => $sub->id, 'suggested' => "k{$i}",
            'action' => $action, 'provider' => 'openai:gpt-4o-mini', 'fallback_used' => $i === 0]);
    }
    $run = ClusterRun::create(['public_id' => 'RUN-01', 'k' => 2, 'silhouette' => 0.19, 'davies_bouldin' => 1.8, 'n_records' => 10, 'params' => [], 'state' => 'current', 'finished_at' => now()]);
    foreach ([[1, 'accepted', 4], [2, 'rejected', 1]] as [$n, $action, $rating]) {
        $c = Cluster::create(['cluster_run_id' => $run->id, 'number' => $n, 'top_terms' => ['a'], 'label' => "L{$n}", 'label_status' => 'confirmed', 'size' => 5]);
        LlmSuggestion::create(['kind' => 'theme_label', 'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id, 'suggested' => "L{$n}",
            'action' => $action, 'rating' => $rating, 'provider' => 'openai:gpt-4o-mini', 'fallback_used' => false]);
    }

    $llm = AiPerformance::llm();
    expect($llm['keyword'])->toMatchArray(['accepted' => 2, 'edited' => 1, 'rejected' => 1, 'pending' => 1, 'fallback' => 1, 'total' => 5]);
    expect(AiPerformance::labelRatings())->toBe(['avg' => 2.5, 'count' => 2]);
    expect(AiPerformance::meaningful($run))->toBe('1 of 2');

    $this->actingAs($this->urdc)->get('/urdc/ai-performance')->assertOk()->assertSee('50%')->assertSee('RUN-01')->assertSee('No labeled pairs yet');
});

it('is for the URDC only', function () {
    $this->actingAs(userWithRole(User::ROLE_COORDINATOR))->get('/urdc/ai-performance')->assertForbidden();
});
