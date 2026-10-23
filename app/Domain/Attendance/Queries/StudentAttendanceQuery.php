<?php

namespace App\Domain\Attendance\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Data\StudentDayData;
use App\Domain\Attendance\Data\StudentTallyData;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Enums\DayState;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;

/**
 * One student's attendance over a stretch of days, read from the registers.
 * Server-side callers decide whose child it is; nothing here checks access.
 */
class StudentAttendanceQuery
{
    public function __construct(
        private readonly SchoolDayQuery $schoolDays,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * Every calendar day from `$from` to `$to` (inclusive), oldest first.
     *
     * @param  int  $studentKey  The student's internal key (server side only)
     * @return list<StudentDayData>
     */
    public function days(int $studentKey, string $from, string $to, ?string $today = null): array
    {
        $today ??= CalendarDate::today();

        $rows = AttendanceMark::query()
            ->join('attendance_registers', 'attendance_registers.id', '=', 'attendance_marks.attendance_register_id')
            ->where('attendance_marks.student_id', $studentKey)
            ->whereBetween('attendance_registers.attendance_on', [$from, $to])
            ->get([
                'attendance_marks.status',
                'attendance_registers.attendance_on',
                'attendance_registers.last_saved_at',
                'attendance_registers.saved_by_staff_id',
                'attendance_registers.last_reason',
            ]);

        $names = $this->staff->namesByKey(array_values($rows->pluck('saved_by_staff_id')->map(fn ($key) => (int) $key)->all()));
        $marks = [];

        foreach ($rows as $row) {
            $marks[(string) $row->getAttribute('attendance_on')] = $row;
        }

        $checks = $this->schoolDays->range($from, $to);
        $days = [];

        foreach ($checks as $date => $check) {
            $mark = $marks[$date] ?? null;

            if ($mark !== null) {
                $status = $mark->getAttribute('status');
                $status = $status instanceof AttendanceStatus ? $status : AttendanceStatus::from((string) $status);
                $staffKey = $mark->getAttribute('saved_by_staff_id');
                $savedAt = $mark->getAttribute('last_saved_at');

                $days[] = new StudentDayData(
                    $date,
                    match ($status) {
                        AttendanceStatus::Present => DayState::Present,
                        AttendanceStatus::Absent => DayState::Absent,
                        AttendanceStatus::Late => DayState::Late,
                    },
                    null,
                    $staffKey === null ? null : ($names[(int) $staffKey] ?? null),
                    $savedAt === null ? null : (new DateTimeImmutable((string) $savedAt))->format(DATE_ATOM),
                    $mark->getAttribute('last_reason'),
                );

                continue;
            }

            $days[] = match (true) {
                $check->isHoliday => new StudentDayData($date, DayState::Holiday, $check->reason),
                ! $check->isSchoolDay => new StudentDayData($date, DayState::Off, $check->reason),
                $date > $today => new StudentDayData($date, DayState::Upcoming),
                default => new StudentDayData($date, DayState::Unmarked),
            };
        }

        return $days;
    }

    /**
     * Counts of present, late and absent days over a stretch (days without a
     * register are not school days for this student).
     */
    public function tally(int $studentKey, string $from, string $to): StudentTallyData
    {
        return $this->tallyOf($this->days($studentKey, $from, $to));
    }

    /**
     * Only the absent days.
     *
     * @param  list<StudentDayData>  $days
     * @return list<StudentDayData>
     */
    public function absencesOf(array $days): array
    {
        return array_values(array_filter($days, fn (StudentDayData $day) => $day->state === DayState::Absent));
    }

    /**
     * @param  list<StudentDayData>  $days
     */
    public function tallyOf(array $days): StudentTallyData
    {
        $present = $late = $absent = 0;

        foreach ($days as $day) {
            match ($day->state) {
                DayState::Present => $present++,
                DayState::Late => $late++,
                DayState::Absent => $absent++,
                default => null,
            };
        }

        $recorded = $present + $late + $absent;

        $attended = $present + $late;

        // Half-up to a tenth of a percent, in integers.
        return new StudentTallyData($present, $late, $absent, $recorded, $recorded === 0 ? null : intdiv($attended * 2000 + $recorded, $recorded * 2));
    }
}
