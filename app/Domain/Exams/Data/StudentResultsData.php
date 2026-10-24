<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A child's exam results for one academic session, earliest exam first, and
 * the school's grading scale for "How grades work".
 */
#[TypeScript]
class StudentResultsData extends Data
{
    /**
     * @param  list<StudentExamResultData>  $exams
     * @param  list<GradeBandData>  $scale
     */
    public function __construct(
        public array $exams,
        public array $scale,
    ) {}
}
