<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * CSRF protection (CLAUDE.md §14) without the script-readable XSRF-TOKEN cookie: every form has its
 * hidden _token, scripts send the token from <meta name="csrf-token"> (X-CSRF-TOKEN), and Livewire
 * sends its own. So no cookie needs to be readable by JavaScript (ZAP "Cookie No HttpOnly Flag").
 */
class ValidateCsrfTokenWithoutCookie extends ValidateCsrfToken
{
    /** @var bool */
    protected $addHttpCookie = false;
}
