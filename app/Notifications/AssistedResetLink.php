<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/** One-time reset link sent to a new recovery contact after an ID check by staff (CLAUDE.md §7 M1). */
class AssistedResetLink extends TuaNotification implements ShouldBeEncrypted
{
    public function __construct(private readonly string $token, private readonly string $tuaEmail) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function subjectLine(): string
    {
        return 'Reset your TUA Research Repository password';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line('The URDC checked your ID and set this address as the recovery contact for '.$this->tuaEmail.'.')
            ->action('Set a new password', route('password.reset', ['token' => $this->token, 'email' => $this->tuaEmail]))
            ->line("The link works once and expires in {$minutes} minutes.");
    }
}
