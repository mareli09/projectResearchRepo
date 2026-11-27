<?php

namespace App\Domain\Submissions;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Repository\DuplicateFileException;
use App\Domain\Repository\FileSlots;
use App\Domain\Repository\RecordRules;
use App\Domain\Repository\RecordWriter;
use App\Domain\Repository\ResearchTypes;
use App\Jobs\ScreenSubmission;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\RecordFile;
use App\Models\ScreeningDecision;
use App\Models\Submission;
use App\Models\SubmissionReview;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The four-step submission workflow (CLAUDE.md §7 M3):
 *   draft → submitted (1) → endorsed (2) → reviewed (3) → published (4)
 *   returned (by adviser, coordinator or URDC, reason required) → resubmitted → submitted (back to adviser)
 *   rejected (coordinator or URDC, reason required; final)
 * Every transition writes a submission_reviews row and an activity-log entry, and sends email + in-app notices.
 * Who may call each transition is checked by SubmissionPolicy before this class is used.
 */
final class Workflow
{
    public function __construct(private readonly SubmissionFiles $files, private readonly RecordWriter $writer) {}

    /**
     * Student submits (CLAUDE.md §7 M3 "On submit, in order"): metadata and word-limit checks,
     * all required files, exact-file check, then status submitted and the adviser is emailed.
     * Similarity screening (step 4 of the list) is queued from Sprint 4.
     *
     * @param  array<string, mixed>  $data  validated by SubmissionRules
     * @param  array<string, UploadedFile>  $uploads  slot => file
     */
    public function submit(User $student, array $data, array $uploads): Submission
    {
        $imradNa = (bool) ($data['imrad_not_applicable'] ?? false);
        $this->checkFiles($uploads, $imradNa, null);

        $program = Program::with('academicUnit')->find($data['program_id']);
        $unit = $program !== null ? $program->academicUnit : ($student->academic_unit_id ? AcademicUnit::find($student->academic_unit_id) : null);

        $sub = DB::transaction(function () use ($student, $data, $uploads, $imradNa, $program, $unit): Submission {
            $sub = Submission::create([
                'public_id' => self::nextPublicId(),
                'student_user_id' => $student->id,
                'adviser_user_id' => (int) $data['adviser_user_id'],
                'research_cluster_id' => $unit?->research_cluster_id,
                'academic_unit_id' => $unit?->id,
                'program_id' => $program?->id,
                'title' => $data['title'],
                'abstract' => $data['abstract'],
                'research_type' => $data['research_type'],
                'year' => (int) $data['year'],
                'keywords' => $data['keywords'],
                'sdgs' => array_map('intval', $data['sdgs'] ?? []),
                'imrad_not_applicable' => $imradNa,
                'status' => 'submitted',
                'current_step' => 1,
                'submitted_at' => now(),
            ]);
            foreach (array_values($data['authors']) as $i => $name) {
                $sub->members()->create(['name' => $name, 'user_id' => $name === $student->name ? $student->id : null, 'position' => $i + 1]);
            }
            foreach ($uploads as $slot => $file) {
                $this->files->store($sub, $slot, (string) $file->getRealPath(), $file->getClientOriginalName(), $student);
            }
            $this->review($sub, 1, $student, 'submit', null);

            return $sub;
        });

        Audit::record(AuditEvent::SUBMIT, "Submitted {$sub->public_id} with ".count($uploads).' files', $sub, ['public_id' => $sub->public_id], $student);
        self::queue(new ScreenSubmission($sub));
        $this->tell($student, "{$sub->public_id} received",
            'All required files were received. Your adviser was asked to endorse it. The similarity screening result appears on your status page.', $sub, 'student.status');
        $this->tell($sub->adviser, "New submission to endorse: {$sub->public_id}", "{$student->name} submitted \"{$sub->title}\".", $sub, 'adviser.review');

        return $sub;
    }

    public function endorse(User $adviser, Submission $sub, ?string $comment): void
    {
        $this->move($sub, 'endorsed', 2, $adviser, 'endorse', $comment, AuditEvent::ENDORSED, 'Endorsed by adviser');
        $this->tell($sub->student, "{$sub->public_id} endorsed by your adviser",
            "{$adviser->name} endorsed your submission. It now goes to the research coordinator of your cluster.".($comment ? " Comment: {$comment}" : ''), $sub, 'student.status');
        $this->tell($sub->cluster?->coordinator, ($sub->flag && $sub->flag !== 'original' ? 'Endorsed submission flagged: ' : 'Endorsed submission to review: ').$sub->public_id,
            "\"{$sub->title}\" was endorsed by {$adviser->name}.", $sub, 'coordinator.screening');
    }

