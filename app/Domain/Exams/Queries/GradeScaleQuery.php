<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Exams\GradeScale;
use App\Domain\Exams\Models\Exam;
use App\Domain\School\Queries\SchoolSettingsQuery;

/**
 * Which grading scale applies: the school's own (or the default until it saves
 * one), and for an exam the scale frozen when its first marks were published.
 */
class GradeScaleQuery
{
    public function __construct(private readonly SchoolSettingsQuery $settings) {}

    public function current(): GradeScale
    {
        return GradeScale::fromStored($this->settings->gradeScale()) ?? GradeScale::default();
    }

    /** Whether the school saved its own scale (otherwise the default applies). */
    public function isSaved(): bool
    {
        return GradeScale::fromStored($this->settings->gradeScale()) !== null;
    }

    public function forExam(Exam $exam): GradeScale
    {
        return GradeScale::fromStored($exam->grade_scale) ?? $this->current();
    }
}
