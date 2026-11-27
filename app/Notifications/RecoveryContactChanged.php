<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the TUA email (and the old recovery email) when a recovery contact changes (CLAUDE.md §7 M1). */
class RecoveryContactChanged extends TuaNotification
{
    public function __construct(private readonly string $what) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'Your recovery contact was changed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line('A recovery contact on your TUA Research Repository account changed: '.$this->what.'.')
            ->line('If you did not do this, contact the URDC right away.');
    }
}
