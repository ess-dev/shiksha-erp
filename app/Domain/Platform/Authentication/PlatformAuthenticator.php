<?php

namespace App\Domain\Platform\Authentication;

use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Hash;

/**
 * Checks a Super Admin's email and password. Wrong password, unknown email and
 * a disabled account all return null, so the caller can show one generic failure.
 */
class PlatformAuthenticator
{
    public function attempt(string $email, string $password): ?PlatformUser
    {
        $user = PlatformUser::query()
            ->whereRaw('lower(email) = ?', [strtolower(trim($email))])
            ->first();

        // Hash a throwaway value for unknown emails so timing does not reveal them.
        $hash = $user instanceof PlatformUser ? $user->password : Hash::make('not-a-real-password');

        if (! Hash::check($password, $hash) || ! $user instanceof PlatformUser || ! $user->isActive()) {
            return null;
        }

        return $user;
    }
}
