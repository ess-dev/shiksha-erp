<?php

namespace App\Domain\Students\Enums;

enum DocumentKind: string
{
    case BirthCertificate = 'birth_certificate';
    case Aadhaar = 'aadhaar';
    case TransferCertificate = 'transfer_certificate';
    case Photo = 'photo';
    case Medical = 'medical';
    case CategoryCertificate = 'category_certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BirthCertificate => 'Birth certificate',
            self::Aadhaar => 'Aadhaar card (masked)',
            self::TransferCertificate => 'Transfer certificate from previous school',
            self::Photo => 'Passport photo',
            self::Medical => 'Medical certificate',
            self::CategoryCertificate => 'Category certificate',
            self::Other => 'Other document',
        };
    }

    /**
     * Whether a new upload replaces the earlier one of the same kind (the
     * prototype keeps every medical and "other" document).
     */
    public function replacesEarlier(): bool
    {
        return ! in_array($this, [self::Medical, self::Other], true);
    }
}
