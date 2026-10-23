<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AdmissionAbilitiesData extends Data
{
    public function __construct(
        public bool $manage,
        public bool $enroll,
        public bool $documents,
        public bool $view_sensitive,
        public bool $settings,
    ) {}
}
