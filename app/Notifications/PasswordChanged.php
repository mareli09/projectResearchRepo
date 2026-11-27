<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the TUA email and the recovery email after a password change or reset (CLAUDE.md §7 M1, §12). */
class PasswordChanged extends TuaNotification
{
    public function __construct(private readonly string $how) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'Your TUA Research Repository password was changed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line('The password of your TUA Research Repository account was changed ('.$this->how.').')
            ->line('All other sessions were signed out.')
            ->line('If you did not do this, contact the URDC right away.');
    }
}