    /** Return for revision, by the adviser, the coordinator or the URDC. A reason is always required. */
    public function returnToStudent(User $by, Submission $sub, string $reason, string $decision = 'return'): void
    {
        $this->requireReason($reason);
        $this->move($sub, 'returned', $sub->current_step, $by, $decision, $reason, AuditEvent::RETURNED, 'Returned for revision');
        $this->tell($sub->student, "{$sub->public_id} returned for revision", "Reason: {$reason}", $sub, 'student.status');
        if ($sub->adviser && ! $sub->adviser->is($by)) {
            $this->tell($sub->adviser, "{$sub->public_id} returned to the student", "Reason: {$reason}", $sub, 'adviser.review');
        }
    }

    /**
     * Coordinator decision after the similarity review. The decision also records a calibration label (§7 M5).
     * A reason is required except for a clear on an original.
     */
    public function coordinatorDecide(User $coordinator, Submission $sub, string $decision, ?string $reason, ?int $matchedRecordId): void
    {
        if (! ($decision === 'clear' && $sub->flag === 'original')) {
            $this->requireReason($reason);
        }

        ScreeningDecision::create([
            'submission_id' => $sub->id,
            'coordinator_user_id' => $coordinator->id,
            'matched_record_id' => $matchedRecordId,
            'decision' => $decision,
            'reason' => $reason,
            'label_for_calibration' => match ($decision) {
                'clear' => 'distinct', 'reject' => 'duplicate', default => null,
            },
        ]);

        match ($decision) {
            'clear' => $this->clear($coordinator, $sub, $reason),
            'revise' => $this->returnToStudent($coordinator, $sub, (string) $reason, 'revise'),
            'reject' => $this->reject($coordinator, $sub, (string) $reason),
            default => throw new RuntimeException('Unknown decision.'),
        };
    }

    /** URDC final step: approve and publish, return, or reject. */
    public function urdcDecide(User $urdc, Submission $sub, string $decision, ?string $reason, ?bool $imradNotApplicable = null): void
    {
        match ($decision) {
            'approve' => $this->publish($urdc, $sub, $reason, $imradNotApplicable),
            'return' => $this->returnToStudent($urdc, $sub, (string) $reason),
            'reject' => $this->reject($urdc, $sub, (string) $reason),
            default => throw new RuntimeException('Unknown decision.'),
        };
    }

    /**
     * Revise and resubmit a returned study. Corrected files become new versions (new SHA-256);
     * older versions are kept. It goes back to the adviser (assumption in §7 M3, to confirm with the URDC).
     *
     * @param  array<string, UploadedFile>  $uploads
     */
    public function resubmit(User $student, Submission $sub, array $uploads, ?string $note): void
    {
        $this->checkFiles($uploads, $sub->imrad_not_applicable, $sub);

        DB::transaction(function () use ($student, $sub, $uploads): void {
            foreach ($uploads as $slot => $file) {
                $this->files->store($sub, $slot, (string) $file->getRealPath(), $file->getClientOriginalName(), $student);
            }
        });
        $missing = SubmissionFiles::missingSlots($sub);
        if ($missing !== []) {
            throw ValidationException::withMessages(['files' => 'Still missing: '.implode(', ', array_map(fn ($s) => FileSlots::slots()[$s]['label'], $missing)).'.']);
        }

        $this->move($sub, 'submitted', 1, $student, 'resubmit', $note, AuditEvent::SUBMIT, 'Resubmitted after revision');
        self::queue(new ScreenSubmission($sub));
        $this->tell($sub->adviser, "Resubmitted for endorsement: {$sub->public_id}", "{$student->name} revised and resubmitted \"{$sub->title}\".", $sub, 'adviser.review');
    }

    /**
     * Queue background work (screening, refits). A failure of the analytics service must never
     * block the workflow: the job retries, and the screens say "Screening is queued" meanwhile.
     */
    public static function queue(object $job): void
    {
        try {
            dispatch($job);
        } catch (Throwable $e) {
            Log::warning('background_job_failed', ['job' => $job::class, 'error' => $e->getMessage()]);
        }
    }

    /** SUB-0143 and up, numbered across all submissions. */
    public static function nextPublicId(): string
    {
        $max = Submission::query()->selectRaw("MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n")->value('n');

        return sprintf('SUB-%04d', ((int) $max) + 1);
    }

    private function clear(User $coordinator, Submission $sub, ?string $reason): void
    {
        $this->move($sub, 'reviewed', 3, $coordinator, 'clear', $reason, AuditEvent::REVIEWED, 'Reviewed by coordinator');
        $this->tell($sub->student, "{$sub->public_id} reviewed by the research coordinator", 'Your study goes to the URDC for final approval.', $sub, 'student.status');
        foreach (User::role(User::ROLE_URDC)->get() as $urdc) {
            $this->tell($urdc, "Ready for approval: {$sub->public_id}", "{$coordinator->name} reviewed the submission and sent it for final approval.", $sub, 'urdc.approvals');
        }
    }

