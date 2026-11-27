<?php

namespace App\Console\Commands;

use App\Domain\Admin\BackupRestorer;
use App\Domain\Admin\Backups;
use Illuminate\Console\Command;

/** Monthly restore test (CLAUDE.md §7 M9): the newest backup is restored to staging and checked. */
class RestoreTest extends Command
{
    protected $signature = 'repository:restore-test {file? : Backup file name; default the newest}';

    protected $description = 'Restore a backup to the staging database and compare it with the live database';

    public function handle(BackupRestorer $restorer): int
    {
        $file = $this->argument('file') ?: (Backups::all()[0]['file'] ?? null);
        if ($file === null) {
            $this->error('There is no backup to test.');
            Backups::alertAdmins('Monthly restore test failed', 'There is no backup on this server to test.');

            return self::FAILURE;
        }

        $result = $restorer->toStaging((string) $file, null, $this->argument('file') ? 'manual' : 'monthly');
        if (! $result['ok']) {
            $this->error("Restore of {$file} failed: {$result['error']}");

            return self::FAILURE;
        }

        $this->info("Restored {$file} to ".BackupRestorer::stagingDatabase()." in {$result['seconds']} s.");
        $this->table(['Table', 'In backup', 'Live now'], collect($result['tables'])->map(fn ($t, $name) => [$name, $t['backup'], $t['live']])->values()->all());

        return self::SUCCESS;
    }
}
