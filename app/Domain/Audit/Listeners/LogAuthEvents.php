<?php

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

/** Writes sign-in events to the activity log. Never logs the password that was tried. */
class LogAuthEvents
{
    public function login(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            Audit::record(AuditEvent::LOGIN, 'Signed in', $event->user, causer: $event->user);
        }
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            Audit::record(AuditEvent::LOGOUT, 'Signed out', $event->user, causer: $event->user);
        }
    }

    public function failed(Failed $event): void
    {
        $email = Str::lower((string) ($event->credentials['email'] ?? ''));
        $user = $event->user instanceof User ? $event->user : User::where('email', $email)->first();

        Audit::record(AuditEvent::LOGIN_FAILED, 'Wrong email or password', $user, ['email' => $email], causer: $user);
    }

    public function lockout(Lockout $event): void
    {
        $email = Str::lower((string) $event->request->input('email'));

        Audit::record(AuditEvent::LOGIN_FAILED, 'Sign-in paused after too many attempts', User::where('email', $email)->first(), [
            'email' => $email,
            'reason' => 'rate_limited',
        ]);
    }
}