    private function reject(User $by, Submission $sub, string $reason): void
    {
        $this->requireReason($reason);
        $this->move($sub, 'rejected', $sub->current_step, $by, 'reject', $reason, AuditEvent::REJECTED, 'Rejected');
        $this->tell($sub->student, "{$sub->public_id} rejected", "Reason: {$reason}", $sub, 'student.status');
        if ($sub->adviser && ! $sub->adviser->is($by)) {
            $this->tell($sub->adviser, "{$sub->public_id} rejected", "Reason: {$reason}", $sub, 'adviser.review');
        }
    }

    /** Publishing creates the research record, moves the files to it, and publishes it (§7 M3). */
    private function publish(User $urdc, Submission $sub, ?string $comment, ?bool $imradNotApplicable): void
    {
        $sub->loadMissing('members', 'adviser', 'program');

        DB::transaction(function () use ($urdc, $sub, $comment, $imradNotApplicable): void {
            $data = RecordRules::normalize([
                'title' => $sub->title,
                'abstract' => $sub->abstract,
                'authors' => $sub->members->pluck('name')->all(),
                'adviser_name' => $sub->adviser?->name,
                'academic_unit_id' => $sub->academic_unit_id,
                'program_id' => $sub->program_id,
                'research_type' => $sub->research_type,
                'degree_level' => $sub->program?->degree_level === 'graduate' ? 'graduate' : ResearchTypes::degreeFor($sub->research_type),
                'year' => $sub->year,
                'keywords' => $sub->keywords,
                'sdgs' => $sub->sdgs,
            ]);
            $record = $this->writer->create($data, 'submission');
            $record->imrad_not_applicable = $imradNotApplicable ?? $sub->imrad_not_applicable;
            $record->save();

            RecordFile::where('submission_id', $sub->id)->update(['record_id' => $record->id]);
            FileSlots::refreshCompleteness($record);
            $this->writer->publish($record->refresh(), $urdc); // throws when files or keywords are missing

            $sub->record_id = $record->id;
            $this->move($sub, 'published', 4, $urdc, 'approve', $comment, AuditEvent::PUBLISHED, "Approved and published as {$record->public_id}");
        });

        $publicId = $sub->record()->value('public_id');
        $body = "Your study is now in the repository as {$publicId} and can be found in search.";
        $this->tell($sub->student, "{$sub->public_id} approved and published", $body, $sub, 'student.status');
        $this->tell($sub->adviser, "{$sub->public_id} approved and published", "\"{$sub->title}\" is now in the repository as {$publicId}.", $sub, 'adviser.review');
    }

    /**
     * @param  array<string, UploadedFile>  $uploads
     *
     * @throws ValidationException
     */
    private function checkFiles(array $uploads, bool $imradNa, ?Submission $own): void
    {
        $errors = [];
        foreach ($uploads as $slot => $file) {
            $error = SubmissionFiles::check($slot, (string) $file->getRealPath(), $file->getClientOriginalName());
            if ($error === null) {
                try {
                    SubmissionFiles::ensureNotDuplicate((string) $file->getRealPath(), $slot, $own);
                } catch (DuplicateFileException $e) {
                    $error = "This exact file is already stored for {$e->matchingPublicId}. Upload your own file.";
                }
            }
            if ($error !== null) {
                $errors['files.'.$slot] = $error;
            }
        }

        if ($own === null) {
            foreach (FileSlots::slots() as $slot => $def) {
                if ($def['required'] && ! isset($uploads[$slot]) && ! ($slot === 'imrad' && $imradNa) && ! isset($errors['files.'.$slot])) {
                    $errors['files.'.$slot] = "Attach the {$def['label']}.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function requireReason(?string $reason): void
    {
        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required. The student will see it in the email.']);
        }
    }

    private function move(Submission $sub, string $status, int $step, User $by, string $decision, ?string $reason, string $event, string $description): void
    {
        $old = $sub->status;
        $sub->status = $status;
        $sub->current_step = $step;
        $sub->save();
        $this->review($sub, $step, $by, $decision, $reason);

        Audit::record($event, "{$description}: {$sub->public_id}", $sub, array_filter(['public_id' => $sub->public_id, 'reason' => $reason]), $by,
            old: ['status' => $old], new: ['status' => $status]);
    }

    private function review(Submission $sub, int $step, User $by, string $decision, ?string $reason): void
    {
        SubmissionReview::create([
            'submission_id' => $sub->id,
            'step' => $step,
            'reviewer_user_id' => $by->id,
            'decision' => $decision,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_at' => now(),
        ]);
    }

    private function tell(?User $to, string $subject, string $body, Submission $sub, string $route): void
    {
        if ($to === null) {
            return;
        }
        $to->notify(new WorkflowNotice($subject, $body, route($route, ['id' => $sub->public_id]), $sub->public_id));
    }
}
