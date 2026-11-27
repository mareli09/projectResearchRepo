<?php

namespace App\Http\Controllers\Design;

use App\Domain\Analytics\Dashboard;
use App\Domain\Site\DesignData;
use App\Domain\Submissions\SubmissionPresenter;
use App\Domain\Submissions\Workflow;
use App\Http\Controllers\Controller;
use App\Models\ResearchCluster;
use App\Models\ResearchRecord;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Research coordinator screens (CLAUDE.md §7 M5, M6, M7). */
class CoordinatorController extends Controller
{
    public function queue(): View
    {
        $cluster = $this->cluster();
        $subs = $this->clusterSubmissions($cluster)->get();
        $queue = $subs->where('status', 'endorsed')->values();

        return view('coordinator.queue', [
            'cluster' => $cluster,
            'queue' => $queue->map(fn ($s) => SubmissionPresenter::present($s)),
            'recent' => $subs->whereIn('status', ['reviewed', 'published', 'rejected'])->take(8)->map(fn ($s) => SubmissionPresenter::present($s))->values(),
            'kpis' => [
                ['Endorsed, waiting for review', $queue->count(), 'inbox', 'gold'],
                ['Flagged by screening', $queue->whereIn('flag', ['similar', 'probable_duplicate'])->count(), 'compare', 'warn'],
                ['Theme labels to confirm', collect(DesignData::labels())->where('confirmed', false)->count(), 'tag', 'accent'],
            ],
        ]);
    }

    public function screening(Request $request): View
    {
        $base = $this->clusterSubmissions($this->cluster());
        $sub = (clone $base)->where('public_id', $request->query('id'))->first() ?? (clone $base)->where('status', 'endorsed')->orderBy('submitted_at')->first();
        if ($sub === null) {
            return view('coordinator.screening', ['sub' => null]);
        }

        $view = SubmissionPresenter::present($sub);
        $matches = SubmissionPresenter::matches($sub);
        $idx = $matches ? min(max(0, (int) $request->query('match', 0)), count($matches) - 1) : 0;
        $match = $matches ? ResearchRecord::with('authors', 'academicUnit', 'keywords')->find($matches[$idx]['record']['id']) : null;

        $shared = [];
        if ($match !== null) {
            $words = fn (string $t) => array_unique(array_filter(preg_split('/[^a-z0-9-]+/', strtolower($t)) ?: [], fn ($w) => strlen($w) > 3));
            $subWords = $words($sub->title.' '.$sub->abstract.' '.implode(' ', $sub->keywords));
            $matchWords = $words($match->title.' '.$match->abstract.' '.$match->keywords->pluck('term')->implode(' '));
            $shared = array_slice(array_values(array_intersect($subWords, $matchWords)), 0, 14);
        }

        return view('coordinator.screening', [
            'sub' => $view, 'matches' => $matches, 'idx' => $idx, 'match' => $match,
            'score' => $matches[$idx]['score'] ?? null, 'shared' => $shared,
            'can' => $request->user()->can('review', $sub),
        ]);
    }

    public function decide(Request $request, Workflow $workflow): RedirectResponse
    {
        $sub = Submission::where('public_id', $request->input('id'))->firstOrFail();
        Gate::authorize('review', $sub);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['clear', 'revise', 'reject'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'matched_record_id' => ['nullable', 'integer', Rule::exists('research_records', 'id')],
        ]);

        $workflow->coordinatorDecide($request->user(), $sub, $data['decision'], $data['reason'] ?? null, $data['matched_record_id'] ?? null);
        $msg = ['clear' => 'Cleared and sent to the URDC for approval.', 'revise' => 'Returned to the student for revision.', 'reject' => 'Rejected. The student was notified.'][$data['decision']];

        return redirect()->route('coordinator.queue')->with('status', "{$sub->public_id}: {$msg}");
    }

    /** @return Builder<Submission> */
    private function clusterSubmissions(?ResearchCluster $cluster): Builder
    {
        return Submission::with(SubmissionPresenter::WITH)
            ->where('research_cluster_id', $cluster->id ?? 0)
            ->whereNotIn('status', ['draft', 'submitted'])
            ->orderByRaw("status = 'endorsed' DESC")->orderByDesc('id');
    }

    public function analytics(Request $request): View
    {
        $cluster = $this->cluster();
        $scope = $cluster?->academicUnits->pluck('code')->all() ?: ['IBAM', 'CEIS'];
        $filters = $request->only('college', 'year', 'theme', 'sdg');

        return view('analytics.dashboard', [
            'title' => 'Cluster analytics',
            'eyebrow' => 'M7 · Cluster '.($cluster->number ?? 1).' · '.($cluster->name ?? 'Business and Technology'),
            'scope' => $scope, 'filters' => $filters, 'full' => false,
            'd' => Dashboard::build($scope, $filters, false),
            'route' => 'coordinator.analytics',
        ]);
    }

    private function cluster(): ?ResearchCluster
    {
        $user = auth()->user();

        // Only the cluster this coordinator is assigned to (Research clusters, CLAUDE.md §5).
        return ResearchCluster::with('academicUnits')->where('coordinator_user_id', $user?->id)->first();
    }
}
