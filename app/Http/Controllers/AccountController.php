<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Mask;
use App\Domain\Accounts\PhMobile;
use App\Domain\Accounts\RecoveryCodeService;
use App\Domain\Audit\Activity;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Notifications\RecoveryContactChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Account and recovery" page for every role (CLAUDE.md §7 M1).
 * [Not in manuscript] recovery email and mobile, each verified with a code before use.
 */
class AccountController extends Controller
{
    private const KINDS = ['email', 'mobile'];

    public function __construct(private readonly RecoveryCodeService $codes) {}

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('academicUnit', 'program');

        return view('account.show', [
            'user' => $user,
            'mobileDisplay' => $user->recovery_mobile ? PhMobile::format($user->recovery_mobile) : null,
            'events' => Activity::query()
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->id)
                ->whereIn('event', AuditEvent::SECURITY)
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /** Add or change a recovery contact. It stays unverified until the code is entered. */
    public function storeContact(Request $request, string $kind): RedirectResponse
    {
        $this->ensureEnabled($kind);
        /** @var User $user */
        $user = $request->user();

        if ($kind === 'email') {
            $request->merge(['recovery_email' => Str::lower(trim((string) $request->input('recovery_email')))]);
            $request->validate(
                ['recovery_email' => ['required', 'string', 'email:rfc', 'max:190', RegisterController::notTua()]],
                [],
                ['recovery_email' => 'recovery email'],
            );
            $old = $user->recovery_email_verified_at ? $user->recovery_email : null;
            $value = (string) $request->input('recovery_email');
            $user->recovery_email = $value;
            $user->recovery_email_verified_at = null;
            $channel = 'recovery_email';
        } else {
            $request->validate(['recovery_mobile' => ['required', 'string', RegisterController::phMobile()]], [], ['recovery_mobile' => 'mobile number']);
            $old = null;
            $value = (string) PhMobile::normalize((string) $request->input('recovery_mobile'));
            $user->recovery_mobile = $value;
            $user->recovery_mobile_verified_at = null;
            $channel = 'sms';
        }
        $user->save();

        $this->codes->issue($user, $channel, RecoveryCodeService::PURPOSE_VERIFY, $value);
        $this->notifyChange($user, 'recovery '.$kind.' set to '.($kind === 'email' ? Mask::email($value) : Mask::mobile($value)), $old);
        Audit::record(AuditEvent::RECOVERY, 'Recovery '.$kind.' added or changed (not verified yet)', $user, ['kind' => $kind]);

        return back()->with('status', 'We sent a 6-digit code to the new '.($kind === 'email' ? 'email address' : 'mobile number').'. Enter it below to verify.');
    }

    public function resendCode(Request $request, string $kind): RedirectResponse
    {
        $this->ensureEnabled($kind);
        /** @var User $user */
        $user = $request->user();
        $value = $kind === 'email' ? $user->recovery_email : $user->recovery_mobile;

        if ($value === null) {
            return back();
        }

        $this->codes->issue($user, $kind === 'email' ? 'recovery_email' : 'sms', RecoveryCodeService::PURPOSE_VERIFY, $value);

        return back()->with('status', 'A new code is on its way. Older codes no longer work.');
    }

    public function verifyContact(Request $request, string $kind): RedirectResponse
    {
        $this->ensureEnabled($kind);
        /** @var User $user */
        $user = $request->user();
        $request->validate(['code' => ['required', 'digits:6']]);

        $channel = $kind === 'email' ? 'recovery_email' : 'sms';
        if (! $this->codes->verify($user, RecoveryCodeService::PURPOSE_VERIFY, (string) $request->input('code'), $channel)) {
            return back()->withErrors(['code_'.$kind => 'This code is wrong or has expired. Send a new code.']);
        }

        if ($kind === 'email') {
            $user->recovery_email_verified_at = now();
        } else {
            $user->recovery_mobile_verified_at = now();
        }
        $user->save();
        Audit::record(AuditEvent::RECOVERY, 'Recovery '.$kind.' verified', $user, ['kind' => $kind]);

        return back()->with('status', 'Your recovery '.$kind.' is verified.');
    }

    public function destroyContact(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        /** @var User $user */
        $user = $request->user();

        $old = $kind === 'email' && $user->recovery_email_verified_at ? $user->recovery_email : null;
        if ($kind === 'email') {
            $user->recovery_email = null;
            $user->recovery_email_verified_at = null;
        } else {
            $user->recovery_mobile = null;
            $user->recovery_mobile_verified_at = null;
        }
        $user->save();

        $this->notifyChange($user, 'recovery '.$kind.' removed', $old);
        Audit::record(AuditEvent::RECOVERY, 'Recovery '.$kind.' removed', $user, ['kind' => $kind]);

        return back()->with('status', 'Recovery '.$kind.' removed.');
    }

    private function ensureEnabled(string $kind): void
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        abort_unless($kind === 'email' ? config('tua.features.recovery_email') : config('tua.features.recovery_sms'), 404);
    }

    /** The TUA email is always told; the old verified recovery email is told too (§7 M1, §12). */
    private function notifyChange(User $user, string $what, ?string $oldRecoveryEmail): void
    {
        $user->notify(new RecoveryContactChanged($what));
        if ($oldRecoveryEmail !== null) {
            Notification::route('mail', $oldRecoveryEmail)->notify(new RecoveryContactChanged($what));
        }
    }

    /** Route constraint for {kind}. */
    public static function kindPattern(): string
    {
        return implode('|', self::KINDS);
    }
}
