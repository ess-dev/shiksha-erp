<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExamClassOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
    ) {}
}
