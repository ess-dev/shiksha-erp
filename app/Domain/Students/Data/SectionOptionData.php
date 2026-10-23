<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SectionOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
        public string $campus_name,
        public int $enrolled,
        public ?int $capacity,
        public bool $is_full,
    ) {}
}
