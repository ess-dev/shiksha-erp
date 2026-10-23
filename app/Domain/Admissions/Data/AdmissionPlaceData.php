<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AdmissionPlaceData extends Data
{
    public function __construct(
        public string $campus_ulid,
        public string $class_ulid,
    ) {}
}
