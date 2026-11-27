<?php

namespace App\Http\Middleware;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account was deactivated or expired while signed in (CLAUDE.md §7 M1).
 * Evaluator accounts (§17) work only in the evaluation copy, and every page they open is logged.
 */
class EnsureAccountCanSignIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $isEvaluator = $user->user_type === 'evaluator';
        if (! $user->canSignIn() || ($isEvaluator && ! config('tua.evaluation_copy'))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your session ended because this account can no longer sign in.']);
        }

        // Every evaluator action is logged (§17). Changes are logged by the actions themselves;
        // this adds each page opened.
        if ($isEvaluator && $request->isMethod('GET') && ! $request->ajax()) {
            Audit::record(AuditEvent::VIEW, 'Evaluator opened /'.ltrim($request->path(), '/'), null, [
                'evaluator' => true, 'path' => '/'.ltrim($request->path(), '/'),
            ], $user);
        }

        return $next($request);
    }
}
