<?php

namespace App\Domain\Accounts;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Shared last step of every password reset (CLAUDE.md §7 M1 step 4):
 * set the password, sign out all other sessions, notify the TUA and recovery emails, log it.
 */
final class PasswordResetter
{
    public function reset(User $user, string $password, string $how, ?string $keepSessionId = null): void
    {
        $user->forceFill([
            'password' => $password,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();

        $user->notify(new PasswordChanged($how));
        if ($user->recovery_email && $user->recovery_email_verified_at) {
            Notification::route('mail', $user->recovery_email)->notify(new PasswordChanged($how));
        }

        Audit::record(AuditEvent::PASSWORD_RESET, 'Password changed ('.$how.')', $user, ['how' => $how], causer: $user);
    }
}
