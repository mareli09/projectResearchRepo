<?php

namespace App\Services\SmsClient;

use Illuminate\Support\Facades\Log;

/** Used while RECOVERY_SMS_ENABLED=false or no gateway is chosen. Logs instead of sending. */
class NullSmsClient implements SmsClient
{
    public function send(string $mobile, string $message): bool
    {
        Log::info('sms_disabled', ['to' => substr($mobile, 0, 6).'*****']);

        return false;
    }
}
