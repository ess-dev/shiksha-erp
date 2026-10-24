<?php

namespace App\Domain\Exams\Data;

use App\Domain\Exams\Enums\SubjectResultState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One subject of a child's exam result. Marks, percent and grade are set only
 * for published marks (`marks` such as "38" or "12.5"; percent whole, half-up).
 */
#[TypeScript]
class StudentSubjectResultData extends Data
{
    public function __construct(
        public string $subject_name,
        public SubjectResultState $state,
        public ?string $marks,
        public ?int $percent,
        public ?string $grade,
        public ?int $grade_point,
        public bool $is_lowest_grade,
    ) {}
}
