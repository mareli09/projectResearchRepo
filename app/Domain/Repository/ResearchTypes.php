<?php

namespace App\Domain\Repository;

use Illuminate\Support\Str;

/** Research types and degree levels (CLAUDE.md §5). */
final class ResearchTypes
{
    public const TYPES = [
        'undergraduate_thesis' => 'Undergraduate thesis',
        'capstone_project' => 'Capstone project',
        'graduate_thesis' => 'Graduate thesis',
        'faculty_research' => 'Faculty research',
    ];

    public const DEGREE_LEVELS = [
        'undergraduate' => 'Undergraduate',
        'graduate' => 'Graduate',
        'faculty' => 'Faculty',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'withdrawn' => 'Withdrawn',
    ];

    public const SOURCES = [
        'submission' => 'Submission',
        'google_form_migration' => 'Google Form migration',
        'legacy_import' => 'Legacy import',
    ];

    /** Accepts a key ("capstone_project") or a label ("Capstone project"), any case. */
    public static function typeKey(?string $value): ?string
    {
        return self::match($value, self::TYPES);
    }

    public static function degreeKey(?string $value): ?string
    {
        return self::match($value, self::DEGREE_LEVELS);
    }

    public static function sourceKey(?string $value): ?string
    {
        return self::match($value, self::SOURCES);
    }

    /** The degree level that normally goes with a research type. */
    public static function degreeFor(string $type): string
    {
        return match ($type) {
            'graduate_thesis' => 'graduate',
            'faculty_research' => 'faculty',
            default => 'undergraduate',
        };
    }

    /** @param  array<string, string>  $options */
    private static function match(?string $value, array $options): ?string
    {
        $needle = Str::snake(Str::lower(trim((string) $value)));
        if ($needle === '') {
            return null;
        }

        foreach ($options as $key => $label) {
            if ($needle === $key || $needle === Str::snake(Str::lower($label))) {
                return $key;
            }
        }

        return null;
    }
}
