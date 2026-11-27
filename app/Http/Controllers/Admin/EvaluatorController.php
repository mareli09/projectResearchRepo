<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\ActivationService;
use App\Domain\Accounts\EvaluatorAccounts;
use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Evaluator accounts" (CLAUDE.md §7 M9, §17): evaluation copy only, System administrator only. */
class EvaluatorController extends Controller
{
    public function index(): View
    {
        return view('admin.evaluators', [
            'evaluators' => EvaluatorAccounts::enabled() ? EvaluatorAccounts::all() : [],
            'isEvalCopy' => EvaluatorAccounts::enabled(),
            'roles' => EvaluatorAccounts::ROLES,
            'units' => AcademicUnit::orderBy('code')->get(['id', 'code', 'name']),
            'programs' => Program::orderBy('name')->get(['id', 'academic_unit_id', 'name']),
            'defaultExpiry' => EvaluatorAccounts::defaultExpiry()->toDateString(),
            'latestExpiry' => EvaluatorAccounts::latestExpiry()->toDateString(),
        ]);
    }

    public function store(Request $request, ActivationService $activation): RedirectResponse
    {
        abort_unless(EvaluatorAccounts::enabled(), 403, 'Evaluator accounts exist only in the evaluation copy.');

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(EvaluatorAccounts::ROLES))],
            'academic_unit_id' => ['nullable', 'required_unless:role,'.User::ROLE_URDC, 'integer', Rule::exists('academic_units', 'id')],
            'program_id' => ['nullable', 'required_if:role,'.User::ROLE_STUDENT, 'integer',
                Rule::exists('programs', 'id')->where('academic_unit_id', (int) $request->input('academic_unit_id'))],
            'expires_on' => ['required', 'date', 'after:today', 'before_or_equal:'.EvaluatorAccounts::latestExpiry()->toDateString()],
        ], [
            'email.unique' => 'An account with this email already exists.',
            'academic_unit_id.required_unless' => 'Choose a college for a student or adviser account.',
            'program_id.required_if' => 'Choose a program for a student account.',
            'program_id.exists' => 'Choose a program from the chosen college.',
            'expires_on.after' => 'The expiry date must be after today.',
            'expires_on.before_or_equal' => 'An evaluator account can last at most '.config('tua.evaluation.max_days').' days.',
        ]);

        $user = EvaluatorAccounts::create($data, $request->user(), $activation);

        return back()->with('status', "{$user->name} was created. An activation link was sent to {$user->email}; the evaluator sets their own password.");
    }

    public function end(Request $request, User $user): RedirectResponse
    {
        abort_unless(EvaluatorAccounts::enabled(), 403);
        EvaluatorAccounts::end($user, $request->user());

        return back()->with('status', "{$user->name} has ended and was signed out.");
    }
}
