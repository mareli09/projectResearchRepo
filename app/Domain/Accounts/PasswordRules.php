<?php

namespace App\Domain\Accounts;

use Closure;
use Illuminate\Validation\Rules\Password;

/**
 * At least 10 characters, with at least one letter and one number,
 * and not on the common-password list (CLAUDE.md §7 M1).
 */
final class PasswordRules
{
    /** @return array<int, mixed> */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min((int) config('tua.auth.password_min_length'))->letters()->numbers(),
            self::notCommon(),
        ];
    }

    public static function notCommon(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && in_array(strtolower($value), self::commonList(), true)) {
                $fail('This password is too common. Choose a different one.');
            }
        };
    }

    /** @return list<string> */
    public static function commonList(): array
    {
        static $list = null;

        if ($list === null) {
            $lines = file(resource_path('data/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $list = array_values(array_map('strtolower', array_filter($lines, fn (string $l) => ! str_starts_with($l, '#'))));
        }

        return $list;
    }
}
