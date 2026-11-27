<?php

namespace App\Providers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Fortify handles sign in, sign out and password change only (CLAUDE.md §7 M1).
 * Registration, activation and recovery live in app/Http/Controllers/Auth.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('guest.login'));
        Fortify::confirmPasswordView(fn () => view('guest.confirm-password'));
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower((string) $request->input('email'));
            $user = User::where('email', $email)->first();

            // Only @tua.edu.ph may sign in; temporary evaluators exist only in the evaluation copy (§1 rule 1, §17).
            $isEvaluator = config('tua.evaluation_copy') && $user?->user_type === 'evaluator';
            if (! User::isTuaEmail($email) && ! $isEvaluator) {
                throw ValidationException::withMessages(['email' => 'Use your TUA email address (@tua.edu.ph).']);
            }

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null; // Fortify counts the attempt and fires Failed, which is logged.
            }

            if (! $user->canSignIn()) {
                $reason = match (true) {
                    $user->deactivated_at !== null => 'deactivated',
                    $user->expires_at !== null && $user->expires_at->isPast() => 'expired',
                    default => 'not_activated',
                };
                Audit::record(AuditEvent::LOGIN_FAILED, 'Sign-in refused: '.$reason, $user, ['reason' => $reason], causer: $user);

                throw ValidationException::withMessages(['email' => match ($reason) {
                    'deactivated' => 'This account is deactivated. Contact the URDC for help.',
                    'expired' => 'This evaluator account has expired.',
                    default => 'Your account is not active yet. Open the activation link sent to your TUA email.',
                }])->redirectTo(route('login').($reason === 'not_activated' ? '?inactive=1' : ''));
            }

            return $user;
        });

        // Sign-in throttling: Fortify's LoginRateLimiter (5 failed attempts per email and IP, 1-minute pause),
        // see config/fortify.php 'limiters.login' => null.

        // LLM suggestions: per user (the provider also has a global limit, §10).
        RateLimiter::for('llm', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user() === null ? $request->ip() : $request->user()->id)));

        // Recovery: code requests and code checks per email and IP.
        RateLimiter::for('recovery', function (Request $request) {
            $email = Str::lower((string) ($request->input('email') ?? $request->session()->get('recovery.email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
