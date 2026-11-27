<?php

namespace App\Domain\Accounts;

use App\Domain\Admin\SystemSettings;
use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Fortify's sign-in limiter (per email and IP, 1-minute pause) with the number of attempts
 * taken from System settings instead of the fixed 5 (CLAUDE.md §7 M1, M9).
 */
class SettingsLoginRateLimiter extends LoginRateLimiter
{
    public function tooManyAttempts(Request $request)
    {
        return $this->limiter->tooManyAttempts($this->throttleKey($request), SystemSettings::loginMaxAttempts());
    }
}
