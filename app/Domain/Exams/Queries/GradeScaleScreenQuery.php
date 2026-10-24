<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Exams\Data\GradeScaleScreenData;
use App\Domain\Exams\GradeScale;
use App\Domain\Exams\Models\Exam;
use App\Domain\School\Queries\SchoolSettingsQuery;

class GradeScaleScreenQuery
{
    public function __construct(
        private readonly GradeScaleQuery $scales,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    public function get(): GradeScaleScreenData
    {
        return new GradeScaleScreenData(
            grades: $this->scales->current()->data(),
            is_saved: $this->scales->isSaved(),
            default_grades: GradeScale::default()->data(),
            frozen_exam_count: Exam::query()->whereNotNull('grade_scale')->count(),
            report_card_approval: $this->settings->reportCardApproval(),
        );
    }
}
