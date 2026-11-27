<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\ActivationService;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AssistedResetLink;
use App\Notifications\RecoveryContactChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Users and roles" (CLAUDE.md §7 M1, M9).
 * Role changes, deactivation and reactivation: System administrator only (users.manage).
 * Resend activation and assisted recovery: System administrator or URDC (users.assist).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $users = User::query()
            ->with('roles', 'academicUnit')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', ['users' => $users, 'q' => $q, 'roles' => User::ROLES]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('assign-role', $user);
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);

        $old = $user->getRoleNames()->first();
        if ($old === $data['role']) {
            return back();
        }

        $user->syncRoles([$data['role']]);
        Audit::record(AuditEvent::ROLE, 'Role changed', $user, old: ['role' => $old], new: ['role' => $data['role']]);

        return back()->with('status', $user->name.' is now '.self::roleLabel($data['role']).'.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()?->is($user), 403, 'You cannot deactivate your own account.');

        $user->deactivated_at = now();
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::record(AuditEvent::ROLE, 'Account deactivated', $user, old: ['status' => 'active'], new: ['status' => 'deactivated']);

        return back()->with('status', $user->name.' is deactivated and was signed out.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        $user->deactivated_at = null;
        $user->save();
        Audit::record(AuditEvent::ROLE, 'Account reactivated', $user, old: ['status' => 'deactivated'], new: ['status' => 'active']);

        return back()->with('status', $user->name.' is reactivated.');
    }

    public function resendActivation(User $user, ActivationService $activation): RedirectResponse
    {
        if ($user->is_active) {
            return back()->with('status', $user->name.' is already active.');
        }

        $activation->issue($user);
        Audit::record(AuditEvent::NOTIFY, 'Activation link resent by staff', $user);

        return back()->with('status', 'A new activation link was sent to '.$user->email.'.');
    }

    /**
     * Assisted recovery: staff check a school or government ID, set a new recovery email,
     * and send a one-time reset link to it. Staff never see or set the password.
     */
    public function assistedRecovery(Request $request, User $user): RedirectResponse
    {
        $request->merge(['recovery_email' => Str::lower(trim((string) $request->input('recovery_email')))]);
        $data = $request->validate([
            'id_checked' => ['accepted'],
            'id_type' => ['required', 'string', 'max:80'],
            'recovery_email' => ['required', 'string', 'email:rfc', 'max:190', RegisterController::notTua()],
        ], ['id_checked.accepted' => 'Confirm that you checked the ID.']);

        $oldVerified = $user->recovery_email_verified_at ? $user->recovery_email : null;
        $user->recovery_email = $data['recovery_email'];
        // Verified by staff in person, so the link can go to it.
        $user->recovery_email_verified_at = now();
        $user->save();

        $token = Password::broker()->createToken($user);
        Notification::route('mail', $data['recovery_email'])->notify(new AssistedResetLink($token, $user->email));

        $user->notify(new RecoveryContactChanged('recovery email set by staff after an ID check'));
        if ($oldVerified !== null && $oldVerified !== $data['recovery_email']) {
            Notification::route('mail', $oldVerified)->notify(new RecoveryContactChanged('recovery email set by staff after an ID check'));
        }

        Audit::record(AuditEvent::RECOVERY, 'Assisted recovery: ID checked, reset link sent', $user, [
            'id_type' => $data['id_type'],
        ]);

        return back()->with('status', 'A one-time reset link was sent to the new recovery email of '.$user->name.'.');
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            User::ROLE_STUDENT => 'Student',
            User::ROLE_ADVISER => 'Faculty adviser',
            User::ROLE_COORDINATOR => 'Research coordinator',
            User::ROLE_URDC => 'URDC administrator',
            User::ROLE_SYSADMIN => 'System administrator',
            default => '—',
        };
    }
}
