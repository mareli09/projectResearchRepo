<?php

namespace App\Policies;

use App\Models\ResearchCluster;
use App\Models\Submission;
use App\Models\User;

/**
 * Who may do what with a submission (CLAUDE.md §4, §7 M3): the right role and the right owner,
 * at the right step.
 */
class SubmissionPolicy
{
    public function view(User $user, Submission $s): bool
    {
        return $s->student_user_id === $user->id
            || $s->adviser_user_id === $user->id
            || $this->coordinates($user, $s)
            || $user->hasRole(User::ROLE_URDC);
    }

    /** Adviser: endorse or return an advisee's submission (step 1 → 2). */
    public function endorse(User $user, Submission $s): bool
    {
        return $s->status === 'submitted' && $s->adviser_user_id === $user->id && $user->hasRole(User::ROLE_ADVISER);
    }

    /** Coordinator of the submission's cluster: clear, return or reject (step 2 → 3). */
    public function review(User $user, Submission $s): bool
    {
        return $s->status === 'endorsed' && $this->coordinates($user, $s);
    }

    /** URDC: approve and publish, return or reject (step 3 → 4). */
    public function decide(User $user, Submission $s): bool
    {
        return $s->status === 'reviewed' && $user->hasRole(User::ROLE_URDC);
    }

    /** Student: revise and resubmit a returned study. */
    public function resubmit(User $user, Submission $s): bool
    {
        return $s->status === 'returned' && $s->student_user_id === $user->id;
    }

    private function coordinates(User $user, Submission $s): bool
    {
        return $user->hasRole(User::ROLE_COORDINATOR)
            && $s->research_cluster_id !== null
            && ResearchCluster::whereKey($s->research_cluster_id)->where('coordinator_user_id', $user->id)->exists();
    }
}
