<?php

namespace App\Domain\Students\Data;

use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;

/**
 * Everything needed to enrol a new student. `houseKey` is the internal key of
 * an active house, or null for "Not assigned".
 */
final readonly class NewStudent
{
    /**
     * @param  list<NewGuardian>  $guardians
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $bornOn,
        public BloodGroup $bloodGroup,
        public ?int $houseKey,
        public string $address,
        public string $sectionUlid,
        public array $guardians,
        public ?string $admissionNumber = null,
        public ?string $admittedOn = null,
    ) {}
}
