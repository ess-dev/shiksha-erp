<?php

namespace App\Domain\Students\Data;

/**
 * An accepted applicant, as the Admissions module hands them to Students for
 * enrolment. Plain strings, so Admissions never needs the Students enums:
 * `gender` and `bloodGroup` must be values of those enums (the application
 * stores only valid ones).
 */
final readonly class AdmissionEnrolment
{
    /**
     * @param  list<AdmissionFile>  $files  Scans collected during admission
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $bornOn,
        public string $bloodGroup,
        public ?string $houseUlid,
        public string $address,
        public string $sectionUlid,
        public string $guardianName,
        public string $guardianRelation,
        public string $guardianPhone,
        public ?string $guardianEmail,
        public array $files = [],
    ) {}
}
