<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\PasswordResetter;
use App\Domain\Accounts\PasswordRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * One-time reset link from assisted recovery (CLAUDE.md §7 M1). The token is Laravel's
 * password broker token, stored hashed in password_reset_tokens and valid for 60 minutes.
 */
class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('guest.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function store(Request $request, PasswordResetter $resetter): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => PasswordRules::rules(),
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($resetter): void {
                $resetter->reset($user, $password, 'assisted recovery link');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This link is not valid or has expired. Ask the URDC for a new one.']);
        }

        return redirect()->route('login')->with('status', 'Your password was changed. Sign in with the new password.');
    }
}
