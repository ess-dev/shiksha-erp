<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SectionSubjectTeacher;

/**
 * Subjects a member of staff is chosen to teach in the current academic year,
 * for their own profile. (Subjects taught only as an automatic class teacher
 * are not listed.)
 */
class StaffSubjectsQuery
{
    /**
     * @return list<string>
     */
    public function names(int $staffKey): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        if ($year === null) {
            return [];
        }

        return array_values(SectionSubjectTeacher::query()
            ->join('subjects', 'subjects.id', '=', 'section_subject_teachers.subject_id')
            ->join('sections', 'sections.id', '=', 'section_subject_teachers.section_id')
            ->where('section_subject_teachers.staff_id', $staffKey)
            ->where('sections.academic_year_id', $year->id)
            ->distinct()
            ->orderBy('subjects.name')
            ->pluck('subjects.name')
            ->all());
    }
}
