<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StaffOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
    ) {}
}
