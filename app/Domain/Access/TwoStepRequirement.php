<?php

namespace App\Domain\Access;

use App\Domain\Identity\Models\Identity;

/**
 * Two-step sign-in is mandatory for a school's owner and for anyone whose
 * roles include a critical permission (refunds, roles and access, ...).
 */
class TwoStepRequirement
{
    public function __construct(private readonly Authorizer $authorizer) {}

    public function isRequiredFor(Identity $identity): bool
    {
        return $this->authorizer->holdsOwnerRole($identity)
            || $this->authorizer->holdsCriticalPermission($identity);
    }

    public function isSatisfiedBy(Identity $identity): bool
    {
        return $identity->hasEnabledTwoFactorAuthentication();
    }
}
