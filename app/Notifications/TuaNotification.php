<?php

namespace App\Notifications;

use App\Models\MailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Base for every notice the system sends (CLAUDE.md §12): queued, retried 3 times,
 * and a final failure is written to mail_log so it shows in System health.
 */
abstract class TuaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    abstract protected function subjectLine(): string;

    public function failed(Throwable $e): void
    {
        MailLog::create([
            'to' => '(queued notice)',
            'subject' => $this->subjectLine(),
            'status' => 'failed',
            'attempts' => $this->tries,
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
            'notification' => static::class,
        ]);
    }
}
