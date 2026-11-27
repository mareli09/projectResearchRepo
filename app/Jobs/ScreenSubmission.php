<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Screening\Screener;
use App\Domain\Screening\Thresholds;
use App\Models\Submission;
use App\Notifications\WorkflowNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Similarity screening as a queue job (CLAUDE.md §7 M3 "On submit", step 4). */
class ScreenSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly Submission $submission) {}

    public function handle(Screener $screener): void
    {
        $check = $screener->screen($this->submission);
        $sub = $this->submission->fresh(['student']);
        if ($sub?->student) {
            $sub->student->notify(new WorkflowNotice(
                "Screening result for {$sub->public_id}",
                'Highest match '.number_format((float) $check->top_score, 2).' ('.strtolower(Thresholds::label($check->flag)).'). '
                    .($check->flag === 'original' ? 'The closest studies are shown as related studies.' : 'Your coordinator will review it. Nothing is rejected automatically.'),
                route('student.status', ['id' => $sub->public_id]),
                $sub->public_id,
            ));
        }
    }

    public function failed(Throwable $e): void
    {
        Audit::record(AuditEvent::SERVICE, "Analytics service: screening of {$this->submission->public_id} failed", $this->submission, [
            'service' => 'analytics', 'purpose' => 'screen', 'error' => mb_substr($e->getMessage(), 0, 300),
        ]);
    }
}
