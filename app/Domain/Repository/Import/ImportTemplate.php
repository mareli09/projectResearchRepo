<?php

namespace App\Domain\Repository\Import;

/**
 * Columns of the bulk import template (CLAUDE.md §7 M2): migration of the Google Form
 * submissions and older outputs. File columns name files inside the optional ZIP.
 */
final class ImportTemplate
{
    /** column => help text shown on the import page */
    public const COLUMNS = [
        'title' => 'Required.',
        'abstract' => 'Required. Word limit applies.',
        'authors' => 'Required. Separate names with ";".',
        'adviser' => 'Adviser name as it should appear.',
        'college' => 'Required. CAHS, CASE, IBAM, CEIS, CMT or SLCN.',
        'program' => 'Program name from the college list.',
        'research_type' => 'Required. Undergraduate thesis, Capstone project, Graduate thesis or Faculty research.',
        'degree_level' => 'Undergraduate, Graduate or Faculty. Left blank: taken from the research type.',
        'year' => 'Required. For example 2023.',
        'keywords' => 'Required. 3 to 5, separated by ";".',
        'sdgs' => 'SDG numbers 1 to 17, separated by ",".',
        'source' => 'Google Form migration (default) or Legacy import.',
        'abstract_file' => 'File name in the ZIP (.doc or .docx).',
        'full_paper_file' => 'File name in the ZIP (.pdf).',
        'imrad_file' => 'File name in the ZIP (.pdf), or N/A.',
        'toa_file' => 'File name in the ZIP (.pdf): signed Transfer of Ownership Agreement.',
        'references_file' => 'File name in the ZIP (.doc or .docx).',
    ];

    public const FILE_COLUMNS = [
        'abstract' => 'abstract_file',
        'full_paper' => 'full_paper_file',
        'imrad' => 'imrad_file',
        'toa' => 'toa_file',
        'references' => 'references_file',
    ];

    /** @return list<list<string>> heading row plus one example row */
    public static function rows(): array
    {
        return [
            array_keys(self::COLUMNS),
            [
                'Flood Risk Prediction for Barangays in Quezon City Using Random Forest',
                'This capstone project built a model that predicts flood risk for each barangay...',
                'Francine Garcia; Enzo Navarro',
                'Prof. Ramon Castillo',
                'CEIS',
                'BS Information Technology',
                'Capstone project',
                '',
                '2025',
                'flood risk; random forest; prediction; barangay',
                '11, 13',
                'Google Form migration',
                'flood-abstract.docx',
                'flood-full-paper.pdf',
                'N/A',
                'flood-toa.pdf',
                'flood-references.docx',
            ],
        ];
    }
}
