<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Mask;
use App\Domain\Accounts\PasswordResetter;
use App\Domain\Accounts\PasswordRules;
use App\Domain\Accounts\RecoveryCodeService;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Forgot password and account recovery (CLAUDE.md §7 M1):
 *  1. TUA email (same answer whether or not the account exists)
 *  2. choose a channel, with only part of each contact shown
 *  3. a 6-digit code is sent
 *  4. code + new password; other sessions are signed out
 */
class ForgotPasswordController extends Controller
{
    public function __construct(private readonly RecoveryCodeService $codes) {}

    public function create(): View
    {
        return view('guest.forgot.email');
    }

    public function storeEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'string', 'email', RegisterController::tuaOnly()]], [
            'email' => 'Use your TUA email address (@'.config('tua.email_domain').').',
        ]);

        $request->session()->put('recovery.email', $request->input('email'));
        $request->session()->forget('recovery.channel');

        return redirect()->route('password.channel');
    }

    public function channel(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('recovery.email');
        if (! is_string($email)) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();
        // An unknown email still shows the TUA email option, so the page does not reveal whether it exists.
        $channels = $user ? RecoveryCodeService::channelsFor($user) : ['tua_email' => Mask::email($email)];

        return view('guest.forgot.channel', ['channels' => $channels]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');
        if (! is_string($email)) {
            return redirect()->route('password.request');
        }

        $request->validate(['channel' => ['required', Rule::in(['tua_email', 'recovery_email', 'sms'])]]);
        $channel = (string) $request->input('channel');
        $user = User::where('email', $email)->first();

        if ($user !== null && $user->deactivated_at === null) {
            $allowed = RecoveryCodeService::channelsFor($user);
            if (! array_key_exists($channel, $allowed)) {
                // An unverified or switched-off contact can never receive a code.
                return back()->withErrors(['channel' => 'Choose one of the listed options.']);
            }

            $destination = match ($channel) {
                'recovery_email' => (string) $user->recovery_email,
                'sms' => (string) $user->recovery_mobile,
                default => $user->email,
            };
            $this->codes->issue($user, $channel, RecoveryCodeService::PURPOSE_RESET, $destination);
            Audit::record(AuditEvent::RECOVERY, 'Recovery code sent', $user, ['channel' => $channel], causer: $user);
        }

        $request->session()->put('recovery.channel', $channel);

        return redirect()->route('password.code')->with('status', 'If the account exists, we sent a 6-digit code. It expires in '
            .config('tua.auth.recovery_code_minutes').' minutes.');
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! is_string($request->session()->get('recovery.channel'))) {
            return redirect()->route('password.request');
        }

        return view('guest.forgot.code');
    }

    public function reset(Request $request, PasswordResetter $resetter): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');
        $channel = $request->session()->get('recovery.channel');
        if (! is_string($email) || ! is_string($channel)) {
            return redirect()->route('password.request');
        }

        // Password rules are checked first so a typo in the password does not use up a code try.
        $request->validate([
            'code' => ['required', 'digits:6'],
            'password' => PasswordRules::rules(),
        ]);

        $user = User::where('email', $email)->first();
        if ($user === null || $user->deactivated_at !== null
            || ! $this->codes->verify($user, RecoveryCodeService::PURPOSE_RESET, (string) $request->input('code'), $channel)) {
            if ($user !== null) {
                Audit::record(AuditEvent::RECOVERY, 'Wrong or expired recovery code', $user, ['channel' => $channel], causer: $user);
            }

            return back()->withErrors(['code' => 'This code is wrong or has expired. Check it, or send a new code.']);
        }

        $resetter->reset($user, (string) $request->input('password'), 'recovery code by '.str_replace('_', ' ', $channel));
        $request->session()->forget('recovery');

        return redirect()->route('login')->with('status', 'Your password was changed. Sign in with the new password.');
    }
}
