<?php

namespace App\Domain\Site;

use App\Domain\Repository\ResearchTypes;
use App\Models\ResearchRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only view of the published records for the design screens and dashboards.
 *
 * Themes: until the first clustering run (Sprint 6), each record gets the design theme whose
 * terms best match its keywords, and a 2-D position near that theme's centre. This lets the
 * theme map, theme filters and heat map be designed with real record counts. Replaced by
 * cluster_runs / record_cluster in Sprint 6.
 */
final class Corpus
{
    /** Design themes = the prototype's 7 themes, with the labels shown until coordinators confirm real ones. */
    public const THEMES = [
        1 => ['label' => 'Health informatics and digital care', 'llm' => 'Digital health and telemedicine', 'terms' => ['health', 'patient', 'telemedicine', 'record', 'clinic', 'electronic', 'readiness', 'appointment', 'prescription', 'medication'], 'center' => [-6, 4]],
        2 => ['label' => 'Nursing practice and community health', 'llm' => 'Nursing practice and community health', 'terms' => ['nursing', 'care', 'community', 'maternal', 'wellbeing', 'burnout', 'hygiene', 'breastfeeding', 'prevention', 'mental'], 'center' => [-8, -3]],
        3 => ['label' => 'Machine learning and decision support', 'llm' => 'Machine learning for prediction and classification', 'terms' => ['machine', 'learning', 'prediction', 'classification', 'algorithm', 'tf-idf', 'cosine similarity', 'dropout', 'detection', 'forecasting'], 'center' => [6, 5]],
        4 => ['label' => 'Web and mobile information systems', 'llm' => 'Web and mobile management systems', 'terms' => ['system', 'web', 'mobile', 'management', 'application', 'web-based', 'inventory', 'attendance', 'thesis archive', 'repository'], 'center' => [7, -2]],
        5 => ['label' => 'Business, marketing and MSMEs', 'llm' => 'Marketing and MSME business practices', 'terms' => ['business', 'marketing', 'consumer', 'msme', 'financial', 'social', 'customer', 'digital', 'employee', 'service'], 'center' => [1, -7]],
        6 => ['label' => 'Education and learning outcomes', 'llm' => 'Teaching strategies and learning outcomes', 'terms' => ['learning', 'students', 'teaching', 'education', 'academic', 'blended', 'reading', 'motivation', 'teacher', 'gamified'], 'center' => [-1, 8]],
        7 => ['label' => 'Laboratory diagnostics and public health', 'llm' => 'Laboratory diagnostics and public health', 'terms' => ['laboratory', 'specimen', 'diagnostic', 'bacterial', 'screening', 'antibacterial', 'water', 'parasites', 'glucose', 'antibiotic'], 'center' => [-3, -8]],
    ];

    /** @return Collection<int, array<string, mixed>> */
    public static function records(): Collection
    {
        $rows = Cache::remember('design.corpus.v1.'.ResearchRecord::published()->max('updated_at'), 600, /** @return list<array<string, mixed>> */ function (): array {
            return ResearchRecord::published()
                ->with(['academicUnit:id,code', 'program:id,name', 'keywords:id,term', 'sdgs:id'])
                ->get()
                ->map(function (ResearchRecord $r): array {
                    $keywords = $r->keywords->pluck('term')->all();
                    $theme = self::themeFor($keywords, $r->academicUnit->code);
                    $seed = crc32($r->public_id);
                    $jit = fn (int $shift): float => ((($seed >> $shift) & 0xFF) / 255 + (($seed >> ($shift + 8)) & 0xFF) / 255 - 1) * 3.2;

                    return [
                        'id' => $r->id, 'public_id' => $r->public_id, 'title' => $r->title, 'year' => $r->year,
                        'type' => $r->research_type, 'type_label' => ResearchTypes::TYPES[$r->research_type],
                        'college' => $r->academicUnit->code, 'program' => $r->program?->name, 'adviser' => $r->adviser_name,
                        'keywords' => $keywords, 'sdgs' => $r->sdgs->pluck('id')->all(),
                        'views' => $r->views, 'downloads' => $r->downloads, 'complete' => $r->is_complete,
                        'theme' => $theme,
                        'x' => round(self::THEMES[$theme]['center'][0] + $jit(0), 2),
                        'y' => round(self::THEMES[$theme]['center'][1] + $jit(16), 2),
                    ];
                })->all();
        });

        /** @var list<array<string, mixed>> $rows */
        return collect($rows);
    }

    /** @param  list<string>  $keywords */
    public static function themeFor(array $keywords, string $college): int
    {
        $best = 0;
        $bestScore = 0;
        foreach (self::THEMES as $id => $t) {
            $score = count(array_intersect($keywords, $t['terms']));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$id, $score];
            }
        }

        return $best ?: match ($college) {
            'SLCN' => 2, 'CMT' => 7, 'IBAM' => 5, 'CASE' => 6, 'CAHS' => 1, default => 4,
        };
    }

    /** @return array<int, string> */
    public static function themeLabels(): array
    {
        return array_map(fn (array $t) => $t['label'], self::THEMES);
    }
}
