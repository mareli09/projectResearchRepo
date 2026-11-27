<?php

namespace App\Http\Controllers;

use App\Domain\Clustering\ClusterRunner;
use App\Jobs\RunClustering;
use App\Models\Cluster;
use App\Models\ClusterRun;
use App\Models\LlmSuggestion;
use App\Models\ResearchRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** M6 Thematic clustering (CLAUDE.md §7): runs (URDC), confirmation, and theme labels (coordinators and URDC). */
class ClusteringController extends Controller
{
    /** The run whose labels are reviewed: the newest finished or current run. */
    public static function reviewRun(): ?ClusterRun
    {
        return ClusterRun::with('clusters')->whereIn('state', ['done', 'current'])->latest('id')->first();
    }

    public function index(): View
    {
        $latest = ClusterRun::latest('id')->first();
        $run = self::reviewRun();
        $scores = collect($run?->scores() ?? []);
        $chosen = (int) ($run->k ?? 0);

        return view('urdc.clustering', [
            'latest' => $latest,
            'run' => $run,
            'runs' => ClusterRun::latest('id')->limit(12)->get(),
            'clusters' => $run ? $run->clusters : collect(),
            'suggestions' => $run ? $this->labelSuggestions($run) : collect(),
            'busy' => $latest !== null && in_array($latest->state, ['queued', 'running'], true),
            'records' => ResearchRecord::published()->count(),
            'elbow' => $scores->isEmpty() ? null : ['tooltip' => ['trigger' => 'axis'], 'xAxis' => ['type' => 'category', 'name' => 'k', 'data' => $scores->pluck('k')->all()],
                'yAxis' => ['type' => 'value', 'name' => 'Inertia', 'scale' => true],
                'series' => [['type' => 'line', 'data' => $scores->pluck('inertia')->all(), 'symbolSize' => 6, 'itemStyle' => ['color' => 'var(--c1)'],
                    'markPoint' => ['symbol' => 'circle', 'symbolSize' => 12, 'data' => [['coord' => [(string) $chosen, $scores->firstWhere('k', $chosen)['inertia'] ?? 0], 'itemStyle' => ['color' => 'var(--c2)']]]]]]],
            'silhouette' => $scores->isEmpty() ? null : ['tooltip' => ['trigger' => 'axis'], 'xAxis' => ['type' => 'category', 'name' => 'k', 'data' => $scores->pluck('k')->all()],
                'yAxis' => ['type' => 'value', 'scale' => true],
                'series' => [['type' => 'bar', 'barMaxWidth' => 18, 'data' => $scores->map(fn ($s) => ['value' => $s['silhouette'], 'itemStyle' => ['color' => $s['k'] === $chosen ? 'var(--c2)' : 'var(--c1)']])->all()]]],
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate(['k' => ['nullable', 'integer', 'min:2', 'max:15']]);
        if (ClusterRun::whereIn('state', ['queued', 'running'])->exists()) {
            return back()->withErrors(['run' => 'A clustering run is already in progress.']);
        }

        $run = ClusterRunner::queue($request->user(), $data['k'] ?? null);
        RunClustering::dispatch($run);

        return back()->with('status', "{$run->public_id} is queued. It runs in the background; you get a notice when it finishes.");
    }

    public function confirm(Request $request, ClusterRun $run): RedirectResponse
    {
        abort_unless($run->state === 'done', 422, 'Only a finished run can be confirmed.');
        ClusterRunner::confirm($run, $request->user());

        return back()->with('status', "{$run->public_id} is now the current set of themes. Search and dashboards use it.");
    }

    public function themes(): View
    {
        $run = self::reviewRun();

        return view('coordinator.themes', [
            'run' => $run,
            'clusters' => $run ? $run->clusters : collect(),
            'suggestions' => $run ? $this->labelSuggestions($run) : collect(),
            'nearest' => $run ? $this->nearestTitles($run) : collect(),
        ]);
    }

    public function label(Request $request, Cluster $cluster): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole([User::ROLE_COORDINATOR, User::ROLE_URDC]), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'edit', 'reject'])],
            'label' => ['nullable', 'required_if:decision,edit', 'string', 'max:60', 'regex:/^[\pL\pN\s]+$/u',
                function ($attr, $value, $fail) {
                    if (count(preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: []) > 5) {
                        $fail('Use 5 words or fewer.');
                    }
                }],
            'rating' => [$user->hasRole(User::ROLE_COORDINATOR) ? 'required' : 'nullable', 'integer', 'between:1,4'],
        ], ['label.regex' => 'Use plain words only, no punctuation.', 'rating.required' => 'Rate how well the suggestion describes the studies.']);

        ClusterRunner::decideLabel($cluster, $user, $data['decision'], $data['label'] ?? null, isset($data['rating']) ? (int) $data['rating'] : null);

        return back()->with('status', "Label for C{$cluster->number} saved. It appears in search and dashboards.");
    }

    /** @return Collection<int, LlmSuggestion> cluster id => suggestion */
    private function labelSuggestions(ClusterRun $run): Collection
    {
        return LlmSuggestion::where('kind', 'theme_label')->where('subject_type', (new Cluster)->getMorphClass())
            ->whereIn('subject_id', $run->clusters->pluck('id'))->get()->keyBy('subject_id');
    }

    /** @return Collection<int, list<string>> cluster id => 3 titles closest to its centre */
    private function nearestTitles(ClusterRun $run): Collection
    {
        return $run->clusters->mapWithKeys(function (Cluster $c) {
            $titles = DB::table('record_cluster')->join('research_records', 'research_records.id', '=', 'record_cluster.record_id')
                ->where('record_cluster.cluster_id', $c->id)
                ->orderByRaw('POW(record_cluster.x - ?, 2) + POW(record_cluster.y - ?, 2)', [$c->x, $c->y])
                ->limit(3)->pluck('research_records.title')->map(fn ($t) => (string) $t)->all();

            return [$c->id => array_values($titles)];
        });
    }
}
