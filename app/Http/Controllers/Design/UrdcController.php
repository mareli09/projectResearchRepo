<?php

namespace App\Http\Controllers\Design;

use App\Domain\Analytics\Dashboard;
use App\Domain\Analytics\DashboardExport;
use App\Domain\Submissions\SubmissionPresenter;
use App\Domain\Submissions\Workflow;
use App\Http\Controllers\Controller;
use App\Models\RecordFile;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** URDC administrator screens (CLAUDE.md §7 M3–M7). */
class UrdcController extends Controller
{
    public function analytics(Request $request): View
    {
        $scope = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'];
        $filters = Dashboard::filters($request, $scope);

        return view('analytics.dashboard', [
            'title' => 'University research dashboard',
            'eyebrow' => 'M7 · University-wide · refreshed nightly 2:15 AM',
            'scope' => $scope, 'filters' => $filters, 'full' => true,
            'd' => Dashboard::build($scope, $filters, true),
            'route' => 'urdc.analytics',
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $scope = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'];

        return DashboardExport::response($format, 'University research dashboard', 'University-wide', $scope,
            Dashboard::filters($request, $scope), true, $request->user());
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
}
