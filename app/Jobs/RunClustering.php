<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Clustering\ClusterRunner;
use App\Models\ClusterRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Clustering always runs as a background job, never during a web request (CLAUDE.md §1 rule 11). */
class RunClustering implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly ClusterRun $run)
    {
        // Longer than the 90-second retry window of the default queue (config/queue.php 'long').
        $this->onConnection((string) config('tua.long_queue'))->onQueue('long');
    }

    public function handle(ClusterRunner $runner): void
    {
        $runner->run($this->run);
    }

    public function failed(Throwable $e): void
    {
        $this->run->update(['state' => 'failed', 'finished_at' => now()]);
        Audit::record(AuditEvent::CLUSTER_RUN, "Clustering {$this->run->public_id} failed", $this->run, ['error' => mb_substr($e->getMessage(), 0, 300)]);
    }
}
