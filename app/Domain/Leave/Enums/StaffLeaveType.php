<?php

namespace App\Domain\Leave\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The leave types of the prototype's staff leave policy. Only the yearly allowance is a school setting. */
#[TypeScript]
enum StaffLeaveType: string
{
    case Casual = 'casual';
    case Sick = 'sick';
    case Earned = 'earned';
    case Duty = 'duty';

    public function label(): string
    {
        return match ($this) {
            self::Casual => 'Casual leave',
            self::Sick => 'Sick leave',
            self::Earned => 'Earned leave',
            self::Duty => 'Duty leave (school work)',
        };
    }

    /**
     * illustrative — verify: the prototype's allowance in days per academic
     * year; null means "as needed". Schools change these in Leave settings.
     */
    public function defaultDays(): ?int
    {
        return match ($this) {
            self::Casual => 12,
            self::Sick => 10,
            self::Earned => 15,
            self::Duty => null,
        };
    }

    /** illustrative — verify: the prototype asks for a medical certificate after 2 days of sick leave. */
    public function defaultDocumentAfterDays(): ?int
    {
        return $this === self::Sick ? 2 : null;
    }
}
