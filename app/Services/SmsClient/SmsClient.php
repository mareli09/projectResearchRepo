<?php

namespace App\Services\SmsClient;

/**
 * [Not in manuscript] Philippine SMS gateway, used only for recovery codes (CLAUDE.md §2, §7 M1).
 */
interface SmsClient
{
    /** @param  string  $mobile  normalized +639XXXXXXXXX */
    public function send(string $mobile, string $message): bool;
}
