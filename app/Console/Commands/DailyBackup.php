<?php

namespace App\Console\Commands;

use App\Domain\Admin\Backups;
use App\Jobs\RunBackup;
use Illuminate\Console\Command;

/** Daily encrypted backup (CLAUDE.md §7 M9). Scheduled in routes/console.php. */
class DailyBackup extends Command
{
    protected $signature = 'repository:backup';

    protected $description = 'Make the encrypted database and file backup, then remove copies past the retention period';

    public function handle(): int
    {
        // Runs in the scheduler's own process; the job class holds the checks and the logging.
        (new RunBackup(null, 'daily'))->handle();
        $ok = Backups::lastRun()['ok'] ?? false;
        $ok ? $this->info('Backup completed.') : $this->error('Backup failed. See Backups in the app.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
