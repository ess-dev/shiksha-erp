<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CampusOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
    ) {}
}
