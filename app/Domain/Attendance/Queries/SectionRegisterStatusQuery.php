<?php

namespace App\Domain\Attendance\Queries;

use App\Domain\Attendance\Models\AttendanceRegister;
use Illuminate\Support\Facades\DB;

/**
 * Whether each section's register was saved on a day, when, and how many
 * students were absent, for the Classes and sections screen. No scope is
 * applied: the caller passes only sections the person may see.
 */
class SectionRegisterStatusQuery
{
    /**
     * @param  list<int>  $sectionKeys
     * @return array<int, array{saved_at: string, absent: int}> Keyed by section key; unmarked sections are missing
     */
    public function forDay(array $sectionKeys, string $date): array
    {
        if ($sectionKeys === []) {
            return [];
        }

        $rows = AttendanceRegister::query()
            ->whereIn('section_id', $sectionKeys)
            ->where('attendance_on', $date)
            ->select('attendance_registers.*')
            ->selectSub(
                fn ($marks) => $marks->from('attendance_marks')
                    ->select(DB::raw('count(*)'))
                    ->whereColumn('attendance_marks.attendance_register_id', 'attendance_registers.id')
                    ->where('attendance_marks.status', 'absent'),
                'absent_count',
            )
            ->get();

        $status = [];

        foreach ($rows as $row) {
            $status[$row->section_id] = [
                'saved_at' => $row->last_saved_at->toIso8601String(),
                'absent' => (int) $row->getAttribute('absent_count'),
            ];
        }

        return $status;
    }
}
