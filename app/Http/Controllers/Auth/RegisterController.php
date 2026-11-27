<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\ActivationService;
use App\Domain\Accounts\PasswordRules;
use App\Domain\Accounts\PhMobile;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Create account" (CLAUDE.md §7 M1). Self-registration gives only Student or Faculty adviser. */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('guest.register', [
            'units' => AcademicUnit::with('programs')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, ActivationService $activation): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'recovery_email' => $request->filled('recovery_email') ? Str::lower(trim((string) $request->input('recovery_email'))) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', self::tuaOnly(), Rule::unique('users', 'email')],
            // "I am a": only these two can be self-assigned (§1 rule 3).
            'account_type' => ['required', Rule::in(['student', 'faculty'])],
            'academic_unit_id' => ['required', 'integer', Rule::exists('academic_units', 'id')],
            'program_id' => ['nullable', 'required_if:account_type,student', 'integer',
                Rule::exists('programs', 'id')->where('academic_unit_id', (int) $request->input('academic_unit_id'))],
            'password' => PasswordRules::rules(),
            'recovery_email' => config('tua.features.recovery_email')
                ? ['nullable', 'string', 'email:rfc', 'max:190', self::notTua()]
                : ['prohibited'],
            'recovery_mobile' => config('tua.features.recovery_sms')
                ? ['nullable', 'string', self::phMobile()]
                : ['prohibited'],
            'privacy' => ['accepted'],
        ], [
            'email.unique' => 'An account with this email already exists. Sign in, or use "Forgot password".',
            'program_id.required_if' => 'Choose your program.',
            'program_id.exists' => 'Choose a program from your college.',
            'privacy.accepted' => 'Please read and accept the Privacy Notice.',
            'recovery_email.prohibited' => 'Recovery email is turned off.',
            'recovery_mobile.prohibited' => 'Recovery by SMS is turned off.',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'user_type' => $data['account_type'],
                'academic_unit_id' => $data['academic_unit_id'],
                'program_id' => $data['program_id'] ?? null,
            ]);
            // Recovery contacts stay unverified until confirmed with a code on the account page.
            $user->recovery_email = $data['recovery_email'] ?? null;
            $user->recovery_mobile = PhMobile::normalize($data['recovery_mobile'] ?? null);
            $user->is_active = false;
            $user->save();

            $user->assignRole($data['account_type'] === 'faculty' ? User::ROLE_ADVISER : User::ROLE_STUDENT);

            return $user;
        });

        Audit::record(AuditEvent::REGISTER, 'Account created', $user, [
            'role' => $user->getRoleNames()->first(),
            'privacy_notice_accepted_at' => now()->toIso8601String(),
        ], causer: $user);

        $activation->issue($user);

        return redirect()->route('register.done')->with('registered_email', $user->email);
    }

    public function done(Request $request): View
    {
        return view('guest.register-done', ['email' => $request->session()->get('registered_email')]);
    }

    public static function tuaOnly(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! User::isTuaEmail((string) $value)) {
                $fail('Only @'.config('tua.email_domain').' addresses can register.');
            }
        };
    }

    public static function notTua(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (User::isTuaEmail((string) $value)) {
                $fail('Use a personal email, not your TUA address.');
            }
        };
    }

    public static function phMobile(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (PhMobile::normalize((string) $value) === null) {
                $fail('Enter a Philippine mobile number, for example 0917 123 4567.');
            }
        };
    }
}
