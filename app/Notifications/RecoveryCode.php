<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/** 6-digit code for password recovery or for verifying a recovery contact (CLAUDE.md §7 M1). */
class RecoveryCode extends TuaNotification implements ShouldBeEncrypted
{
    public function __construct(
        private readonly string $code,
        private readonly string $purpose,
        private readonly bool $bySms = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->bySms ? SmsChannel::class : 'mail'];
    }

    protected function subjectLine(): string
    {
        return $this->purpose === 'password_reset'
            ? 'Your TUA Research Repository recovery code'
            : 'Verify your recovery contact';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('tua.auth.recovery_code_minutes');

        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line($this->purpose === 'password_reset'
                ? 'Use this code to set a new password:'
                : 'Use this code to verify this address as your recovery contact:')
            ->line('**'.$this->code.'**')
            ->line("The code expires in {$minutes} minutes and works once.")
            ->line('If you did not ask for this code, you can ignore this message. Your password has not changed.');
    }

    public function toSms(object $notifiable): string
    {
        return 'TUA Research Repository code: '.$this->code.'. Expires in '
            .config('tua.auth.recovery_code_minutes').' minutes. Do not share it.';
    }
}
