<?php

namespace App\Domain\Students\Enums;

enum TransferReason: string
{
    case FamilyRelocating = 'family_relocating';
    case ChangingSchool = 'changing_school';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FamilyRelocating => 'Family relocating',
            self::ChangingSchool => 'Changing school',
            self::Other => 'Other',
        };
    }
}
