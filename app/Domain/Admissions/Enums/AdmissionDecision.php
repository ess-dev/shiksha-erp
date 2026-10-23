<?php

namespace App\Domain\Admissions\Enums;

enum AdmissionDecision: string
{
    case Accepted = 'accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Waitlisted => 'Waitlisted',
            self::Rejected => 'Not admitted',
        };
    }

    /** The word written in the History list. */
    public function historyNote(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Waitlisted => 'Waitlisted',
            self::Rejected => 'Not admitted',
        };
    }
}
