<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClassLevelOptionData extends Data
{
    public function __construct(
        public int $level,
        public string $name,
    ) {}
}
