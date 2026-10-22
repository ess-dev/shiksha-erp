<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SchoolSettingsData extends Data
{
    public function __construct(
        public string $admission_prefix,
        public string $tc_prefix,
    ) {}
}
