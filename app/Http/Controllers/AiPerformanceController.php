<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\AiPerformance;
use App\Domain\Site\Corpus;
use App\Http\Controllers\ClusteringController as Clustering;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** "AI performance" (CLAUDE.md §7 M5, SOP 3.1–3.4), URDC. Real figures only. */
class AiPerformanceController extends Controller
{
    public function index(): View
    {
        $pairs = AiPerformance::pairs();
        $threshold = AiPerformance::threshold();
        $llm = AiPerformance::llm();
        $runs = AiPerformance::runs();
        $run = Corpus::currentRun() ?? Clustering::reviewRun();
        $series = [['accepted', 'Accepted', 'var(--ok)'], ['edited', 'Edited', 'var(--c1)'], ['rejected', 'Rejected', 'var(--bad)']];
        $decided = fn (array $s) => max(1, $s['accepted'] + $s['edited'] + $s['rejected']);

        return view('urdc.ai', [
            'pairs' => $pairs,
            'duplicates' => count(array_filter($pairs, fn ($p) => $p['duplicate'])),
            'threshold' => $threshold,
            'cm' => AiPerformance::confusion($pairs, $threshold),
            'run' => $run,
            'meaningful' => $run ? AiPerformance::meaningful($run) : null,
            'llm' => $llm,
            'ratings' => AiPerformance::labelRatings(),
            'llmChart' => ['tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']], 'legend' => (object) [],
                'xAxis' => ['type' => 'value', 'max' => 100, 'axisLabel' => ['formatter' => '{value}%']],
                'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => array_values(AiPerformance::KINDS)],
                'series' => array_map(fn ($s) => [
                    'name' => $s[1], 'type' => 'bar', 'stack' => 'a', 'barMaxWidth' => 22, 'itemStyle' => ['color' => $s[2]],
                    'data' => array_map(fn ($k) => round($llm[$k][$s[0]] / $decided($llm[$k]) * 100, 1), array_keys(AiPerformance::KINDS)),
                ], $series)],
            'runsChart' => $runs === [] ? null : ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [], 'xAxis' => ['type' => 'category', 'data' => array_column($runs, 'id')],
                'yAxis' => [['type' => 'value', 'scale' => true, 'name' => 'Silhouette'], ['type' => 'value', 'scale' => true, 'name' => 'DB', 'splitLine' => ['show' => false]]],
                'series' => [['name' => 'Silhouette', 'type' => 'line', 'data' => array_column($runs, 'silhouette'), 'itemStyle' => ['color' => 'var(--c1)']],
                    ['name' => 'Davies–Bouldin', 'type' => 'line', 'yAxisIndex' => 1, 'data' => array_column($runs, 'db'), 'itemStyle' => ['color' => 'var(--c2)']]]],
        ]);
    }

    /** CSV for the evaluation chapter: metrics at the current threshold, then every labeled pair. */
    public function export(): StreamedResponse
    {
        $pairs = AiPerformance::pairs();
        $threshold = AiPerformance::threshold();
        $cm = AiPerformance::confusion($pairs, $threshold);
        $llm = AiPerformance::llm();
        $ratings = AiPerformance::labelRatings();

        return response()->streamDownload(function () use ($pairs, $threshold, $cm, $llm, $ratings): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
            fputcsv($out, ['TUA Research Repository: AI performance', now()->format('Y-m-d H:i').' Asia/Manila']);
            fputcsv($out, []);
            fputcsv($out, ['Duplicate screening', 'threshold '.number_format($threshold, 2), count($pairs).' labeled pairs']);
            foreach (['tp' => 'True positives', 'fp' => 'False positives', 'fn' => 'False negatives', 'tn' => 'True negatives'] as $k => $l) {
                fputcsv($out, [$l, $cm[$k]]);
            }
            foreach (['accuracy' => 'Accuracy', 'precision' => 'Precision', 'recall' => 'Recall', 'f1' => 'F1-score'] as $k => $l) {
                fputcsv($out, [$l, $cm[$k] === null ? '' : number_format($cm[$k], 3)]);
            }
            fputcsv($out, []);
            fputcsv($out, ['LLM suggestions', 'Accepted', 'Edited', 'Rejected', 'Pending', 'Fallback used', 'Acceptance rate']);
            foreach (AiPerformance::KINDS as $k => $l) {
                $s = $llm[$k];
                $decided = $s['accepted'] + $s['edited'] + $s['rejected'];
                fputcsv($out, [$l, $s['accepted'], $s['edited'], $s['rejected'], $s['pending'], $s['fallback'], $decided ? number_format($s['accepted'] / $decided * 100, 1).'%' : '']);
            }
            fputcsv($out, ['Theme label rating (mean of 4)', $ratings['avg'] === null ? '' : number_format($ratings['avg'], 2), $ratings['count'].' rated']);
            fputcsv($out, []);
            fputcsv($out, ['Submission', 'Matched record', 'Similarity score', 'Coordinator label', 'Flagged at threshold']);
            foreach ($pairs as $p) {
                fputcsv($out, [$p['submission'], $p['record'] ?? '', number_format($p['score'], 3), $p['duplicate'] ? 'duplicate' : 'distinct', $p['score'] >= $threshold ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'ai-performance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
