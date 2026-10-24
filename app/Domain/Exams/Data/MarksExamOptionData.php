<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\ExamStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An exam in the marks screen's Exam picker ("October Unit Test · out of 25").
 */
#[TypeScript]
class MarksExamOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public int $max_marks,
        public ExamStatus $status,
    ) {}
}
