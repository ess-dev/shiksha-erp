<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentEditData extends Data
{
    /**
     * @param  list<GuardianEditData>  $guardians
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $gender,
        public string $born_on,
        public string $blood_group,
        public string $house_ulid,
        public string $address,
        public ?int $roll_number,
        public array $guardians,
    ) {}
}
