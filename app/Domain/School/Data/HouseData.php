<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HouseData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $status,
        public int $student_count,
    ) {}
}
