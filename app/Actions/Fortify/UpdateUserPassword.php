<?php

namespace App\Actions\Fortify;

use App\Domain\Accounts\PasswordResetter;
use App\Domain\Accounts\PasswordRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/** "Change password" on the Account and recovery page. Other sessions are signed out. */
class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(private readonly PasswordResetter $resetter) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => PasswordRules::rules(),
        ], [
            'current_password.current_password' => 'This is not your current password.',
        ])->validateWithBag('updatePassword');

        $this->resetter->reset($user, $input['password'], 'changed on the account page', session()->getId());
    }
}
