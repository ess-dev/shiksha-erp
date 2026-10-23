<?php

namespace App\Domain\Attendance\Queries;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Attendance\AttendanceAccess;
use App\Domain\Attendance\Data\AbsentStudentData;
use App\Domain\Attendance\Data\AttendanceDashboardData;
use App\Domain\Attendance\Data\AttendanceTrendPointData;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Attendance for the staff dashboard: today's figures, the trend over the last
 * school days, sections still to mark, students absent often, and today's
 * absentees. Only this year's sections the person may see (`attendance.view`
 * or `attendance.mark` scope), narrowed to the top-bar campus.
 */
class AttendanceDashboardQuery
{
    /** How far back to look for school days, in calendar days. */
    private const LOOKBACK_DAYS = 60;

    /** Absentee rows sent to the page; the total is sent too. */
    private const ABSENTEE_LIMIT = 50;

    /** Names shown in the "frequent absences" line. */
    private const FREQUENT_NAMES = 3;

    public function __construct(
        private readonly SectionDirectoryQuery $sections,
        private readonly AttendanceAccess $access,
        private readonly PermissionScopesQuery $scopes,
        private readonly SchoolDayQuery $schoolDays,
        private readonly SectionRegisterStatusQuery $registers,
    ) {}

    /**
     * Null when the person may not see any register.
     */
    public function get(Identity $identity, ?string $campusUlid): ?AttendanceDashboardData
    {
        if ($this->scopes->get($identity, 'attendance.view')->isEmpty() && $this->scopes->get($identity, 'attendance.mark')->isEmpty()) {
            return null;
        }

        $sections = [];

        foreach ($this->sections->currentYear() as $section) {
            if (($campusUlid === null || $section->campusUlid === $campusUlid) && $this->access->mayView($identity, $section)) {
                $sections[$section->sectionKey] = $section;
            }
        }

        $keys = array_keys($sections);
        $today = CalendarDate::today();
        $todayCheck = $this->schoolDays->check($today);
        $pastDays = $this->pastSchoolDays($today, (int) config('shiksha.attendance_frequent_absence.school_days'));
        $trendDays = $todayCheck->isSchoolDay ? [...$pastDays, $today] : $pastDays;
        $totals = $this->dailyTotals($keys, $trendDays);

        $status = $this->registers->forDay($keys, $today);
        $severalCampuses = count($this->campusNames($sections)) > 1;
        $unmarked = $todayCheck->isSchoolDay
            ? array_values(array_map(
                fn (SectionRefData $section) => $section->label().($severalCampuses ? " ({$section->campusName})" : ''),
                array_filter($sections, fn (SectionRefData $section) => ! isset($status[$section->sectionKey])),
            ))
            : [];

        [$frequentCount, $frequentNames] = $this->frequentAbsences($keys, $pastDays);
        [$absentees, $absenteesTotal] = $this->absentees($identity, $sections, $today, $pastDays);

        return new AttendanceDashboardData(
            today: $today,
            closed_reason: $todayCheck->isSchoolDay ? null : $todayCheck->reason,
            percent_tenths: $todayCheck->isSchoolDay ? self::percentTenths($totals[$today] ?? null) : null,
            absent: array_sum(array_column($status, 'absent')),
            sections_marked: count($status),
            sections_total: count($sections),
            trend: array_map(
                fn (string $day) => new AttendanceTrendPointData($day, self::percentTenths($totals[$day] ?? null)),
                $trendDays,
            ),
            unmarked_sections: $unmarked,
            frequent_absent_count: $frequentCount,
            frequent_absentees: $frequentNames,
            frequent_absence_threshold: (int) config('shiksha.attendance_frequent_absence.absences'),
            frequent_absence_days: count($pastDays),
            absentees: $absentees,
            absentees_total: $absenteesTotal,
        );
    }

