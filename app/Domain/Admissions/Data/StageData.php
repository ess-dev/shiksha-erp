<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StageData extends Data
{
    public function __construct(
        public string $stage,
        public string $label,
        public int $count,
    ) {}
}
