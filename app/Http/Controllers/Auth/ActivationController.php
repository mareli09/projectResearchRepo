<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\ActivationService;
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
        ]);
    }

    public function store(string $token, ActivationService $service): RedirectResponse
    {
        $activation = $service->find($token);

        if ($activation === null || ! $service->activate($activation)) {
            return redirect()->route('activation.show', $token);
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
}
