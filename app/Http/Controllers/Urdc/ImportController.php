<?php

namespace App\Http\Controllers\Urdc;

use App\Domain\Repository\Import\ImportChecker;
use App\Domain\Repository\Import\ImportRunner;
use App\Domain\Repository\Import\ImportTemplate;
use App\Domain\Repository\Import\SpreadsheetRows;
use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * "Bulk import" for the URDC (CLAUDE.md §7 M2): template, upload, dry-run preview
 * with row-level errors and duplicates, then import.
 */
class ImportController extends Controller
{
    private const DIR = 'imports';

    public function create(): View
    {
        return view('urdc.import.create', ['columns' => ImportTemplate::COLUMNS]);
    }

    public function template(string $format): StreamedResponse|BinaryFileResponse
    {
        if ($format === 'xlsx') {
            return Excel::download(new ImportTemplateExport, 'tua-import-template.xlsx');
        }
        abort_unless($format === 'csv', 404);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            foreach (ImportTemplate::rows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'tua-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Upload and dry run: nothing is written to the repository here. */
    public function preview(Request $request, ImportChecker $checker): View|RedirectResponse
    {
        $request->validate([
            'sheet' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'files_zip' => ['nullable', 'file', 'mimes:zip', 'max:512000'],
        ], [
            'sheet.required' => 'Choose a CSV or XLSX file.',
            'sheet.mimes' => 'The spreadsheet must be a .csv or .xlsx file.',
            'files_zip.mimes' => 'The files must be in one .zip file.',
        ]);

        $token = (string) Str::uuid();
        $dir = self::DIR.'/'.$token;
        $sheet = $request->file('sheet');
        $sheetExt = Str::lower($sheet->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $sheet->storeAs($dir, 'sheet.'.$sheetExt, 'local');
        if ($request->hasFile('files_zip')) {
            $request->file('files_zip')->storeAs($dir, 'files.zip', 'local');
        }

        $request->session()->put('import.'.$token, [
            'sheet' => $dir.'/sheet.'.$sheetExt,
            'zip' => $request->hasFile('files_zip') ? $dir.'/files.zip' : null,
            'name' => $sheet->getClientOriginalName(),
        ]);

        return $this->showPreview($token, $request, $checker);
    }

    public function show(Request $request, string $token, ImportChecker $checker): View|RedirectResponse
    {
        return $this->showPreview($token, $request, $checker);
    }

    public function store(Request $request, string $token, ImportChecker $checker, ImportRunner $runner): RedirectResponse
    {
        $upload = $request->session()->get('import.'.$token);
        if (! is_array($upload)) {
            return redirect()->route('urdc.import')->withErrors(['sheet' => 'This upload has expired. Upload the file again.']);
        }

        // Check again: the repository may have changed since the preview.
        $rows = $checker->check(SpreadsheetRows::read(Storage::disk('local')->path($upload['sheet'])), $this->zipPath($upload));
        $result = $runner->run($rows, $this->zipPath($upload), $request->boolean('publish_complete'), $request->user(), $upload['name']);

        Storage::disk('local')->deleteDirectory(self::DIR.'/'.$token);
        $request->session()->forget('import.'.$token);

        $message = "Imported {$result['imported']} records ({$result['published']} published, the rest saved as drafts). Skipped {$result['skipped']} rows with errors or duplicates.";

        return redirect()->route('urdc.records')->with('status', $message)->with('import_failed', $result['failed']);
    }

    private function showPreview(string $token, Request $request, ImportChecker $checker): View|RedirectResponse
    {
        $upload = $request->session()->get('import.'.$token);
        if (! is_array($upload)) {
            return redirect()->route('urdc.import')->withErrors(['sheet' => 'This upload has expired. Upload the file again.']);
        }

        try {
            $raw = SpreadsheetRows::read(Storage::disk('local')->path($upload['sheet']));
        } catch (Throwable) {
            return redirect()->route('urdc.import')->withErrors(['sheet' => 'The spreadsheet could not be read. Use the template.']);
        }

        $missing = array_diff(['title', 'abstract', 'college', 'research_type', 'year', 'keywords', 'authors'], array_keys($raw[0] ?? []));
        if ($raw === [] || $missing !== []) {
            return redirect()->route('urdc.import')->withErrors(['sheet' => $raw === []
                ? 'The spreadsheet has no rows.'
                : 'Missing columns: '.implode(', ', $missing).'. Use the template.']);
        }

        $rows = $checker->check($raw, $this->zipPath($upload));

        return view('urdc.import.preview', [
            'token' => $token,
            'name' => $upload['name'],
            'hasZip' => $upload['zip'] !== null,
            'rows' => $rows,
            'counts' => [
                'ready' => count(array_filter($rows, fn ($r) => $r->status() === 'ready')),
                'error' => count(array_filter($rows, fn ($r) => $r->status() === 'error')),
                'duplicate' => count(array_filter($rows, fn ($r) => $r->status() === 'duplicate')),
            ],
        ]);
    }

    /** @param  array<string, mixed>  $upload */
    private function zipPath(array $upload): ?string
    {
        return $upload['zip'] ? Storage::disk('local')->path($upload['zip']) : null;
    }
}
