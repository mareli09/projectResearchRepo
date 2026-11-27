<?php

namespace App\Jobs;

use App\Domain\Admin\BackupRestorer;
use App\Domain\Admin\Backups;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** "Restore to staging" and the monthly restore test (CLAUDE.md §7 M9). */
class RestoreBackupToStaging implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $file, public readonly ?User $by = null, public readonly string $kind = 'manual')
    {
        $this->onConnection((string) config('tua.long_queue'))->onQueue('long');
    }

    public function handle(BackupRestorer $restorer): void
    {
        $restorer->toStaging($this->file, $this->by, $this->kind);
    }

    public function failed(): void
    {
        Cache::forget(Backups::BUSY_KEY);
    }
}
