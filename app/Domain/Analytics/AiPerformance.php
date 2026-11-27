<?php

namespace App\Domain\Analytics;

use App\Domain\Screening\Thresholds;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use Illuminate\Support\Facades\DB;

/**
 * "AI performance" (CLAUDE.md §7 M5, SOP 3.1–3.4), from real data only:
 * - duplicate screening: the coordinators' calibration labels (duplicate or distinct) against the
 *   similarity score of the same submission–record pair, at any threshold;
 * - clustering quality: silhouette and Davies–Bouldin of each run;
 * - LLM suggestions: what users did with them (llm_suggestions) and the 1–4 label ratings.
 */
final class AiPerformance
{
    public const KINDS = ['keyword' => 'Keywords', 'sdg' => 'SDG tags', 'research_type' => 'Research type', 'theme_label' => 'Theme labels'];

    /**
     * Labeled pairs: one per coordinator decision with a calibration label.
     *
     * @return list<array{submission: string, record: ?string, score: float, duplicate: bool}>
     */
    public static function pairs(): array
    {
        $rows = DB::table('screening_decisions as d')
            ->join('submissions as s', 's.id', '=', 'd.submission_id')
            ->leftJoin('research_records as r', 'r.id', '=', 'd.matched_record_id')
            ->whereNotNull('d.label_for_calibration')
            ->orderBy('d.id')
            ->get(['d.submission_id', 'd.matched_record_id', 'd.label_for_calibration', 's.public_id as sub', 's.max_similarity', 'r.public_id as rec']);

        // Score of the matched record in the submission's latest screening (else the submission's top score).
        $scores = DB::table('similarity_matches as m')
            ->join('similarity_checks as c', 'c.id', '=', 'm.check_id')
            ->whereIn('c.submission_id', $rows->pluck('submission_id'))
            ->orderBy('c.id')
            ->get(['c.submission_id', 'm.record_id', 'm.score'])
            ->mapWithKeys(fn ($m) => [$m->submission_id.'-'.$m->record_id => (float) $m->score]);

        return $rows->map(fn ($r) => [
            'submission' => (string) $r->sub,
            'record' => $r->rec,
            'score' => round($r->matched_record_id !== null && isset($scores[$r->submission_id.'-'.$r->matched_record_id])
                ? $scores[$r->submission_id.'-'.$r->matched_record_id] : (float) ($r->max_similarity ?? 0), 3),
            'duplicate' => $r->label_for_calibration === 'duplicate',
        ])->values()->all();
    }

    /**
     * @param  list<array{score: float, duplicate: bool}>  $pairs
     * @return array{tp: int, fp: int, fn: int, tn: int, accuracy: ?float, precision: ?float, recall: ?float, f1: ?float}
     */
    public static function confusion(array $pairs, float $threshold): array
    {
        $c = ['tp' => 0, 'fp' => 0, 'fn' => 0, 'tn' => 0];
        foreach ($pairs as $p) {
            $flagged = $p['score'] >= $threshold;
            $c[$flagged ? ($p['duplicate'] ? 'tp' : 'fp') : ($p['duplicate'] ? 'fn' : 'tn')]++;
        }
        $n = array_sum($c);
        $precision = $c['tp'] + $c['fp'] > 0 ? $c['tp'] / ($c['tp'] + $c['fp']) : null;
        $recall = $c['tp'] + $c['fn'] > 0 ? $c['tp'] / ($c['tp'] + $c['fn']) : null;

        return $c + [
            'accuracy' => $n > 0 ? ($c['tp'] + $c['tn']) / $n : null,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $precision !== null && $recall !== null && $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : null,
        ];
    }

    /** @return array<string, array{accepted: int, edited: int, rejected: int, pending: int, fallback: int, total: int}> */
    public static function llm(): array
    {
        $rows = LlmSuggestion::query()->selectRaw('kind, action, fallback_used, COUNT(*) AS n')->groupBy('kind', 'action', 'fallback_used')->get();
        $out = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $k = $rows->where('kind', $kind);
            $count = fn (string $action) => (int) $k->where('action', $action)->sum('n');
            $out[$kind] = [
                'accepted' => $count('accepted'), 'edited' => $count('edited'), 'rejected' => $count('rejected'), 'pending' => $count('pending'),
                'fallback' => (int) $k->filter(fn ($r) => (bool) $r->fallback_used)->sum('n'),
                'total' => (int) $k->sum('n'),
            ];
        }

        return $out;
    }

    /** @return array{avg: ?float, count: int} */
    public static function labelRatings(): array
    {
        $q = LlmSuggestion::where('kind', 'theme_label')->whereNotNull('rating');

        return ['avg' => $q->clone()->avg('rating') !== null ? (float) $q->clone()->avg('rating') : null, 'count' => $q->count()];
    }

    /** @return list<array{id: string, k: int, silhouette: ?float, db: ?float, state: string}> oldest first */
    public static function runs(int $limit = 8): array
    {
        return ClusterRun::whereIn('state', ['done', 'current', 'archived'])->latest('id')->limit($limit)->get()->reverse()
            ->map(fn (ClusterRun $r) => ['id' => $r->public_id, 'k' => $r->k, 'silhouette' => $r->silhouette !== null ? (float) $r->silhouette : null,
                'db' => $r->davies_bouldin !== null ? (float) $r->davies_bouldin : null, 'state' => $r->state])->values()->all();
    }

    /** Labels coordinators kept (accepted or edited) of those decided in a run, e.g. "5 of 7". */
    public static function meaningful(ClusterRun $run): string
    {
        $ids = Cluster::where('cluster_run_id', $run->id)->pluck('id');
        $decided = LlmSuggestion::where('kind', 'theme_label')->whereIn('subject_id', $ids)->where('subject_type', (new Cluster)->getMorphClass())
            ->whereIn('action', ['accepted', 'edited', 'rejected'])->pluck('action');

        return $decided->filter(fn ($a) => $a !== 'rejected')->count().' of '.$ids->count();
    }

    public static function threshold(): float
    {
        return Thresholds::get()['duplicate'];
    }
}
