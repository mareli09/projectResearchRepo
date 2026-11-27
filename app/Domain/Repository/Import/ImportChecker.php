<?php

namespace App\Domain\Repository\Import;

use App\Domain\Repository\FileSlots;
use App\Domain\Repository\RecordRules;
use App\Domain\Repository\ResearchTypes;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Checks every row of a bulk import without writing anything (the dry run, CLAUDE.md §7 M2):
 * row-level errors, and duplicates by title and by file hash, against the repository
 * and against earlier rows of the same file.
 */
final class ImportChecker
{
    /** @var array<string, int> college code => id */
    private array $units;

    /** @var array<int, array<string, int>> unit id => [lowercase program name => id] */
    private array $programs = [];

    public function __construct()
    {
        $this->units = AcademicUnit::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [Str::upper($code) => (int) $id])->all();
        foreach (Program::all(['id', 'academic_unit_id', 'name']) as $p) {
            $this->programs[$p->academic_unit_id][Str::lower($p->name)] = (int) $p->id;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows  from SpreadsheetRows
     * @return list<ImportRow>
     */
    public function check(array $rows, ?string $zipPath = null): array
    {
        $zip = null;
        if ($zipPath !== null) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $zip = null;
            }
        }

        $seenTitles = [];
        $seenHashes = [];
        $results = [];

        foreach ($rows as $i => $raw) {
            $row = new ImportRow($i + 2, $raw); // +2: heading row is row 1
            $data = $this->toRecordData($raw, $row);

            $validator = Validator::make($data, RecordRules::rules($data['academic_unit_id']), RecordRules::messages());
            foreach ($validator->errors()->all() as $message) {
                $row->errors[] = $message;
            }
            $row->data = $data;

            // Duplicate by title: repository and earlier rows.
            $key = self::titleKey((string) $data['title']);
            if ($key !== '') {
                $existing = ResearchRecord::withTrashed()->whereRaw('LOWER(title) = ?', [Str::lower((string) $data['title'])])->value('public_id')
                    ?? $this->findByTitleKey($key);
                if ($existing !== null) {
                    $row->duplicates[] = "Same title as {$existing}.";
                } elseif (isset($seenTitles[$key])) {
                    $row->duplicates[] = "Same title as row {$seenTitles[$key]}.";
                } else {
                    $seenTitles[$key] = $row->line;
                }
            }

            $this->checkFiles($raw, $row, $zip, $seenHashes);
            $results[] = $row;
        }

        $zip?->close();

        return $results;
    }

    /** Lowercase, letters and digits only, single spaces: catches case and punctuation differences. */
    public static function titleKey(string $title): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($title)))));
    }

    private function findByTitleKey(string $key): ?string
    {
        // Narrow with LIKE on the first word, then compare keys in PHP.
        $first = explode(' ', $key)[0];

        return ResearchRecord::withTrashed()
            ->where('title', 'like', '%'.$first.'%')
            ->get(['public_id', 'title'])
            ->first(fn (ResearchRecord $r) => self::titleKey($r->title) === $key)
            ?->public_id;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function toRecordData(array $raw, ImportRow $row): array
    {
        $get = fn (string $k): string => trim((string) ($raw[$k] ?? ''));

        $code = Str::upper($get('college'));
        $unitId = $this->units[$code] ?? null;
        if ($code !== '' && $unitId === null) {
            $row->errors[] = "Unknown college \"{$code}\".";
        }

        $programId = null;
        if ($get('program') !== '' && $unitId !== null) {
            $programId = $this->programs[$unitId][Str::lower($get('program'))] ?? null;
            if ($programId === null) {
                $row->errors[] = "Program \"{$get('program')}\" is not in {$code}.";
            }
        }

        $type = ResearchTypes::typeKey($get('research_type'));
        if ($get('research_type') !== '' && $type === null) {
            $row->errors[] = "Unknown research type \"{$get('research_type')}\".";
        }

        $degree = $get('degree_level') === '' ? ($type ? ResearchTypes::degreeFor($type) : null) : ResearchTypes::degreeKey($get('degree_level'));
        if ($get('degree_level') !== '' && $degree === null) {
            $row->errors[] = "Unknown degree level \"{$get('degree_level')}\".";
        }

        $source = $get('source') === '' ? 'google_form_migration' : ResearchTypes::sourceKey($get('source'));
        if (! in_array($source, ['google_form_migration', 'legacy_import'], true)) {
            $row->errors[] = 'Source must be Google Form migration or Legacy import.';
            $source = 'google_form_migration';
        }
        $row->source = $source;

        return RecordRules::normalize([
            'title' => $get('title'),
            'abstract' => $get('abstract'),
            'authors' => $get('authors'),
            'adviser_name' => $get('adviser'),
            'academic_unit_id' => $unitId,
            'program_id' => $programId,
            'research_type' => $type,
            'degree_level' => $degree,
            'year' => $get('year'),
            'keywords' => $get('keywords'),
            'sdgs' => $get('sdgs'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, int>  $seenHashes
     */
    private function checkFiles(array $raw, ImportRow $row, ?ZipArchive $zip, array &$seenHashes): void
    {
        foreach (ImportTemplate::FILE_COLUMNS as $slot => $column) {
            $name = trim((string) ($raw[$column] ?? ''));
            if ($slot === 'imrad' && in_array(Str::lower($name), ['n/a', 'na', 'not applicable'], true)) {
                $row->imradNotApplicable = true;

                continue;
            }
            if ($name === '') {
                continue;
            }
            if ($zip === null) {
                $row->warnings[] = "File \"{$name}\" is listed but no ZIP was uploaded. The record will be marked incomplete.";

                continue;
            }

            $index = $zip->locateName($name, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
            if ($index === false) {
                $row->errors[] = "File \"{$name}\" is not in the ZIP.";

                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'tua-imp');
            file_put_contents($tmp, (string) $zip->getFromIndex($index));
            $error = FileSlots::check($slot, $tmp, $name);
            if ($error !== null) {
                $row->errors[] = $error." ({$name})";
                @unlink($tmp);

                continue;
            }

            $sha = (string) hash_file('sha256', $tmp);
            @unlink($tmp);
            $match = FileSlots::exactMatch($sha);
            if ($match !== null) {
                $row->duplicates[] = "File \"{$name}\" is identical to a file of {$match}.";
            } elseif (isset($seenHashes[$sha])) {
                $row->duplicates[] = "File \"{$name}\" is identical to a file in row {$seenHashes[$sha]}.";
            } else {
                $seenHashes[$sha] = $row->line;
            }
            $row->files[$slot] = (string) $zip->getNameIndex($index);
        }
    }
}
