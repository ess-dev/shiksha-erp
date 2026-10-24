<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A section in the marks screen's Class picker.
 */
#[TypeScript]
class MarksSectionOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
        public string $campus_name,
    ) {}
}
