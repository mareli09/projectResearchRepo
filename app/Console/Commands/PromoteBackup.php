<?php

namespace App\Console\Commands;

use App\Domain\Admin\BackupRestorer;
use App\Domain\Admin\Backups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Promotes a backup to the live database (CLAUDE.md §7 M9: "restore to a staging database first,
 * then promote"). Command line only, never a button: it replaces live data.
 */
class PromoteBackup extends Command
{
    protected $signature = 'backup:promote {file : Backup file name, as shown on the Backups page} {--force : Skip the typed confirmation}';

    protected $description = 'Replace the live database and research files with a backup that passed the staging check';

    public function handle(BackupRestorer $restorer): int
    {
        $file = (string) $this->argument('file');
        $last = Backups::lastRestore();
        if (($last['file'] ?? null) !== $file || ($last['ok'] ?? false) !== true) {
            $this->error("Restore {$file} to staging first (Backups page or php artisan repository:restore-test {$file}).");

            return self::FAILURE;
        }

        $this->warn('This replaces the live database with the backup from '.Backups::formatDate($last['at'] ?? null).' (restored to staging).');
        $this->warn('Changes made after that backup will be lost. A safety backup of the current data is made first.');
        if (! $this->option('force') && $this->ask('Type the backup file name to continue') !== $file) {
            $this->info('Nothing was changed.');

            return self::FAILURE;
        }

        Artisan::call('down', ['--retry' => 60]);
        try {
            $this->info('Making a safety backup of the current data...');
            if (Artisan::call('repository:backup') !== self::SUCCESS) {
                $this->error('The safety backup failed, so nothing was changed.');

                return self::FAILURE;
            }
            $restorer->toLive($file);
            $this->info("Promoted {$file}. Run php artisan migrate --force if the app is newer than the backup.");
        } catch (Throwable $e) {
            $this->error('Promote failed: '.$e->getMessage().' The safety backup is on the Backups page.');

            return self::FAILURE;
        } finally {
            Artisan::call('up');
        }

        return self::SUCCESS;
    }
}
