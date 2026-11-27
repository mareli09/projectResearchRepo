<?php

namespace App\Domain\Audit\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

/** Records every email that left the system in mail_log (CLAUDE.md §12). */
class LogSentMail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $to = implode(', ', array_map(fn (Address $a) => $a->getAddress(), $message->getTo()));

        MailLog::create([
            'to' => mb_substr($to, 0, 255),
            'subject' => mb_substr((string) $message->getSubject(), 0, 255),
            'status' => 'sent',
            'notification' => $event->data['__laravel_notification'] ?? null,
        ]);
    }
}
