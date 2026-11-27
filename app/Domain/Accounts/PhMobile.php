<?php

namespace App\Domain\Accounts;

/** Philippine mobile numbers, stored as +639XXXXXXXXX and shown as +63 9XX XXX XXXX (CLAUDE.md §7 M1). */
final class PhMobile
{
    /** Accepts 09171234567, 9171234567, 639171234567, +63 917 123 4567, with spaces, dashes or dots. */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', (string) $input) ?? '';

        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $digits, $m) === 1) {
            return '+63'.$m[1];
        }

        return null;
    }

    public static function format(string $normalized): string
    {
        $n = substr($normalized, 3); // 9XXXXXXXXX

        return sprintf('+63 %s %s %s', substr($n, 0, 3), substr($n, 3, 3), substr($n, 6));
    }
}
