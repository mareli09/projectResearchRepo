<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/** One-time activation link, valid for 48 hours (CLAUDE.md §1 rule 2). */
class ActivationLink extends TuaNotification implements ShouldBeEncrypted
{
    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'Activate your TUA Research Repository account';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine())
            ->greeting('Welcome to the TUA Research Repository')
            ->line('Open the link below to activate your account. The link works once and expires in 48 hours.')
            ->action('Activate account', route('activation.show', $this->token))
            ->line('If you did not create this account, you can ignore this email.');
    }
}
