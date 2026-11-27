<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\ActivationService;
use App\Domain\Accounts\PasswordRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Activate account" (CLAUDE.md §7 M1). Opening the link shows a button and the POST activates.
 * This stops mail scanners that prefetch links from using up the one-time token.
 */
class ActivationController extends Controller
{
    public function show(string $token, ActivationService $service): View
    {
        $activation = $service->find($token);

        $state = match (true) {
            $activation === null => 'invalid',
            $activation->isUsed() => 'used',
            $activation->isExpired() => 'expired',
            default => 'ready',
        };

        return view('guest.activate', [
            'state' => $state,
            'token' => $token,
            'email' => $activation?->user->email,
            'setPassword' => $activation !== null && self::setsPassword($activation->user),
        ]);
    }

    public function store(Request $request, string $token, ActivationService $service): RedirectResponse
    {
        $activation = $service->find($token);

        // Evaluator accounts (§17) are made by the System administrator, so the evaluator
        // chooses the password here. Nobody else ever knows it.
        $password = null;
        if ($activation !== null && self::setsPassword($activation->user)) {
            $password = $request->validate(['password' => PasswordRules::rules()])['password'];
        }

        if ($activation === null || ! $service->activate($activation)) {
            return redirect()->route('activation.show', $token);
        }
        if ($password !== null) {
            $activation->user->forceFill(['password' => $password, 'password_changed_at' => now()])->save();
        }

        return redirect()->route('login')->with('status', 'Your account is active. You can sign in now.');
    }

    /** "Send a new link". The answer is the same whether or not the account exists. */
    public function resend(Request $request, ActivationService $service): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', Str::lower((string) $request->input('email')))->first();
        if ($user !== null && ! $user->is_active && $user->deactivated_at === null) {
            $service->issue($user);
        }

        return redirect()->route('login')->with('status',
            'If this account is waiting for activation, a new link is on its way. Older links no longer work.');
    }

    private static function setsPassword(User $user): bool
    {
        return $user->user_type === 'evaluator' && $user->password_changed_at === null;
    }
}
