<?php

namespace App\Console\Commands;

use App\Domain\Admin\SystemSettings;
use App\Domain\Clustering\ClusterRunner;
use App\Jobs\RunClustering;
use App\Models\ClusterRun;
use App\Models\ResearchRecord;
use Illuminate\Console\Command;

/**
 * Nightly check of the clustering schedule in System settings (CLAUDE.md §7 M6): each semester or
 * each month, or after N new published records. Queues a run when one is due; the run still needs
 * the URDC's confirmation before search and dashboards use it.
 */
class ScheduledClustering extends Command
{
    protected $signature = 'clustering:scheduled {--dry-run : Say whether a run is due without queuing it}';

    protected $description = 'Queue a clustering run when the schedule in System settings says one is due';

    public function handle(): int
    {
        $reason = self::dueReason();
        if ($reason === null) {
            $this->info('No clustering run is due.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->info("A run is due: {$reason}.");

            return self::SUCCESS;
        }

        $run = ClusterRunner::queue(null);
        RunClustering::dispatch($run);
        $this->info("Queued {$run->public_id}: {$reason}.");

        return self::SUCCESS;
    }

    public static function dueReason(): ?string
    {
        if (ClusterRun::whereIn('state', ['queued', 'running'])->exists()) {
            return null;
        }

        $schedule = SystemSettings::current('schedule');
        $last = ClusterRun::whereIn('state', ['done', 'current', 'archived'])->latest('id')->first();
        $published = ResearchRecord::published()->count();
        if ($published < 2) {
            return null;
        }
        if ($last === null) {
            return 'no run yet';
        }

        $days = ['semester' => 150, 'month' => 28][$schedule['every'] ?? 'manual'] ?? null;
        if ($days !== null && ($last->finished_at === null || $last->finished_at->lt(now()->subDays($days)))) {
            return $schedule['every'] === 'semester' ? 'a new semester' : 'a new month';
        }

        $after = (int) ($schedule['after_new_records'] ?? 0);
        $new = $published - (int) $last->n_records;
        if ($after > 0 && $new >= $after) {
            return "{$new} new published records";
        }

        return null;
    }
}
