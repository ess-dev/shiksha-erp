<?php

namespace App\Domain\Students\Queries;

use App\Domain\Students\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * How many students sit in each section right now, and how many of them are
 * girls, for the Classes and sections screen. Counts only; no student data.
 * No scope is applied: the caller passes only sections the person may see.
 */
class SectionStrengthQuery
{
    /**
     * @param  list<int>  $sectionKeys
     * @return array<int, array{students: int, girls: int}> Keyed by section key
     */
    public function forSections(array $sectionKeys): array
    {
        if ($sectionKeys === []) {
            return [];
        }

        $rows = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->whereIn('enrollments.section_id', $sectionKeys)
            ->where('enrollments.is_current', true)
            ->groupBy('enrollments.section_id')
            ->select([
                'enrollments.section_id',
                DB::raw('count(*) as students'),
                DB::raw("count(*) filter (where students.gender = 'female') as girls"),
            ])
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->getAttribute('section_id')] = [
                'students' => (int) $row->getAttribute('students'),
                'girls' => (int) $row->getAttribute('girls'),
            ];
        }

        return $counts;
    }
}
