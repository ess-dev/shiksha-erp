<?php

namespace App\Tenancy;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    /** Being permanently deleted; see DeleteSchool. */
    case Deleting = 'deleting';

    public function allowsAccess(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            self::Suspended, self::Expired, self::Deleting => false,
        };
    }
}
