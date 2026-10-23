<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Domain\Identity\Actions\ChangeIdentityPassword;
use App\Domain\Identity\Models\Identity;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly ChangeIdentityPassword $changePassword) {}

    /**
     * Validate and reset the identity's forgotten password.
     *
     * @param  Identity  $user
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $this->changePassword->handle($user, $input['password']);
    }
}