    /**
     * The last `$count` school days before today, oldest first, never before
     * the current academic year began.
     *
     * @return list<string>
     */
    private function pastSchoolDays(string $today, int $count): array
    {
        $from = CarbonImmutable::parse($today)->subDays(self::LOOKBACK_DAYS)->toDateString();
        $yearStart = $this->sections->currentYearRef()['starts_on'] ?? $from;
        $from = max($from, (string) $yearStart);
        $to = CarbonImmutable::parse($today)->subDay()->toDateString();

        if ($from > $to) {
            return [];
        }

        $open = array_keys(array_filter($this->schoolDays->range($from, $to), fn ($check) => $check->isSchoolDay));

        return array_slice($open, -$count);
    }

    /**
     * Marked and absent students per day over the given sections.
     *
     * @param  list<int>  $sectionKeys
     * @param  list<string>  $days
     * @return array<string, array{marked: int, absent: int}>
     */
    private function dailyTotals(array $sectionKeys, array $days): array
    {
        if ($sectionKeys === [] || $days === []) {
            return [];
        }

        $rows = $this->table('attendance_registers as r')
            ->join('attendance_marks as m', 'm.attendance_register_id', '=', 'r.id')
            ->whereIn('r.section_id', $sectionKeys)
            ->whereIn('r.attendance_on', $days)
            ->groupBy('r.attendance_on')
            ->select('r.attendance_on')
            ->selectRaw('count(*) as marked')
            ->selectRaw("sum(case when m.status = 'absent' then 1 else 0 end) as absent")
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[CalendarDate::parse((string) $row->attendance_on)] = ['marked' => (int) $row->marked, 'absent' => (int) $row->absent];
        }

