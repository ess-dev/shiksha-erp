<?php

namespace App\Domain\Identity\Queries;

use App\Domain\Identity\Models\Identity;

class IdentityLookupQuery
{
    /**
     * The identity that signs in with this email, if there is one.
     */
    public function byEmail(string $email): ?Identity
    {
        return Identity::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }
}
