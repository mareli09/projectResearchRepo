<?php

namespace App\Domain\Repository;

use App\Models\Setting;

/** Runtime limits from the settings table, with config/tua.php as the default (CLAUDE.md §7 M2, M9). */
final class RepositorySettings
{
    public static function abstractWordLimit(): int
    {
        return (int) Setting::valueOf('repository.abstract_word_limit', config('tua.repository.abstract_word_limit'));
    }

    public static function maxFileMb(): int
    {
        return (int) Setting::valueOf('files.max_mb', config('tua.repository.max_file_mb'));
    }

    public static function keywordsMin(): int
    {
        return (int) config('tua.repository.keywords_min');
    }

    public static function keywordsMax(): int
    {
        return (int) config('tua.repository.keywords_max');
    }

    public static function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
