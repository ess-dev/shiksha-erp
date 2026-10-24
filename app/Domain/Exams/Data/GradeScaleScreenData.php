<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The grading scale settings page: the school's scale, whether it saved its
 * own, the default to reset to, and how many exams keep a frozen scale.
 */
#[TypeScript]
class GradeScaleScreenData extends Data
{
    /**
     * @param  list<GradeBandData>  $grades
     * @param  list<GradeBandData>  $default_grades
     */
    public function __construct(
        public array $grades,
        public bool $is_saved,
        public array $default_grades,
        public int $frozen_exam_count,
    ) {}
}
