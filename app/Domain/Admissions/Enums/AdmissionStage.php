<?php

namespace App\Domain\Admissions\Enums;

enum AdmissionStage: string
{
    case Enquiry = 'enquiry';
    case Application = 'application';
    case Verified = 'verified';
    case Decision = 'decision';
    case Enrolled = 'enrolled';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Application => 'Application',
            self::Verified => 'Documents verified',
            self::Decision => 'Decision',
            self::Enrolled => 'Enrolled',
        };
    }

    /** Position on the board, left to right. */
    public function position(): int
    {
        return match ($this) {
            self::Enquiry => 0,
            self::Application => 1,
            self::Verified => 2,
            self::Decision => 3,
            self::Enrolled => 4,
        };
    }

    /** From "Documents verified" onwards the child's own details must be complete. */
    public function needsCompleteDetails(): bool
    {
        return $this->position() >= self::Verified->position();
    }
}
