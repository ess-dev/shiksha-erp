<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClassOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public int $level,
    ) {}
}
