<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A child's result in one exam, from published marks only. The total counts
 * subjects with published marks (absences and awaiting subjects are left
 * out); `percent_tenths` is tenths of a percent, half-up (913 = 91.3%), and
 * the overall grade comes from the exam's grading scale.
 */
#[TypeScript]
class StudentExamResultData extends Data
{
    /**
     * @param  string  $held_on  First day of the exam
     * @param  int  $pending_count  Subjects still awaiting results
     * @param  list<StudentSubjectResultData>  $subjects
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $held_on,
        public int $max_marks,
        public int $pending_count,
        public array $subjects,
        public ?string $total_marks,
        public ?int $total_max,
        public ?int $percent_tenths,
        public ?string $grade,
        public bool $is_lowest_grade,
    ) {}
}
