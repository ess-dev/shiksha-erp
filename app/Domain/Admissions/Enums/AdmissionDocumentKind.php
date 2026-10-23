<?php

namespace App\Domain\Admissions\Enums;

/**
 * The documents an application can collect before enrolment (the same four
 * kinds the prototype lists). Which are required is a school setting.
 */
enum AdmissionDocumentKind: string
{
    case BirthCertificate = 'birth_certificate';
    case Aadhaar = 'aadhaar';
    case TransferCertificate = 'transfer_certificate';
    case Photo = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::BirthCertificate => 'Birth certificate',
            self::Aadhaar => 'Aadhaar card',
            self::TransferCertificate => 'Transfer certificate',
            self::Photo => 'Passport photo',
        };
    }
}
