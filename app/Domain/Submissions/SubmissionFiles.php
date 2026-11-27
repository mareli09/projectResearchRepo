<?php

namespace App\Domain\Submissions;

use App\Domain\Repository\DuplicateFileException;
use App\Domain\Repository\FileSlots;
use App\Models\RecordFile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files of a submission, in the same five typed slots as records (CLAUDE.md §7 M2, M3).
 * A new version gets a new SHA-256 and older versions are kept.
 */
final class SubmissionFiles
{
    /** Same file checks as records (extension, real MIME type, size). Null when fine. */
    public static function check(string $slot, string $localPath, string $originalName): ?string
    {
        return FileSlots::check($slot, $localPath, $originalName);
    }

    /** @throws DuplicateFileException when an identical file is already stored anywhere else */
    public static function ensureNotDuplicate(string $localPath, string $slot, ?Submission $own = null): string
    {
        $sha = (string) hash_file('sha256', $localPath);
        $match = FileSlots::exactMatchAny($sha, $own?->id);
        if ($match !== null) {
            throw new DuplicateFileException($match, $slot);
        }

        return $sha;
    }

    public function store(Submission $sub, string $slot, string $localPath, string $originalName, ?User $by): RecordFile
    {
        $sha = self::ensureNotDuplicate($localPath, $slot, $sub);
        $ext = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = 'submissions/'.$sub->public_id.'/'.$slot.'/'.Str::uuid().'.'.$ext;

        $stream = fopen($localPath, 'rb');
        Storage::disk('research')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return DB::transaction(function () use ($sub, $slot, $path, $originalName, $localPath, $sha, $by): RecordFile {
            RecordFile::where('submission_id', $sub->id)->where('slot', $slot)->where('is_current', true)->update(['is_current' => false]);

            return RecordFile::create([
                'submission_id' => $sub->id,
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
    }

    /** @return list<string> required slots with no current file */
    public static function missingSlots(Submission $sub): array
    {
        $present = RecordFile::where('submission_id', $sub->id)->where('is_current', true)->pluck('slot')->all();

        return array_values(array_filter(
            array_keys(FileSlots::slots()),
            fn (string $slot) => FileSlots::slots()[$slot]['required']
                && ! ($slot === 'imrad' && $sub->imrad_not_applicable)
                && ! in_array($slot, $present, true),
        ));
    }
}
