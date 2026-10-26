<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StudentLeaveRequest;
use DateTimeImmutable;

/**
 * Approved leave as Attendance needs it: who is on leave on a day, so the
 * register can show it and absences on leave send no alert to parents.
 */
class ApprovedLeaveQuery
{
    /**
     * @param  list<int>  $studentKeys
     * @return array<int, string> student key => reason label, for those on approved leave on the date
     */
    public function onDate(array $studentKeys, string $date): array
    {
        if ($studentKeys === []) {
            return [];
        }

        $found = [];

        foreach (StudentLeaveRequest::query()
            ->whereIn('student_id', $studentKeys)
            ->where('status', LeaveStatus::Approved->value)
            ->where('from_on', '<=', $date)
            ->where('to_on', '>=', $date)
            ->get(['student_id', 'reason_type']) as $leave) {
            $found[$leave->student_id] = $leave->reason_type->label();
        }

        return $found;
    }

    /**
     * @return array<string, string> calendar date => reason label, for each day of the range on approved leave
     */
    public function forStudent(int $studentKey, string $from, string $to): array
    {
        $days = [];

        foreach (StudentLeaveRequest::query()
            ->where('student_id', $studentKey)
            ->where('status', LeaveStatus::Approved->value)
            ->where('from_on', '<=', $to)
            ->where('to_on', '>=', $from)
            ->get(['from_on', 'to_on', 'reason_type']) as $leave) {
            $start = max($leave->from_on, $from);
            $end = min($leave->to_on, $to);

            for ($day = new DateTimeImmutable($start); $day->format('Y-m-d') <= $end; $day = $day->modify('+1 day')) {
                $days[$day->format('Y-m-d')] = $leave->reason_type->label();
            }
        }

        return $days;
    }
}
