<?php

namespace App\Http\Controllers\Design;

use App\Domain\Repository\FileSlots;
use App\Domain\Site\Corpus;
use App\Domain\Submissions\SubmissionPresenter;
use App\Domain\Submissions\Workflow;
use App\Http\Controllers\Controller;
use App\Models\ResearchRecord;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Faculty adviser screens (CLAUDE.md §7 M3, M7). */
class AdviserController extends Controller
{
    public function queue(Request $request): View
    {
        $mine = Submission::with(SubmissionPresenter::WITH)->where('adviser_user_id', $request->user()->id)
            ->where('status', '!=', 'draft')->orderByRaw("status = 'submitted' DESC")->orderByDesc('submitted_at')->get();

        return view('adviser.queue', [
            'mine' => $mine->map(fn ($s) => SubmissionPresenter::present($s)),
            'kpis' => [
                ['Waiting for you', $mine->where('status', 'submitted')->count(), 'inbox', 'gold'],
                ['Advisee studies in progress', $mine->whereNotIn('status', ['published', 'rejected'])->count(), 'status', 'accent'],
                ['Published', $mine->where('status', 'published')->count(), 'check', 'ok'],
            ],
        ]);
    }

    public function review(Request $request): View
    {
        $mine = Submission::with(SubmissionPresenter::WITH)->where('adviser_user_id', $request->user()->id)->where('status', '!=', 'draft');
        $sub = (clone $mine)->where('public_id', $request->query('id'))->first()
            ?? (clone $mine)->where('status', 'submitted')->orderBy('submitted_at')->first()
            ?? $mine->orderByDesc('id')->first();

        return view('adviser.review', [
            'sub' => $sub ? SubmissionPresenter::present($sub) : null,
            'matches' => $sub ? SubmissionPresenter::matches($sub) : [],
            'slots' => FileSlots::slots(),
            'files' => $sub ? $sub->files()->where('is_current', true)->get()->keyBy('slot') : collect(),
            'can' => $sub && $request->user()->can('endorse', $sub),
        ]);
    }

    public function decide(Request $request, Workflow $workflow): RedirectResponse
    {
        $sub = Submission::where('public_id', $request->input('id'))->firstOrFail();
        Gate::authorize('endorse', $sub);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['endorse', 'return'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['decision'] === 'endorse') {
            $workflow->endorse($request->user(), $sub, $data['reason'] ?? null);
            $msg = 'Endorsed. It goes to the research coordinator, and the student was notified.';
        } else {
            $workflow->returnToStudent($request->user(), $sub, (string) ($data['reason'] ?? ''));
            $msg = 'Returned to the student with your reason.';
        }

        return redirect()->route('adviser.queue')->with('status', "{$sub->public_id}: {$msg}");
    }

    public function supervised(Request $request): View
    {
        $name = $request->user()->name;
        $recs = Corpus::records()->where('adviser', $name);
        $years = $recs->countBy('year')->sortKeys();
        $themes = $recs->countBy('theme');

        return view('adviser.supervised', [
            'name' => $name,
            'kpis' => [
                ['Total supervised', number_format($recs->count())],
                ['Since 2023', number_format($recs->where('year', '>=', 2023)->count())],
                ['Downloads of your advisees’ work', number_format($recs->sum('downloads'))],
            ],
            'yearChart' => ['tooltip' => (object) [], 'xAxis' => ['type' => 'category', 'data' => $years->keys()->all()], 'yAxis' => ['type' => 'value'],
                'series' => [['type' => 'bar', 'barMaxWidth' => 28, 'itemStyle' => ['color' => 'var(--c1)'], 'data' => $years->values()->all()]]],
            'themeChart' => ['tooltip' => (object) [], 'series' => [['type' => 'pie', 'radius' => ['45%', '72%'], 'itemStyle' => ['borderColor' => 'var(--surface)', 'borderWidth' => 2], 'label' => ['color' => 'var(--muted)', 'width' => 110, 'overflow' => 'break'],
                'data' => collect(Corpus::themeLabels())->map(fn ($l, $id) => ['name' => $l, 'value' => (int) ($themes[$id] ?? 0), 'key' => $id])->filter(fn ($d) => $d['value'] > 0)->values()->all()]]],
            'recent' => ResearchRecord::published()->where('adviser_name', $name)->with('authors', 'academicUnit', 'program', 'keywords')->orderByDesc('year')->limit(4)->get(),
        ]);
    }
}
