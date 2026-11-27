<?php

namespace App\Policies;

use App\Domain\Repository\AccessRequests;
use App\Models\RecordFile;
use App\Models\User;

/**
 * Who may download a stored file (CLAUDE.md §1 rule 9, §4 "Full paper").
 * - URDC administrator: every slot, any status.
 * - Faculty adviser, research coordinator: paper files of published records.
 * - Student: the full paper and IMRAD version, with an approved, unexpired full-paper request.
 * - System administrator, guests: no.
 * The signed Transfer of Ownership Agreement is an office record: URDC only.
 */
class RecordFilePolicy
{
    private const READER_SLOTS = ['abstract', 'full_paper', 'imrad', 'references'];

    public function download(User $user, RecordFile $file): bool
    {
        if ($user->can('records.manage')) {
            return true;
        }

        // Files of a submission that is not published yet: the people in its workflow (CLAUDE.md §7 M3).
        if ($file->record_id === null && $file->submission_id !== null) {
            $submission = $file->submission;

            return $submission !== null && $user->can('view', $submission);
        }

        $record = $file->record;
        if ($record === null || $record->status !== 'published' || ! in_array($file->slot, self::READER_SLOTS, true)) {
            return false;
        }

        if ($user->hasAnyRole([User::ROLE_ADVISER, User::ROLE_COORDINATOR])) {
            return true;
        }

        // Students: the paper files of a record they have an approved, unexpired request for.
        return $user->hasRole(User::ROLE_STUDENT) && in_array($file->slot, AccessRequests::SLOTS, true)
            && AccessRequests::hasAccess($user, $record->id);
    }
}
