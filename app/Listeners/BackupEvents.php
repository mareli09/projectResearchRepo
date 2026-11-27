<?php

namespace App\Listeners;

use App\Domain\Admin\Backups;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use Illuminate\Support\Str;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * Logs every backup event (CLAUDE.md §13 "backup") and emails the System administrators
 * when something fails (§12). The package's own emails are off in config/backup.php.
 */
class BackupEvents
{
    public function succeeded(BackupWasSuccessful $e): void
    {
        $newest = $e->backupDestination->newestBackup();
        Audit::record(AuditEvent::BACKUP, 'Backup completed on '.$this->diskLabel($e->backupDestination->diskName()), null, [
            'result' => 'ok',
            'disk' => $e->backupDestination->diskName(),
            'file' => $newest ? basename($newest->path()) : null,
            'bytes' => $newest ? (int) $newest->sizeInBytes() : null,
        ]);
    }

    public function failed(BackupHasFailed $e): void
    {
        $where = $e->backupDestination ? ' on '.$this->diskLabel($e->backupDestination->diskName()) : '';
        $error = Str::limit($e->exception->getMessage(), 300);
        Audit::record(AuditEvent::BACKUP, "Backup failed{$where}: {$error}", null, ['result' => 'failed', 'disk' => $e->backupDestination?->diskName()]);
        Backups::alertAdmins('Backup failed', "The backup failed{$where}: {$error}");
    }

    public function cleanupFailed(CleanupHasFailed $e): void
    {
        $error = Str::limit($e->exception->getMessage(), 300);
        Audit::record(AuditEvent::BACKUP, "Cleanup of old backups failed: {$error}", null, ['result' => 'failed']);
        Backups::alertAdmins('Cleanup of old backups failed', $error);
    }

    public function unhealthy(UnhealthyBackupWasFound $e): void
    {
        $problem = $e->backupDestinationStatus->getHealthCheckFailure()?->exception()->getMessage() ?? 'unknown problem';
        Audit::record(AuditEvent::BACKUP, 'Backup check failed: '.Str::limit($problem, 300), null, ['result' => 'failed']);
        Backups::alertAdmins('Backups need attention', $problem);
    }

    private function diskLabel(string $disk): string
    {
        return $disk === Backups::OFFSITE_DISK ? 'the off-server copy' : 'this server';
    }
}
