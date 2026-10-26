<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LeaveReasonOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
