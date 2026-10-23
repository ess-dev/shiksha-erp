<?php

namespace App\Domain\Attendance\Queries;

use App\Domain\Attendance\Data\RosterRow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Who is on a section's register on a given day: students enrolled in the
 * section that day (on the day of a section change the student is on the new
 * section's register only; the last day before a transfer still counts), plus
 * anyone who already has a mark on that day's saved register, so history stays
 * readable after a student moves or leaves. Ordered by roll number.
 */
class RosterQuery
{
    /**
     * @return list<RosterRow>
     */
    public function for(int $sectionKey, string $date, ?int $registerKey = null): array
    {
        $enrolled = $this->table('enrollments as e')
            ->join('students as st', 'st.id', '=', 'e.student_id')
            ->where('e.section_id', $sectionKey)
            ->where('e.started_on', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('e.ended_on')->orWhere('e.ended_on', '>=', $date);
            })
            ->whereNotExists(function (Builder $query) use ($date) {
                $query->select(DB::raw(1))->from('enrollments as later')
                    ->whereColumn('later.student_id', 'e.student_id')
                    ->whereColumn('later.id', '>', 'e.id')
                    ->where('later.started_on', '<=', $date);
            })
            ->get(['st.id', 'st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', 'e.roll_number']);

        $rows = [];

        foreach ($enrolled as $row) {
            $rows[(int) $row->id] = $this->row($row);
        }

        if ($registerKey !== null) {
            $marked = $this->table('attendance_marks as m')
                ->join('students as st', 'st.id', '=', 'm.student_id')
                ->where('m.attendance_register_id', $registerKey)
                ->whereNotIn('st.id', array_keys($rows))
                ->get(['st.id', 'st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', DB::raw('null as roll_number')]);

            foreach ($marked as $row) {
                $rows[(int) $row->id] = $this->row($row);
            }
        }

        $rows = array_values($rows);

        usort($rows, fn (RosterRow $a, RosterRow $b) => [$a->rollNumber ?? PHP_INT_MAX, $a->name] <=> [$b->rollNumber ?? PHP_INT_MAX, $b->name]);

        return $rows;
    }

    private function table(string $table): Builder
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'))->table($table);
    }

    private function row(stdClass $row): RosterRow
    {
        return new RosterRow(
            studentKey: (int) $row->id,
            ulid: (string) $row->ulid,
            name: trim("{$row->first_name} {$row->last_name}"),
            admissionNumber: (string) $row->admission_number,
            rollNumber: $row->roll_number === null ? null : (int) $row->roll_number,
        );
    }
}
