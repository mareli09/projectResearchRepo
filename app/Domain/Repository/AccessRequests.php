<?php

namespace App\Domain\Repository;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\AccessRequest;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Full-paper requests (CLAUDE.md §4, §7 M4): a student asks with a stated purpose, the URDC
 * approves with an expiry date or declines with a reason, and the student is emailed either way.
 * An approved, unexpired request lets the student download the paper files of that record.
 */
final class AccessRequests
{
    /** Paper files a student may open once a request is approved (never the Transfer of Ownership). */
    public const SLOTS = ['full_paper', 'imrad'];

    public const DEFAULT_DAYS = 14;

    public static function nextPublicId(): string
    {
        $max = AccessRequest::query()->selectRaw("MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n")->value('n');

        return sprintf('AR-%03d', ((int) $max) + 1);
    }

    /** The student's latest request for a record, if any. */
    public static function latestFor(User $user, ResearchRecord $record): ?AccessRequest
    {
        return AccessRequest::where('user_id', $user->id)->where('record_id', $record->id)->latest('id')->first();
    }

    public static function hasAccess(User $user, int $recordId): bool
    {
        return AccessRequest::where('user_id', $user->id)->where('record_id', $recordId)->where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    public static function request(User $student, ResearchRecord $record, string $purpose): AccessRequest
    {
        $latest = self::latestFor($student, $record);
        if ($latest !== null && ($latest->status === 'pending' || ($latest->status === 'approved' && ($latest->expires_at === null || $latest->expires_at->isFuture())))) {
            throw new RuntimeException($latest->status === 'pending'
                ? "You already asked for this paper ({$latest->public_id}). The URDC will email you."
                : 'You already have access to this paper.');
        }

        $req = DB::transaction(fn () => AccessRequest::create([
            'public_id' => self::nextPublicId(),
            'user_id' => $student->id,
            'record_id' => $record->id,
            'purpose' => trim($purpose),
            'status' => 'pending',
        ]));

        Audit::record(AuditEvent::REQUEST, "Full-paper request {$req->public_id} for {$record->public_id}", $record, ['request' => $req->public_id], $student);
        foreach (User::role(User::ROLE_URDC)->where('is_active', true)->get() as $urdc) {
            $urdc->notify(new WorkflowNotice(
                "Full-paper request {$req->public_id}",
                "{$student->name} asked for the full paper of {$record->public_id}, \"{$record->title}\". Purpose: {$req->purpose}",
                route('urdc.requests'),
            ));
        }

        return $req;
    }

    public static function approve(AccessRequest $req, User $by, Carbon $expiresAt): void
    {
        self::assertPending($req);
        $req->update(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now(), 'expires_at' => $expiresAt->endOfDay(), 'reason' => null]);
        self::notify($req, $by, 'approved', "You can download the full paper until {$expiresAt->format('F j, Y')}. Downloads are logged.");
    }

    public static function decline(AccessRequest $req, User $by, string $reason): void
    {
        self::assertPending($req);
        $req->update(['status' => 'declined', 'decided_by' => $by->id, 'decided_at' => now(), 'reason' => trim($reason)]);
        self::notify($req, $by, 'declined', 'Reason: '.trim($reason));
    }

    private static function assertPending(AccessRequest $req): void
    {
        if ($req->status !== 'pending') {
            throw new RuntimeException("{$req->public_id} was already decided.");
        }
    }

    private static function notify(AccessRequest $req, User $by, string $decision, string $detail): void
    {
        $req->loadMissing('record', 'user');
        Audit::record(AuditEvent::REQUEST, "Full-paper request {$req->public_id} {$decision}", $req->record, [
            'request' => $req->public_id, 'decision' => $decision, 'reason' => $req->reason, 'expires_at' => $req->expires_at?->toDateString(),
        ], $by);
        $req->user->notify(new WorkflowNotice(
            "Your full-paper request was {$decision}",
            "{$req->public_id} for {$req->record->public_id}, \"{$req->record->title}\". {$detail}",
            route('studies.show', $req->record->public_id),
        ));
    }
}
