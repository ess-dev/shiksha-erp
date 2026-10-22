<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SessionOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public bool $is_current,
    ) {}
}
