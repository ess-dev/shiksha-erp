<?php

namespace App\Domain\Admissions\Data;

/**
 * The child's own details, filled in at the Application stage. `bloodGroup` is
 * a Students blood-group value; `houseUlid` null means "Not assigned".
 */
final readonly class ApplicationDetailsUpdate
{
    public function __construct(
        public ?string $bornOn,
        public ?string $bloodGroup,
        public ?string $address,
        public ?string $houseUlid,
        public ?string $parentEmail,
    ) {}
}
