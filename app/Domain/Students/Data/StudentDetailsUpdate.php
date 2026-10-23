<?php

namespace App\Domain\Students\Data;

use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;

/**
 * The editable details of a student. `houseKey` is the internal key of an
 * active house (or the student's current one), null for "Not assigned".
 */
final readonly class StudentDetailsUpdate
{
    /**
     * @param  list<GuardianUpdate>  $guardians
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $bornOn,
        public BloodGroup $bloodGroup,
        public ?int $houseKey,
        public string $address,
        public array $guardians,
    ) {}
}
