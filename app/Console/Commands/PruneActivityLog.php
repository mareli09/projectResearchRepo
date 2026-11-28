<?php

namespace App\Console\Commands;

use App\Domain\Admin\SystemSettings;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Activity log retention (CLAUDE.md §7 M8): removes entries older than the years set in
 * System settings. Scheduled nightly at 3:00 AM. This is the only path that removes entries:
 * the Activity model refuses every edit and delete, and no screen offers one.
 * The removal itself is written to the log, with the cut-off date and the number removed.
 */
class PruneActivityLog extends Command
{
    protected $signature = 'audit:prune {--dry-run : Count the entries that would be removed, without removing them}';

    protected $description = 'Remove activity log entries older than the retention period in System settings';

    public function handle(): int
    {
        $years = SystemSettings::logRetentionYears();
        $cutoff = now()->subYears($years)->startOfDay();
        $table = (string) config('activitylog.table_name', 'activity_log');
        $old = fn () => DB::table($table)->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d entries are older than %s (%d %s). Nothing was removed.', $old()->count(), $cutoff->toDateString(), $years, $years === 1 ? 'year' : 'years'));

            return self::SUCCESS;
        }

        // In batches, so a large first cleanup does not hold one long lock on the table.
        $removed = 0;
        do {
            $n = $old()->orderBy('id')->limit(1000)->delete();
            $removed += $n;
        } while ($n > 0);

        if ($removed > 0) {
            Audit::record(AuditEvent::SETTING, "Activity log retention: {$removed} entries older than {$cutoff->toDateString()} removed", null,
                ['retention_years' => $years, 'cutoff' => $cutoff->toDateString(), 'removed' => $removed]);
        }
        $this->info("{$removed} entries older than {$cutoff->toDateString()} were removed.");

        return self::SUCCESS;
    }
}
