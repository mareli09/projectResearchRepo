<?php

namespace App\Notifications\Channels;

use App\Services\SmsClient\SmsClient;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use RuntimeException;

/** [Not in manuscript] Sends recovery codes through the SMS gateway. */
class SmsChannel
{
    public function __construct(private readonly SmsClient $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? ($notifiable->routes[self::class] ?? null)
            : null;

        if (! is_string($to) || ! method_exists($notification, 'toSms')) {
            return;
        }

        if (! $this->sms->send($to, (string) $notification->toSms($notifiable))) {
            throw new RuntimeException('The SMS gateway did not accept the message.');
        }
    }
}
