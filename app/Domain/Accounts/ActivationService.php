<?php

namespace App\Domain\Accounts;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\AccountActivation;
use App\Models\User;
use App\Notifications\ActivationLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time activation links (CLAUDE.md §1 rule 2, §7 M1).
 * The raw token goes only into the email. The database keeps its SHA-256 hash.
 */
final class ActivationService
{
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Sends a new link. Any older unused link stops working. */
    public function issue(User $user): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $token): void {
            AccountActivation::where('user_id', $user->id)->whereNull('used_at')->delete();
            AccountActivation::create([
                'user_id' => $user->id,
                'token_hash' => self::hash($token),
                'expires_at' => now()->addHours((int) config('tua.auth.activation_link_hours')),
            ]);
        });

        $user->notify(new ActivationLink($token));
    }

    public function find(string $token): ?AccountActivation
    {
        return AccountActivation::with('user')->where('token_hash', self::hash($token))->first();
    }

    /** @return bool false when the link is used, expired, or the account is deactivated */
    public function activate(AccountActivation $activation): bool
    {
        if ($activation->isUsed() || $activation->isExpired() || $activation->user->deactivated_at !== null) {
            return false;
        }

        DB::transaction(function () use ($activation): void {
            $activation->used_at = now();
            $activation->save();

            $user = $activation->user;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
        });

        Audit::record(AuditEvent::ACTIVATE, 'Account activated', $activation->user, causer: $activation->user);

        return true;
    }
}
