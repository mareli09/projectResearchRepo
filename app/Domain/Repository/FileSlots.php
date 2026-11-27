<?php

namespace App\Domain\Repository;

use App\Models\RecordFile;
use App\Models\ResearchRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The five typed file slots (CLAUDE.md §7 M2). Checks the real MIME type and the
 * extension, the size limit, stores a SHA-256 for every file, keeps older versions,
 * and keeps research_records.is_complete in sync.
 */
final class FileSlots
{
    /** Real MIME types accepted for each extension. */
    private const MIMES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    /** @return array<string, array{label: string, mimes: list<string>, required: bool, can_be_not_applicable?: bool}> */
    public static function slots(): array
    {
        return config('tua.repository.file_slots');
    }

    /**
     * Checks a local file for a slot. Returns an error message, or null when it is fine.
     */
    public static function check(string $slot, string $localPath, string $originalName): ?string
    {
        $def = self::slots()[$slot] ?? null;
        if ($def === null) {
            return 'Unknown file slot.';
        }

        $ext = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $def['mimes'];
        $label = implode(' or ', array_map(fn ($e) => '.'.$e, $allowed));
        if (! in_array($ext, $allowed, true)) {
            return "{$def['label']} must be a {$label} file.";
        }

        $maxMb = RepositorySettings::maxFileMb();
        $size = @filesize($localPath);
        if ($size === false || $size === 0) {
            return 'The file is empty.';
        }
        if ($size > $maxMb * 1024 * 1024) {
            return "The file is larger than {$maxMb} MB.";
        }

        $mime = Str::lower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($localPath));
        if (! in_array($mime, self::MIMES[$ext], true)) {
            return "The file content is not a real {$label} file.";
        }
        // A .docx is a ZIP; make sure it is a Word document and not any ZIP renamed.
        if ($ext === 'docx' && ! self::isWordDocx($localPath)) {
            return 'The file content is not a real .docx file.';
        }

        return null;
    }

    /**
     * Stores a checked file in a slot and returns it. Earlier versions in the slot are kept
     * but stop being current. Refuses a file identical to one stored for a different record.
     *
     * @throws DuplicateFileException
     */
    public function store(ResearchRecord $record, string $slot, string $localPath, string $originalName, ?User $by): RecordFile
    {
        $sha = hash_file('sha256', $localPath) ?: '';

        $match = self::exactMatch($sha, $record->id);
        if ($match !== null) {
            throw new DuplicateFileException($match, $slot);
        }

        $ext = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = $record->public_id.'/'.$slot.'/'.Str::uuid().'.'.$ext;
        $stream = fopen($localPath, 'rb');
        Storage::disk('research')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $file = DB::transaction(function () use ($record, $slot, $path, $originalName, $localPath, $sha, $by): RecordFile {
            RecordFile::where('record_id', $record->id)->where('slot', $slot)->where('is_current', true)->update(['is_current' => false]);

            return RecordFile::create([
                'record_id' => $record->id,
                'slot' => $slot,
                'path' => $path,
                'original_name' => mb_substr(basename($originalName), 0, 255),
                'mime' => (string) (new \finfo(FILEINFO_MIME_TYPE))->file($localPath),
                'size' => (int) filesize($localPath),
                'sha256' => $sha,
                'uploaded_by' => $by?->id,
                'is_current' => true,
            ]);
        });

        self::refreshCompleteness($record);

        return $file;
    }

    /** The public ID of another record that already has this exact file, if any. */
    public static function exactMatch(string $sha256, ?int $exceptRecordId = null): ?string
    {
        return RecordFile::query()
            ->join('research_records', 'research_records.id', '=', 'record_files.record_id')
            ->where('record_files.sha256', $sha256)
            ->when($exceptRecordId, fn ($q) => $q->where('record_files.record_id', '!=', $exceptRecordId))
            ->value('research_records.public_id');
    }

    /**
     * The public ID (TUA-… or SUB-…) of any record or other submission that already stores this exact file.
     * Used by the submission check: only an identical file is stopped automatically (CLAUDE.md §1 rule 6).
     */
    public static function exactMatchAny(string $sha256, ?int $exceptSubmissionId = null): ?string
    {
        $record = RecordFile::query()
            ->join('research_records', 'research_records.id', '=', 'record_files.record_id')
            ->where('record_files.sha256', $sha256)
            ->value('research_records.public_id');
        if ($record !== null) {
            return $record;
        }

        return RecordFile::query()
            ->join('submissions', 'submissions.id', '=', 'record_files.submission_id')
            ->whereNull('record_files.record_id')
            ->where('record_files.sha256', $sha256)
            ->when($exceptSubmissionId, fn ($q) => $q->where('record_files.submission_id', '!=', $exceptSubmissionId))
            ->value('submissions.public_id');
    }

    /** @return list<string> required slots that have no current file */
    public static function missingSlots(ResearchRecord $record): array
    {
        $present = RecordFile::where('record_id', $record->id)->where('is_current', true)->pluck('slot')->all();

        return array_values(array_filter(
            array_keys(self::requiredSlots($record)),
            fn (string $slot) => ! in_array($slot, $present, true),
        ));
    }

    /** @return array<string, array<string, mixed>> */
    public static function requiredSlots(ResearchRecord $record): array
    {
        return array_filter(
            self::slots(),
            fn (array $def, string $slot) => $def['required'] && ! ($slot === 'imrad' && $record->imrad_not_applicable),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function refreshCompleteness(ResearchRecord $record): void
    {
        $record->is_complete = self::missingSlots($record) === [];
        $record->saveQuietly();
    }

    private static function isWordDocx(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('word/document.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ok;
    }
}
