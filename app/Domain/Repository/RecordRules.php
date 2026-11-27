<?php

namespace App\Domain\Repository;

use App\Models\Keyword;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Metadata rules shared by the edit form and bulk import (CLAUDE.md §7 M2):
 * title, abstract (word limit), authors, adviser, college, program, degree level,
 * research type, year, 3–5 keywords and SDG tags.
 */
final class RecordRules
{
    /**
     * Turns free-text fields into arrays: authors one per line or separated by ";",
     * keywords separated by "," or ";", SDGs as numbers separated by "," or ";".
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $split = fn (mixed $v, string $pattern): array => is_array($v)
            ? array_values(array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== ''))
            : array_values(array_filter(array_map('trim', preg_split($pattern, (string) $v) ?: []), fn ($x) => $x !== ''));

        $out = $input;
        $out['title'] = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')));
        $out['abstract'] = trim((string) ($input['abstract'] ?? ''));
        $out['authors'] = $split($input['authors'] ?? '', '/[\r\n;]+/');
        $out['keywords'] = array_values(array_unique(array_map(
            [Keyword::class, 'normalize'],
            $split($input['keywords'] ?? '', '/[,;\r\n]+/'),
        )));
        $out['sdgs'] = array_values(array_unique(array_map('intval', $split($input['sdgs'] ?? '', '/[,;\s]+/'))));
        $out['adviser_name'] = trim((string) ($input['adviser_name'] ?? '')) ?: null;
        $out['program_id'] = ($input['program_id'] ?? '') === '' ? null : $input['program_id'];

        return $out;
    }

    /** @return array<string, mixed> */
    public static function rules(int|string|null $academicUnitId): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['required', 'string', self::wordLimit()],
            'year' => ['required', 'integer', 'min:1990', 'max:'.((int) date('Y') + 1)],
            'research_type' => ['required', Rule::in(array_keys(ResearchTypes::TYPES))],
            'degree_level' => ['required', Rule::in(array_keys(ResearchTypes::DEGREE_LEVELS))],
            'academic_unit_id' => ['required', 'integer', Rule::exists('academic_units', 'id')],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where('academic_unit_id', (int) $academicUnitId)],
            'adviser_name' => ['nullable', 'string', 'max:190'],
            'authors' => ['required', 'array', 'min:1', 'max:10'],
            'authors.*' => ['string', 'max:120'],
            'keywords' => ['required', 'array', 'min:'.RepositorySettings::keywordsMin(), 'max:'.RepositorySettings::keywordsMax()],
            'keywords.*' => ['string', 'max:100'],
            'sdgs' => ['array'],
            'sdgs.*' => ['integer', Rule::exists('sdgs', 'id')],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $min = RepositorySettings::keywordsMin();
        $max = RepositorySettings::keywordsMax();

        return [
            'keywords.required' => "Add {$min} to {$max} keywords.",
            'keywords.min' => "Add {$min} to {$max} keywords.",
            'keywords.max' => "Use no more than {$max} keywords.",
            'authors.required' => 'Add at least one author.',
            'authors.min' => 'Add at least one author.',
            'program_id.exists' => 'Choose a program from the selected college.',
            'sdgs.*.exists' => 'SDGs are numbers from 1 to 17.',
            'sdgs.*.integer' => 'SDGs are numbers from 1 to 17.',
        ];
    }

    private static function wordLimit(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $limit = RepositorySettings::abstractWordLimit();
            $count = RepositorySettings::wordCount((string) $value);
            if ($count > $limit) {
                $fail("The abstract has {$count} words. The limit is {$limit}.");
            }
        };
    }
}
