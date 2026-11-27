<?php

namespace App\Jobs;

use App\Domain\Admin\Backups;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "Back up now" and the daily backup (CLAUDE.md §7 M9). The result is logged by
 * App\Listeners\BackupEvents from the package's events.
 */
class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly ?User $by = null, public readonly string $kind = 'manual')
    {
        $this->onConnection((string) config('tua.long_queue'))->onQueue('long');
    }

    public function handle(): void
    {
        if (! Backups::encryptionReady()) {
            // Never write an unencrypted copy of research files and personal data (§14).
            Audit::record(AuditEvent::BACKUP, 'Backup refused: BACKUP_ARCHIVE_PASSWORD is not set', null, ['result' => 'failed', 'kind' => $this->kind], $this->by);
            Backups::alertAdmins('Backup failed', 'The backup did not run because BACKUP_ARCHIVE_PASSWORD is not set in .env. Backups are always encrypted.');

            return;
        }

        Cache::put(Backups::BUSY_KEY, 'Backing up', 3600);
        try {
            // The package fires BackupWasSuccessful or BackupHasFailed; the listener logs and alerts.
            Artisan::call('backup:run', ['--timeout' => $this->timeout]);
            Artisan::call('backup:clean');
        } finally {
            Cache::forget(Backups::BUSY_KEY);
        }
    }

    public function failed(Throwable $e): void
    {
        Cache::forget(Backups::BUSY_KEY);
        Audit::record(AuditEvent::BACKUP, 'Backup failed: '.mb_substr($e->getMessage(), 0, 300), null, ['result' => 'failed', 'kind' => $this->kind], $this->by);
        Backups::alertAdmins('Backup failed', 'The backup job stopped with an error: '.mb_substr($e->getMessage(), 0, 300));
    }
}
