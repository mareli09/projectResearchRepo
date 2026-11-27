<?php

namespace App\Http\Controllers\Design;

use App\Domain\Analytics\Dashboard;
use App\Domain\Llm\Suggestions;
use App\Domain\Repository\FileSlots;
use App\Domain\Repository\RepositorySettings;
use App\Domain\Site\Corpus;
use App\Domain\Site\DesignData;
use App\Domain\Submissions\SubmissionPresenter;
use App\Domain\Submissions\SubmissionRules;
use App\Domain\Submissions\Workflow;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Student screens (CLAUDE.md §7 M2–M4, M7). The submission workflow is live from Sprint 3. */
class StudentController extends Controller
{
    public function home(Request $request): View
    {
        $mine = $this->mine($request->user());
        $current = $mine->first(fn (Submission $s) => $s->status !== 'returned') ?? $mine->first();
        $college = $request->user()->academicUnit->code ?? 'CEIS';

        return view('student.home', [
            'sub' => $current ? SubmissionPresenter::present($current) : null,
            'matches' => $current ? array_slice(SubmissionPresenter::matches($current), 0, 4) : [],
            'returned' => $mine->where('status', 'returned')->map(fn ($s) => SubmissionPresenter::present($s))->values(),
            'college' => $college,
            'recent' => ResearchRecord::published()->whereHas('academicUnit', fn ($q) => $q->where('code', $college))
                ->with('authors', 'academicUnit', 'program', 'keywords')->orderByDesc('year')->orderByDesc('views')->limit(3)->get(),
        ]);
    }

    public function submit(Request $request): View
    {
        $user = $request->user();

        return view('student.submit', [
            'slots' => FileSlots::slots(),
            'sdgs' => Sdg::orderBy('id')->get(),
            'suggestion' => DesignData::metadataSuggestion(),
            'programs' => Program::where('academic_unit_id', $user->academic_unit_id)->orderBy('name')->get(),
            'advisers' => User::role(User::ROLE_ADVISER)->orderByRaw('academic_unit_id = ? DESC', [$user->academic_unit_id])->orderBy('name')->get(['id', 'name', 'academic_unit_id']),
            'maxMb' => RepositorySettings::maxFileMb(),
        ]);
    }

    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = SubmissionRules::normalize($request->except('files'));
        $data['files'] = $request->file('files', []);
        Validator::make($data, SubmissionRules::rules(), SubmissionRules::messages())->validate();

        /** @var array<string, UploadedFile> $uploads */
        $uploads = array_filter((array) $request->file('files', []), fn ($f, $slot) => array_key_exists($slot, FileSlots::slots()), ARRAY_FILTER_USE_BOTH);
        $sub = $workflow->submit($request->user(), $data, $uploads);

        // What the student did with each LLM suggestion (§10 "Logging").
        $log = json_decode((string) $request->input('llm_log', '[]'), true);
        $meta = json_decode((string) $request->input('llm_meta', '{}'), true);
        if (is_array($log) && $log !== []) {
            Suggestions::record($sub, $request->user(), array_slice($log, 0, 20), is_array($meta) ? $meta : []);
        }

        return redirect()->route('student.status', ['id' => $sub->public_id])
            ->with('status', "Submitted as {$sub->public_id}. Your adviser was notified by email.");
    }

    /** LLM metadata suggestions for the submit form (JSON). Only the title and abstract are sent (§10). */
    public function suggest(Request $request, Suggestions $suggestions): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['required', 'string', 'max:5000'],
        ]);

        return response()->json($suggestions->metadata($data['title'], $data['abstract']));
    }

    public function status(Request $request): View
    {
        $mine = $this->mine($request->user());
        $sub = $mine->firstWhere('public_id', $request->query('id')) ?? $mine->first();

        return view('student.status', [
            'sub' => $sub ? SubmissionPresenter::present($sub) : null,
            'mine' => $mine,
            'matches' => $sub ? SubmissionPresenter::matches($sub) : [],
            'slots' => FileSlots::slots(),
            'files' => $sub ? $sub->files()->where('is_current', true)->get()->keyBy('slot') : collect(),
        ]);
    }

    public function resubmit(Request $request, Workflow $workflow): RedirectResponse
    {
        $sub = Submission::where('public_id', $request->input('id'))->firstOrFail();
        Gate::authorize('resubmit', $sub);
        $request->validate(['note' => ['nullable', 'string', 'max:1000'], 'files' => ['array'], 'files.*' => ['file']]);

        $uploads = array_filter((array) $request->file('files', []), fn ($f, $slot) => array_key_exists($slot, FileSlots::slots()), ARRAY_FILTER_USE_BOTH);
        $workflow->resubmit($request->user(), $sub, $uploads, $request->input('note'));

        return redirect()->route('student.status', ['id' => $sub->public_id])->with('status', 'Resubmitted. Your adviser was notified.');
    }

    public function summary(Request $request): View
    {
        $college = $request->user()->academicUnit->code ?? 'CEIS';
        $recs = Corpus::records()->where('college', $college);
        $themeCounts = $recs->countBy('theme');
        $latest = (int) ($recs->max('year') ?? 2025);
        $kw = Dashboard::values($recs->where('year', '>=', $latest - 1), 'keywords')->countBy()->sortDesc()->take(8);

        return view('student.summary', [
            'college' => $college,
            'kpis' => [
                ["{$college} studies", number_format($recs->count())],
                ["Published in {$latest}", number_format($recs->where('year', $latest)->count())],
                ['Capstone projects', number_format($recs->where('type', 'capstone_project')->count())],
            ],
            'themeChart' => ['tooltip' => (object) [], 'series' => [['type' => 'pie', 'radius' => ['45%', '72%'], 'itemStyle' => ['borderColor' => 'var(--surface)', 'borderWidth' => 2], 'label' => ['color' => 'var(--muted)', 'width' => 110, 'overflow' => 'break'],
                'data' => collect(Corpus::themeLabels())->map(fn ($l, $id) => ['name' => $l, 'value' => (int) ($themeCounts[$id] ?? 0), 'key' => $id])->filter(fn ($d) => $d['value'] > 0)->values()->all()]]],
            'kwChart' => ['tooltip' => (object) [], 'xAxis' => ['type' => 'value'], 'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => $kw->keys()->all()],
                'series' => [['type' => 'bar', 'barMaxWidth' => 16, 'itemStyle' => ['color' => 'var(--c2)'], 'data' => $kw->values()->all()]]],
        ]);
    }

    /** @return Collection<int, Submission> */
    private function mine(User $user): Collection
    {
        return Submission::with(SubmissionPresenter::WITH)->where('student_user_id', $user->id)->orderByDesc('id')->get();
    }
}
