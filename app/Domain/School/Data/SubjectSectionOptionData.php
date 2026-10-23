<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A section in the "Subject teachers" picker.
 */
#[TypeScript]
class SubjectSectionOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $campus_name,
    ) {}
}
