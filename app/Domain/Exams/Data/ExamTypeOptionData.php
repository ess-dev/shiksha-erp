<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ExamType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExamTypeOptionData extends Data
{
    public function __construct(
        public ExamType $value,
        public string $label,
        public int $default_max_marks,
    ) {}
}
