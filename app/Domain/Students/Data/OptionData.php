<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
