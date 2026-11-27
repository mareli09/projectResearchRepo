<?php

namespace App\Http\Controllers\Design;

use App\Domain\Analytics\Dashboard;
use App\Domain\Site\DesignData;
use App\Domain\Submissions\SubmissionPresenter;
use App\Domain\Submissions\Workflow;
use App\Http\Controllers\Controller;
use App\Models\RecordFile;
use App\Models\ResearchRecord;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** URDC administrator screens (CLAUDE.md §7 M3–M7). */
class UrdcController extends Controller
{
    public function analytics(Request $request): View
    {
        $scope = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'];
        $filters = $request->only('college', 'year', 'theme', 'sdg');

        return view('analytics.dashboard', [
            'title' => 'University research dashboard',
            'eyebrow' => 'M7 · University-wide · refreshed nightly 2:15 AM',
            'scope' => $scope, 'filters' => $filters, 'full' => true,
            'd' => Dashboard::build($scope, $filters, true),
            'route' => 'urdc.analytics',
        ]);
    }

    public function approvals(): View
    {
        $base = Submission::with(SubmissionPresenter::WITH);

        return view('urdc.approvals', [
            'queue' => (clone $base)->where('status', 'reviewed')->orderBy('updated_at')->get()->map(fn ($s) => SubmissionPresenter::present($s)),
            'published' => (clone $base)->where('status', 'published')->orderByDesc('updated_at')->limit(8)->get()->map(fn ($s) => SubmissionPresenter::present($s)),
            'files' => RecordFile::whereIn('submission_id', Submission::where('status', 'reviewed')->pluck('id'))->where('is_current', true)->get()->groupBy('submission_id'),
        ]);
    }

    public function decide(Request $request, Workflow $workflow): RedirectResponse
    {
        $sub = Submission::where('public_id', $request->input('id'))->firstOrFail();
        Gate::authorize('decide', $sub);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'imrad_not_applicable' => ['nullable', 'boolean'],
        ]);

        try {
            $workflow->urdcDecide($request->user(), $sub, $data['decision'], $data['reason'] ?? null,
                $request->has('imrad_not_applicable') ? $request->boolean('imrad_not_applicable') : null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision_'.$sub->public_id => 'Cannot publish yet. '.$e->getMessage()]);
        }

        $msg = ['approve' => 'Approved and published. It is now in the catalog.', 'return' => 'Returned to the student.', 'reject' => 'Rejected. The student was notified.'][$data['decision']];

        return redirect()->route('urdc.approvals')->with('status', "{$sub->public_id}: {$msg}");
    }

    public function requests(): View
    {
        $titles = ResearchRecord::whereIn('public_id', collect(DesignData::accessRequests())->pluck('rec'))->pluck('title', 'public_id');

        return view('urdc.requests', ['requests' => DesignData::accessRequests(), 'titles' => $titles]);
    }

    public function ai(): View
    {
        $L = DesignData::llmStats();
        $cm = DesignData::confusion();
        $acc = ($cm['tp'] + $cm['tn']) / array_sum($cm);
        $pre = $cm['tp'] / ($cm['tp'] + $cm['fp']);
        $rec = $cm['tp'] / ($cm['tp'] + $cm['fn']);
        $labels = DesignData::labels();
        $rated = collect($labels)->where('rating', '>', 0);
        $runs = array_reverse(DesignData::runs());
        $kinds = ['keyword' => 'Keywords', 'sdg' => 'SDG tags', 'type' => 'Research type', 'label' => 'Theme labels'];

        return view('urdc.ai', [
            'cm' => $cm,
            'metrics' => ['Accuracy' => $acc, 'Precision' => $pre, 'Recall' => $rec, 'F1-score' => 2 * $pre * $rec / ($pre + $rec)],
            'run' => DesignData::runs()[0],
            'meaningful' => collect($labels)->where('confirmed', true)->where('action', '!=', 'rejected')->count().' of '.count($labels),
            'rates' => collect($kinds)->map(fn ($n, $k) => ['name' => $n, 'rate' => $L[$k]['accepted'] / max(1, array_sum($L[$k])) * 100, 'total' => array_sum($L[$k])])->all(),
            'avgRating' => $rated->avg('rating') ?? 0, 'ratedCount' => $rated->count(),
            'llmChart' => ['tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']], 'legend' => (object) [],
                'xAxis' => ['type' => 'value', 'max' => 100, 'axisLabel' => ['formatter' => '{value}%']], 'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => array_values($kinds)],
                'series' => collect([['accepted', 'Accepted', 'var(--ok)'], ['edited', 'Edited', 'var(--c1)'], ['rejected', 'Rejected', 'var(--bad)']])->map(fn ($s) => [
                    'name' => $s[1], 'type' => 'bar', 'stack' => 'a', 'barMaxWidth' => 22, 'itemStyle' => ['color' => $s[2]],
                    'data' => array_map(fn ($k) => round($L[$k][$s[0]] / max(1, array_sum($L[$k])) * 100, 1), array_keys($kinds)),
                ])->all()],
            'runsChart' => ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [], 'xAxis' => ['type' => 'category', 'data' => array_column($runs, 'id')],
                'yAxis' => [['type' => 'value', 'min' => .15], ['type' => 'value', 'min' => 1.5, 'splitLine' => ['show' => false]]],
                'series' => [['name' => 'Silhouette', 'type' => 'line', 'data' => array_column($runs, 'sil'), 'itemStyle' => ['color' => 'var(--c1)']],
                    ['name' => 'Davies–Bouldin', 'type' => 'line', 'yAxisIndex' => 1, 'data' => array_column($runs, 'db'), 'itemStyle' => ['color' => 'var(--c2)']]]],
        ]);
    }
}
