<?php

namespace App\Domain\Submissions;

use App\Domain\Repository\RecordRules;
use App\Domain\Repository\RepositorySettings;
use App\Models\User;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Validation for "Submit a study" (CLAUDE.md §7 M2, M3). Shares the metadata rules of records:
 * abstract word limit, 3–5 keywords, SDGs 1–17, authors.
 */
final class SubmissionRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $n = RecordRules::normalize($input);
        $n['imrad_not_applicable'] = filter_var($input['imrad_not_applicable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $n;
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $base = RecordRules::rules(null);

        return [
            'title' => $base['title'],
            'abstract' => $base['abstract'],
            'year' => $base['year'],
            // Students submit theses and capstone projects; faculty research comes through the URDC.
            'research_type' => ['required', Rule::in(['undergraduate_thesis', 'capstone_project', 'graduate_thesis'])],
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')],
            'adviser_user_id' => ['required', 'integer', Rule::exists('model_has_roles', 'model_id')->where('role_id', self::adviserRoleId())],
            'authors' => $base['authors'],
            'authors.*' => $base['authors.*'],
            'keywords' => $base['keywords'],
            'keywords.*' => $base['keywords.*'],
            'sdgs' => $base['sdgs'],
            'sdgs.*' => $base['sdgs.*'],
            'imrad_not_applicable' => ['boolean'],
            'files' => ['array'],
            'files.*' => ['file', 'max:'.(RepositorySettings::maxFileMb() * 1024)],
        ];
    }

    /**
     * "Save draft": only the title is required. Anything filled in must still be valid
     * (word limit, at most 5 keywords, real program and adviser), so a draft never holds bad data.
     *
     * @return array<string, mixed>
     */
    public static function draftRules(): array
    {
        $rules = self::rules();
        foreach ($rules as $field => $list) {
            if ($field === 'title' || ! is_array($list)) {
                continue;
            }
            // Lists (authors, keywords) may be shorter than the minimum while drafting.
            $isList = in_array('array', $list, true);
            $list = array_values(array_filter($list, fn ($r) => $r !== 'required' && ! ($isList && is_string($r) && str_starts_with($r, 'min:'))));
            $rules[$field] = ['nullable', ...$list];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return RecordRules::messages() + [
            'adviser_user_id.required' => 'Choose your adviser.',
            'adviser_user_id.exists' => 'Choose an adviser from the list.',
            'program_id.required' => 'Choose your program.',
            'research_type.in' => 'Choose a research type.',
            'research_type.required' => 'Choose a research type.',
            'files.*.max' => 'The file is larger than '.RepositorySettings::maxFileMb().' MB.',
        ];
    }

    private static function adviserRoleId(): int
    {
        return (int) Role::where('name', User::ROLE_ADVISER)->value('id');
    }
}
