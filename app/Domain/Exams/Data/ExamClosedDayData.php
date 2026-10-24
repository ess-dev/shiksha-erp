<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExamClosedDayData extends Data
{
    public function __construct(
        public string $date,
        public string $name,
    ) {}
}
