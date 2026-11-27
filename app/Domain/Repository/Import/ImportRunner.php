<?php

namespace App\Domain\Repository\Import;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Repository\FileSlots;
use App\Domain\Repository\RecordWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;
use ZipArchive;

/** Imports the rows that passed the check. Rows with errors or duplicates are skipped. */
final class ImportRunner
{
    public function __construct(private readonly RecordWriter $writer, private readonly FileSlots $files) {}

    /**
     * @param  list<ImportRow>  $rows
     * @return array{imported: int, published: int, skipped: int, failed: list<string>}
     */
    public function run(array $rows, ?string $zipPath, bool $publishComplete, User $by, string $fileName): array
    {
        $zip = null;
        if ($zipPath !== null) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $zip = null;
            }
        }

        $imported = $published = $skipped = 0;
        $failed = [];

        foreach ($rows as $row) {
            if (! $row->isReady()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $zip, $publishComplete, $by, &$published): void {
                    $record = $this->writer->create($row->data, $row->source, 'import');
                    $record->imrad_not_applicable = $row->imradNotApplicable;
                    $record->save();

                    foreach ($row->files as $slot => $entry) {
                        $tmp = tempnam(sys_get_temp_dir(), 'tua-imp');
                        file_put_contents($tmp, (string) $zip?->getFromName($entry));
                        try {
                            $this->files->store($record, $slot, $tmp, basename($entry), $by);
                        } finally {
                            @unlink($tmp);
                        }
                    }
                    FileSlots::refreshCompleteness($record);

                    if ($publishComplete && RecordWriter::publishBlockers($record->refresh()) === []) {
                        $this->writer->publish($record, $by);
                        $published++;
                    }
                });
                $imported++;
            } catch (Throwable $e) {
                $failed[] = "Row {$row->line}: ".$e->getMessage();
            }
        }

        $zip?->close();

        Audit::record(AuditEvent::IMPORT, "Bulk import of {$fileName}", null, [
            'file' => $fileName,
            'imported' => $imported,
            'published' => $published,
            'skipped' => $skipped,
            'failed' => count($failed),
        ], $by);

        return compact('imported', 'published', 'skipped', 'failed');
    }
}
