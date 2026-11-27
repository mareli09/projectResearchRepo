<?php

namespace App\Http\Controllers\Urdc;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Repository\DuplicateFileException;
use App\Domain\Repository\FileSlots;
use App\Domain\Repository\RecordRules;
use App\Domain\Repository\RecordWriter;
use App\Domain\Repository\RepositorySettings;
use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** "Records and metadata" for the URDC (CLAUDE.md §7 M2). */
class RecordController extends Controller
{
    public function __construct(private readonly RecordWriter $writer, private readonly FileSlots $slots) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'college' => ['nullable', 'string', 'max:10'],
            'missing' => ['nullable', Rule::in(['keywords', 'sdg', 'files'])],
            'status' => ['nullable', Rule::in(['draft', 'published', 'withdrawn'])],
        ]);

        $query = ResearchRecord::query()
            ->with('academicUnit')
            ->withCount('keywords', 'sdgs')
            ->when(($filters['status'] ?? null) === 'withdrawn', fn (Builder $q) => $q->onlyTrashed())
            ->when(in_array($filters['status'] ?? null, ['draft', 'published'], true), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] ?? null, function (Builder $q, string $term): void {
                $q->where(fn (Builder $w) => $w
                    ->where('public_id', $term)
                    ->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhereHas('authors', fn (Builder $a) => $a->where('name', 'like', '%'.$term.'%')));
            })
            ->when($filters['college'] ?? null, fn (Builder $q, string $code) => $q->whereHas('academicUnit', fn (Builder $u) => $u->where('code', $code)))
            ->when(($filters['missing'] ?? null) === 'keywords', fn (Builder $q) => $q->missingKeywords())
            ->when(($filters['missing'] ?? null) === 'sdg', fn (Builder $q) => $q->missingSdg())
            ->when(($filters['missing'] ?? null) === 'files', fn (Builder $q) => $q->incompleteFiles())
            ->orderByDesc('year')->orderByDesc('id');

        return view('urdc.records.index', [
            'records' => $query->paginate(20)->withQueryString(),
            'units' => AcademicUnit::orderBy('code')->get(['id', 'code']),
            'filters' => $filters,
            'counts' => [
                'all' => ResearchRecord::count(),
                'keywords' => ResearchRecord::query()->missingKeywords()->count(),
                'sdg' => ResearchRecord::query()->missingSdg()->count(),
                'files' => ResearchRecord::query()->incompleteFiles()->count(),
            ],
        ]);
    }

    public function edit(ResearchRecord $record): View
    {
        $record->load('authors', 'keywords', 'sdgs', 'academicUnit', 'program');

        return view('urdc.records.edit', [
            'record' => $record,
            'units' => AcademicUnit::with('programs')->orderBy('code')->get(),
            'sdgs' => Sdg::orderBy('id')->get(),
            'files' => $record->currentFiles()->get()->keyBy('slot'),
            'older' => $record->files()->where('is_current', false)->latest('id')->get()->groupBy('slot'),
            'slots' => FileSlots::slots(),
            'blockers' => RecordWriter::publishBlockers($record),
            'wordLimit' => RepositorySettings::abstractWordLimit(),
            'maxMb' => RepositorySettings::maxFileMb(),
        ]);
    }

    public function update(Request $request, ResearchRecord $record): RedirectResponse
    {
        $data = RecordRules::normalize($request->all());
        Validator::make($data, RecordRules::rules($data['academic_unit_id'] ?? null), RecordRules::messages())->validate();

        $this->writer->update($record, $data, $request->user());

        return back()->with('status', 'Metadata saved.');
    }

    public function upload(Request $request, ResearchRecord $record, string $slot): RedirectResponse
    {
        abort_unless(array_key_exists($slot, FileSlots::slots()), 404);
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Choose a file.']);

        $file = $request->file('file');
        $error = FileSlots::check($slot, (string) $file->getRealPath(), $file->getClientOriginalName());
        if ($error !== null) {
            return back()->withErrors(['file_'.$slot => $error]);
        }

        try {
            $stored = $this->slots->store($record, $slot, (string) $file->getRealPath(), $file->getClientOriginalName(), $request->user());
        } catch (DuplicateFileException $e) {
            return back()->withErrors(['file_'.$slot => "This exact file is already stored for {$e->matchingPublicId}."]);
        }

        Audit::record(AuditEvent::UPLOAD, 'Uploaded '.FileSlots::slots()[$slot]['label'], $record, [
            'slot' => $slot, 'file_id' => $stored->id, 'sha256' => $stored->sha256, 'size' => $stored->size,
        ]);

        return back()->with('status', FileSlots::slots()[$slot]['label'].' uploaded.');
    }

    public function imrad(Request $request, ResearchRecord $record): RedirectResponse
    {
        $na = $request->boolean('not_applicable');
        $old = $record->imrad_not_applicable;
        $record->imrad_not_applicable = $na;
        $record->save();
        FileSlots::refreshCompleteness($record);

        if ($old !== $na) {
            Audit::record(AuditEvent::METADATA_EDIT, 'IMRAD version marked '.($na ? 'not applicable' : 'required'), $record,
                ['public_id' => $record->public_id], old: ['imrad_not_applicable' => $old], new: ['imrad_not_applicable' => $na]);
        }

        return back()->with('status', $na ? 'IMRAD version marked not applicable.' : 'IMRAD version is required again.');
    }

    public function publish(Request $request, ResearchRecord $record): RedirectResponse
    {
        try {
            $this->writer->publish($record, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['publish' => 'This record cannot be published yet. '.$e->getMessage()]);
        }

        return back()->with('status', $record->public_id.' is published.');
    }

    public function withdraw(Request $request, ResearchRecord $record): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Give a reason for withdrawing.']);
        $this->writer->withdraw($record, $data['reason'], $request->user());

        return redirect()->route('urdc.records')->with('status', $record->public_id.' was withdrawn. It stays in the log and can be found under Withdrawn.');
    }
}
