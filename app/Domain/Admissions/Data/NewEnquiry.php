<?php

namespace App\Domain\Admissions\Data;

use App\Domain\Admissions\Enums\AdmissionSource;

/**
 * What the front desk collects at enquiry. `gender` is a Students gender
 * value (male, female, other); the phone is raw text the Action parses.
 */
final readonly class NewEnquiry
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $gender,
        public ?string $bornOn,
        public string $classUlid,
        public string $campusUlid,
        public string $parentName,
        public string $parentRelation,
        public string $phone,
        public AdmissionSource $source,
        public ?string $notes = null,
    ) {}
}
