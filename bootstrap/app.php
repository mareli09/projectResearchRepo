<?php

use App\Http\Middleware\ApplyRuntimeSettings;
use App\Http\Middleware\EnsureAccountCanSignIn;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UploadRedirectAsJson;
use App\Http\Middleware\ValidateCsrfTokenWithoutCookie;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Security headers on every response, error pages included (CLAUDE.md §14).
        $middleware->append(SecurityHeaders::class);
        // CSRF without the script-readable XSRF-TOKEN cookie (forms, meta tag and Livewire carry the token).
        $middleware->web(replace: [ValidateCsrfToken::class => ValidateCsrfTokenWithoutCookie::class]);
        // Runs on every web request, so a deactivated or expired account is signed out at once.
        $middleware->web(append: [EnsureAccountCanSignIn::class, UploadRedirectAsJson::class]);
        // Idle session timeout from System settings, applied before the session starts.
        $middleware->web(prepend: [ApplyRuntimeSettings::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