        return $totals;
    }

    /**
     * Students absent at least the threshold number of times on the given days.
     *
     * @param  list<int>  $sectionKeys
     * @param  list<string>  $days
     * @return array{0: int, 1: list<string>} How many, and the first few names
     */
    private function frequentAbsences(array $sectionKeys, array $days): array
    {
        if ($sectionKeys === [] || $days === []) {
            return [0, []];
        }

        $rows = $this->table('attendance_marks as m')
            ->join('attendance_registers as r', 'r.id', '=', 'm.attendance_register_id')
            ->join('students as st', 'st.id', '=', 'm.student_id')
            ->whereIn('r.section_id', $sectionKeys)
            ->whereIn('r.attendance_on', $days)
            ->where('m.status', 'absent')
            ->groupBy('st.id', 'st.first_name', 'st.last_name')
            ->havingRaw('count(*) >= ?', [(int) config('shiksha.attendance_frequent_absence.absences')])
            ->select('st.first_name', 'st.last_name')
            ->selectRaw('count(*) as total')
            ->orderByDesc('total')
            ->orderBy('st.first_name')
            ->orderBy('st.last_name')
            ->get();

        $names = [];

        foreach ($rows->take(self::FREQUENT_NAMES) as $row) {
            $names[] = trim("{$row->first_name} {$row->last_name}");
        }

        return [$rows->count(), $names];
    }

    /**
     * Today's absent marks with the primary guardian and who saved the register.
     *
     * @param  array<int, SectionRefData>  $sections
     * @param  list<string>  $pastDays
     * @return array{0: list<AbsentStudentData>, 1: int}
     */
    private function absentees(Identity $identity, array $sections, string $today, array $pastDays): array
    {
        if ($sections === []) {
            return [[], 0];
        }

        $rows = $this->table('attendance_marks as m')
            ->join('attendance_registers as r', 'r.id', '=', 'm.attendance_register_id')
            ->join('students as st', 'st.id', '=', 'm.student_id')
            ->leftJoin('enrollments as e', fn ($join) => $join->on('e.student_id', '=', 'st.id')->on('e.section_id', '=', 'r.section_id')->where('e.is_current', true))
            ->leftJoin('guardian_student as gs', fn ($join) => $join->on('gs.student_id', '=', 'st.id')->where('gs.is_primary', true))
            ->leftJoin('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->leftJoin('staff as sf', 'sf.id', '=', 'r.saved_by_staff_id')
            ->whereIn('r.section_id', array_keys($sections))
            ->where('r.attendance_on', $today)
            ->where('m.status', 'absent')
            ->get([
                'st.id', 'st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', 'e.roll_number',
                'r.section_id', 'r.last_saved_at', 'g.name as guardian_name', 'gs.relation', 'g.phone', 'sf.full_name as marked_by',
            ]);

        $order = array_flip(array_keys($sections));
        $rows = $rows->all();
        usort($rows, fn (stdClass $a, stdClass $b) => [$order[$a->section_id], $a->roll_number ?? PHP_INT_MAX, $a->first_name]
            <=> [$order[$b->section_id], $b->roll_number ?? PHP_INT_MAX, $b->first_name]);

        $total = count($rows);
        $rows = array_slice($rows, 0, self::ABSENTEE_LIMIT);
        $streaks = $this->streaks(array_map(fn (stdClass $row) => (int) $row->id, $rows), $pastDays);
        $sensitive = $this->scopes->get($identity, 'students.view_sensitive');

        $list = array_map(function (stdClass $row) use ($sections, $streaks, $sensitive) {
            $section = $sections[(int) $row->section_id];
            $phone = $row->phone === null ? null : (string) $row->phone;

            return new AbsentStudentData(
                student_ulid: (string) $row->ulid,
                name: trim("{$row->first_name} {$row->last_name}"),
                roll_number: $row->roll_number === null ? null : (int) $row->roll_number,
                admission_number: (string) $row->admission_number,
                section_label: $section->label(),
                campus_name: $section->campusName,
                guardian_name: $row->guardian_name,
                guardian_relation: $row->relation === null ? null : ucfirst((string) $row->relation),
                guardian_phone: $phone === null || $sensitive->allows($section->scopeTarget()) ? $phone : PhoneNumber::mask($phone),
                days_in_a_row: 1 + ($streaks[(int) $row->id] ?? 0),
                marked_by: $row->marked_by,
                marked_at: CarbonImmutable::parse((string) $row->last_saved_at)->toIso8601String(),
            );
        }, $rows);

        return [$list, $total];
    }

    /**
     * How many school days in a row, counting back from yesterday, each
     * student was marked absent.
     *
     * @param  list<int>  $studentKeys
     * @param  list<string>  $pastDays
     * @return array<int, int>
     */
    private function streaks(array $studentKeys, array $pastDays): array
    {
        if ($studentKeys === [] || $pastDays === []) {
            return [];
        }

        $absent = [];

        $rows = $this->table('attendance_marks as m')
            ->join('attendance_registers as r', 'r.id', '=', 'm.attendance_register_id')
            ->whereIn('m.student_id', $studentKeys)
            ->whereIn('r.attendance_on', $pastDays)
            ->where('m.status', 'absent')
            ->get(['m.student_id', 'r.attendance_on']);

        foreach ($rows as $row) {
            $absent[(int) $row->student_id][CalendarDate::parse((string) $row->attendance_on)] = true;
        }

        $streaks = [];

        foreach ($studentKeys as $key) {
            $streaks[$key] = 0;

            foreach (array_reverse($pastDays) as $day) {
                if (! isset($absent[$key][$day])) {
                    break;
                }

                $streaks[$key]++;
            }
        }

        return $streaks;
    }

    /**
     * @param  array<int, SectionRefData>  $sections
     * @return list<string>
     */
    private function campusNames(array $sections): array
    {
        return array_values(array_unique(array_map(fn (SectionRefData $section) => $section->campusName, $sections)));
    }

    /**
     * Present (late counts as present) out of marked, in tenths of a percent, half-up.
     *
     * @param  array{marked: int, absent: int}|null  $totals
     */
    private static function percentTenths(?array $totals): ?int
    {
        if ($totals === null || $totals['marked'] === 0) {
            return null;
        }

        $present = $totals['marked'] - $totals['absent'];

        return intdiv($present * 2000 + $totals['marked'], $totals['marked'] * 2);
    }

    private function table(string $table): Builder
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'))->table($table);
    }
}
