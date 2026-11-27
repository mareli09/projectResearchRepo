<?php

namespace App\Http\Middleware;

use App\Domain\Admin\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies settings saved in "System settings" that Laravel reads from config, before the
 * session starts: the idle session timeout (CLAUDE.md §7 M1, M9).
 */
class ApplyRuntimeSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            config(['session.lifetime' => SystemSettings::idleMinutes()]);
        } catch (Throwable) {
            // Database not ready (fresh install): keep the config value.
        }

        return $next($request);
    }
}
