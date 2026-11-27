<?php

namespace App\Domain\Accounts;

/** Shows only part of a contact, for choosing a recovery channel (CLAUDE.md §7 M1). */
final class Mask
{
    public static function email(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $len = mb_strlen($local);
        $shown = $len <= 2 ? mb_substr($local, 0, 1).'*' : mb_substr($local, 0, 1).str_repeat('*', max(3, $len - 2)).mb_substr($local, -1);

        return $shown.'@'.$domain;
    }

    public static function mobile(string $normalized): string
    {
        return '+63 9** *** '.substr($normalized, -4);
    }
}
