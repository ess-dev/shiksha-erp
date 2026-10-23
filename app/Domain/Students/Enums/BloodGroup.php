<?php

namespace App\Domain\Students\Enums;

/**
 * "Unknown" is allowed for now so a school without the information can still
 * admit a child; it is meant to be removed later (owner decision 2026-10-22).
 */
enum BloodGroup: string
{
    case APositive = 'A+';
    case ANegative = 'A-';
    case BPositive = 'B+';
    case BNegative = 'B-';
    case ABPositive = 'AB+';
    case ABNegative = 'AB-';
    case OPositive = 'O+';
    case ONegative = 'O-';
    case Unknown = 'unknown';

    public function label(): string
    {
        return $this === self::Unknown ? 'Unknown' : $this->value;
    }

    public static function fromInput(string $input): ?self
    {
        $clean = strtoupper(str_replace(' ', '', trim($input)));

        return $clean === 'UNKNOWN' ? self::Unknown : self::tryFrom($clean);
    }
}
