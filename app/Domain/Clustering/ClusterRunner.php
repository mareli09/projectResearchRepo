<?php

namespace App\Domain\Clustering;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Llm\Suggestions;
use App\Domain\Screening\Screener;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Support\Facades\DB;

/**
 * Thematic clustering (CLAUDE.md §7 M6): K-Means on the TF-IDF vectors of the published records,
 * k = 2..15 scored by inertia, silhouette and Davies–Bouldin; the k with the best silhouette is
 * suggested. Each cluster gets an LLM label suggestion (fallback: top 3 terms). A run becomes
 * current only after the URDC confirms it; earlier runs are archived and kept.
 */
final class ClusterRunner
{
    public function __construct(
        private readonly AnalyticsClient $analytics,
        private readonly Screener $screener,
        private readonly Suggestions $suggestions,
    ) {}

    public static function nextPublicId(): string
    {
        $max = ClusterRun::query()->selectRaw("MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n")->value('n');

        return sprintf('RUN-%02d', ((int) $max) + 1);
    }

    /** Creates the queued run (the job does the work). */
    public static function queue(?User $by, ?int $k = null): ClusterRun
    {
        return ClusterRun::create([
            'public_id' => self::nextPublicId(),
            'k' => $k ?? 0,
            'n_records' => ResearchRecord::published()->count(),
            'params' => ['init' => 'k-means++', 'n_init' => 10, 'random_state' => 42,
                'k_min' => (int) config('tua.clustering.k_min'), 'k_max' => (int) config('tua.clustering.k_max'), 'k' => $k],
            'triggered_by' => $by?->id,
            'trigger_label' => $by ? $by->name : 'Scheduler',
            'state' => 'queued',
        ]);
    }

    public function run(ClusterRun $run): void
    {
        $run->update(['state' => 'running']);
        $params = $run->params;

        // Refit first so the run uses every published record (§7 M5: refit after each clustering run).
        $fit = $this->screener->fit();
        $result = $this->analytics->cluster((int) $params['k_min'], (int) $params['k_max'], $params['k'] ?? null);

        $chosen = collect($result['scores'])->firstWhere('k', $result['chosen_k']);
        $titles = ResearchRecord::whereIn('id', collect($result['clusters'])->pluck('nearest_ids')->flatten())->pluck('title', 'id');

        DB::transaction(function () use ($run, $result, $chosen, $fit, $params, $titles): void {
            foreach ($result['clusters'] as $c) {
                $nearest = collect($c['nearest_ids'])->map(fn ($id) => $titles[$id] ?? null)->filter()->values()->all();
                $label = $this->suggestions->themeLabel($c['top_terms'], $nearest);

                $cluster = Cluster::create([
                    'cluster_run_id' => $run->id,
                    'number' => $c['n'],
                    'top_terms' => $c['top_terms'],
                    'label' => $label['label'],
                    'label_status' => 'pending',
                    'size' => $c['size'],
                    'x' => $c['x'],
                    'y' => $c['y'],
                ]);
                LlmSuggestion::create([
                    'kind' => 'theme_label', 'subject_type' => $cluster->getMorphClass(), 'subject_id' => $cluster->id,
                    'suggested' => $label['label'], 'action' => 'pending', 'provider' => mb_substr($label['provider'], 0, 40),
                    'latency_ms' => $label['latency_ms'], 'fallback_used' => $label['fallback'],
                ]);
                $rows = collect($result['assignments'])->where('cluster', $c['n'])
                    ->map(fn ($a) => ['record_id' => (int) $a['id'], 'cluster_id' => $cluster->id, 'x' => $a['x'], 'y' => $a['y']]);
                foreach ($rows->chunk(500) as $chunk) {
                    DB::table('record_cluster')->insert($chunk->values()->all());
                }
            }

            $run->update([
                'k' => $result['chosen_k'],
                'silhouette' => $chosen['silhouette'] ?? null,
                'davies_bouldin' => $chosen['db'] ?? null,
                'n_records' => count($result['assignments']),
                'params' => array_merge($params, ['scores' => $result['scores'], 'vectorizer_version' => $fit['vectorizer_version']]),
                'state' => 'done',
                'finished_at' => now(),
            ]);
        });

        Audit::record(AuditEvent::CLUSTER_RUN, "Clustering {$run->public_id} finished: k = {$result['chosen_k']}", $run, [
            'k' => $result['chosen_k'], 'silhouette' => $chosen['silhouette'] ?? null, 'davies_bouldin' => $chosen['db'] ?? null,
            'n_records' => count($result['assignments']),
        ]);

        $to = User::role([User::ROLE_URDC, User::ROLE_COORDINATOR])->get();
        foreach ($to as $user) {
            $user->notify(new WorkflowNotice(
                "Clustering {$run->public_id} finished",
                "{$result['chosen_k']} themes were found in ".count($result['assignments']).' published studies. Suggested theme labels are waiting for confirmation.',
                $user->hasRole(User::ROLE_URDC) ? route('urdc.clustering') : route('coordinator.themes'),
            ));
        }
    }

    /** The URDC confirms a finished run: it becomes current, the previous current run is archived. */
    public static function confirm(ClusterRun $run, User $by): void
    {
        DB::transaction(function () use ($run): void {
            ClusterRun::where('state', 'current')->update(['state' => 'archived']);
            $run->update(['state' => 'current']);
        });
        Audit::record(AuditEvent::CLUSTER_RUN, "Clustering {$run->public_id} confirmed as current", $run, ['k' => $run->k], $by);
    }

    /** Coordinator or URDC decision on a suggested label, with a 1–4 rating (§7 M6, SOP 3.4). */
    public static function decideLabel(Cluster $cluster, User $by, string $decision, ?string $label, ?int $rating): void
    {
        $suggestion = LlmSuggestion::where('kind', 'theme_label')->where('subject_type', $cluster->getMorphClass())->where('subject_id', $cluster->id)->latest('id')->first();
        $suggested = $suggestion->suggested ?? (string) $cluster->label;

        $final = match ($decision) {
            'accept' => $suggested,
            'edit' => trim((string) $label),
            default => Suggestions::termsLabel($cluster->top_terms), // reject: fall back to the top terms
        };
        $old = $cluster->label;
        $cluster->update(['label' => $final, 'label_status' => 'confirmed']);

        $suggestion?->update([
            'action' => ['accept' => 'accepted', 'edit' => 'edited', 'reject' => 'rejected'][$decision],
            'final' => $final, 'rating' => $rating, 'acted_by' => $by->id, 'acted_at' => now(),
        ]);
        Audit::record(AuditEvent::LLM, "Theme label C{$cluster->number} {$decision}ed: \"{$final}\"".($rating ? " (rated {$rating}/4)" : ''), $cluster,
            ['decision' => $decision, 'rating' => $rating], $by, old: ['label' => $old], new: ['label' => $final]);
    }
}
