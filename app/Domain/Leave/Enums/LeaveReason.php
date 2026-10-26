<?php

namespace App\Domain\Leave\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The reason types of the prototype's leave form. */
#[TypeScript]
enum LeaveReason: string
{
    case Sick = 'sick';
    case FamilyFunction = 'family_function';
    case FamilyEmergency = 'family_emergency';
    case Travel = 'travel';
    case Religious = 'religious';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Sick',
            self::FamilyFunction => 'Family function',
            self::FamilyEmergency => 'Family emergency',
            self::Travel => 'Travel',
            self::Religious => 'Religious observance',
            self::Other => 'Other',
        };
    }
}
