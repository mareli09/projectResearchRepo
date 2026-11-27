<?php

namespace App\Domain\Screening;

use App\Models\Setting;

/**
 * Similarity flags (CLAUDE.md §7 M5). Thresholds live in Settings and changes are logged:
 *   score ≥ duplicate (0.80)          → probable duplicate
 *   review (0.60) ≤ score < duplicate → similar, needs review
 *   score < review                    → original
 */
final class Thresholds
{
    /** @return array{duplicate: float, review: float} */
    public static function get(): array
    {
        return once(function (): array {
            $t = (array) Setting::valueOf('screening.thresholds', []);

            return [
                'duplicate' => (float) ($t['duplicate'] ?? config('tua.screening.duplicate_threshold')),
                'review' => (float) ($t['review'] ?? config('tua.screening.review_threshold')),
            ];
        });
    }

    /** @return 'probable_duplicate'|'similar'|'original' */
    public static function flag(float $score): string
    {
        $t = self::get();

        return match (true) {
            round($score, 3) >= $t['duplicate'] => 'probable_duplicate',
            round($score, 3) >= $t['review'] => 'similar',
            default => 'original',
        };
    }

    public static function label(string $flag): string
    {
        return ['probable_duplicate' => 'Probable duplicate', 'similar' => 'Similar, needs review', 'original' => 'Original'][$flag] ?? $flag;
    }
}
