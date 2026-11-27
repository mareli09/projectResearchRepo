<?php

namespace App\Domain\Accounts;

use App\Domain\Admin\SystemSettings;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\RecoveryCode as RecoveryCodeNotice;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * [Not in manuscript] 6-digit codes (CLAUDE.md §7 M1): stored hashed, expire in 10 minutes,
 * work once, and stop working after 5 wrong tries.
 */
final class RecoveryCodeService
{
    public const PURPOSE_RESET = 'password_reset';

    public const PURPOSE_VERIFY = 'verify_contact';

    /**
     * Channels the user may receive a code on. Unverified contacts are never offered.
     *
     * @return array<string, string> channel => masked destination
     */
    public static function channelsFor(User $user): array
    {
        $channels = ['tua_email' => Mask::email($user->email)];

        if (SystemSettings::recoveryEnabled('recovery_email') && $user->recovery_email && $user->recovery_email_verified_at) {
            $channels['recovery_email'] = Mask::email($user->recovery_email);
        }

        if (SystemSettings::recoveryEnabled('sms') && $user->recovery_mobile && $user->recovery_mobile_verified_at) {
            $channels['sms'] = Mask::mobile($user->recovery_mobile);
        }

        return $channels;
    }

    /** Sends a code. Earlier unused codes for the same purpose stop working. */
    public function issue(User $user, string $channel, string $purpose, string $destination): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        RecoveryCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        RecoveryCode::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('tua.auth.recovery_code_minutes')),
        ]);

        $route = $channel === 'sms' ? SmsChannel::class : 'mail';
        Notification::route($route, $destination)->notify(new RecoveryCodeNotice($code, $purpose, $channel === 'sms'));
    }

    /** Checks a code once. A wrong code uses up one of the 5 tries. */
    public function verify(User $user, string $purpose, string $code, ?string $channel = null): bool
    {
        $record = RecoveryCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if ($record === null || $record->expires_at->isPast()
            || $record->attempts >= (int) config('tua.auth.recovery_code_max_attempts')) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->used_at = now();
        $record->save();

        return true;
    }
}
