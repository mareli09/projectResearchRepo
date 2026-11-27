<?php

namespace App\Domain\Submissions;

use App\Domain\Repository\ResearchTypes;
use App\Models\Submission;
use App\Models\SubmissionReview;

/**
 * Turns a Submission into the array the screens use (status label, step, history, score),
 * the same shape the design screens were built with.
 */
final class SubmissionPresenter
{
    private const EVENT = [
        'submit' => 'Submitted', 'resubmit' => 'Resubmitted', 'endorse' => 'Endorsed', 'clear' => 'Reviewed',
        'approve' => 'Published', 'return' => 'Returned', 'revise' => 'Returned', 'reject' => 'Rejected',
    ];

    public const WITH = ['student', 'adviser', 'members', 'program.academicUnit', 'reviews.reviewer', 'similarityChecks.matches.record.academicUnit', 'similarityChecks.matches.record.keywords', 'record'];

    /** @return array<string, mixed> */
    public static function present(Submission $s): array
    {
        $history = $s->reviews->map(fn (SubmissionReview $r) => [
            self::EVENT[$r->decision],
            $r->reviewer->name ?? '—',
            $r->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A'),
            (string) $r->reason,
        ])->values()->all();

        return [
            'id' => $s->public_id,
            'model' => $s,
            'title' => $s->title,
            'abstract' => $s->abstract,
            'student' => $s->student->name ?? '—',
            'members' => $s->members->pluck('name')->all(),
            'program' => $s->program->name ?? '—',
            'college' => $s->program?->academicUnit->code ?? '—',
            'adviser' => $s->adviser->name ?? '—',
            'type' => ResearchTypes::TYPES[$s->research_type] ?? $s->research_type,
            'keywords' => $s->keywords,
            'sdg' => $s->sdgs,
            'status' => ucfirst($s->status),
            'step' => $s->current_step,
            'score' => $s->max_similarity !== null ? (float) $s->max_similarity : null,
            'screened' => $s->max_similarity !== null,
            'history' => $history,
            'record' => $s->record?->public_id,
        ];
    }

    /**
     * Top matches from the latest screening, in the shape of the match table.
     *
     * @return list<array{record: array<string, mixed>, score: float}>
     */
    public static function matches(Submission $s): array
    {
        $check = $s->similarityChecks->sortByDesc('id')->first();
        if ($check === null) {
            return [];
        }

        $out = [];
        foreach ($check->matches as $m) {
            if ($m->record === null) {
                continue;
            }
            $out[] = ['record' => [
                'id' => $m->record->id,
                'public_id' => $m->record->public_id,
                'title' => $m->record->title,
                'year' => $m->record->year,
                'college' => $m->record->academicUnit->code,
                'type_label' => ResearchTypes::TYPES[$m->record->research_type],
                'keywords' => $m->record->keywords->pluck('term')->all(),
            ], 'score' => (float) $m->score];
        }

        return $out;
    }
}
