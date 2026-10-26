<?php

namespace App\Domain\Attendance\Queries;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\AttendanceAccess;
use App\Domain\Attendance\Data\RegisterData;
use App\Domain\Attendance\Data\RosterStudentData;
use App\Domain\Attendance\Data\SavedRegisterData;
use App\Domain\Attendance\Data\SectionChoiceData;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Enums\RegisterState;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Queries\ApprovedLeaveQuery;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use InvalidArgumentException;

class RegisterQuery
{
    /** How many earlier registers the "absent X of the last 10 days" flag looks at. */
    private const RECENT_REGISTERS = 10;

    public function __construct(
        private readonly AttendanceAccess $access,
        private readonly SectionDirectoryQuery $sections,
        private readonly RosterQuery $roster,
        private readonly SchoolDayQuery $days,
        private readonly StaffRefQuery $staff,
        private readonly PermissionScopesQuery $scopes,
        private readonly ApprovedLeaveQuery $leave,
    ) {}

    /**
     * The register of one class on one day, for the screen. The class must be
     * one the person may open (otherwise their first class is shown); a date
     * outside the current academic year or in the future becomes today.
     */
    public function get(Identity $identity, ?string $sectionUlid, ?string $date): RegisterData
    {
        $today = CalendarDate::today();
        $minDate = $this->sections->currentYearRef()['starts_on'] ?? $today;
        $date = $this->validDate($date, $minDate, $today);

        $choices = $this->choices($identity);
        $canManageSettings = $this->scopes->get($identity, 'settings.manage')->allows();

        if ($choices === []) {
            return new RegisterData([], null, $date, $today, $minDate, $this->timezone(), RegisterState::ViewOnly, null, false, false, 0, $this->access->correctionWindowDays(), null, null, [], $canManageSettings);
        }

        $selected = $choices[0];

        foreach ($choices as $choice) {
            if ($choice['ref']->ulid === $sectionUlid) {
                $selected = $choice;
            }
        }

        /** @var SectionRefData $section */
        $section = $selected['ref'];

        $age = (int) (new DateTimeImmutable($date))->diff(new DateTimeImmutable($today))->days;
        $day = $this->days->check($date);
        $rights = $this->access->rights($identity, $section, $age);

        $register = AttendanceRegister::query()->where('section_id', $section->sectionKey)->where('attendance_on', $date)->first();
        $marks = $register === null ? [] : $register->marks()->pluck('status', 'student_id')->all();

        $showRoster = $day->isSchoolDay && ($register !== null || $rights->canEdit);
        $recent = $showRoster ? $this->recentAbsences($section->sectionKey, $date) : [];

        $rows = $showRoster ? $this->roster->for($section->sectionKey, $date, $register?->id) : [];
        $onLeave = $this->leave->onDate(array_map(fn ($row) => $row->studentKey, $rows), $date);

        $students = array_map(fn ($row) => new RosterStudentData(
            $row->ulid, $row->name, $row->admissionNumber, $row->rollNumber,
            isset($marks[$row->studentKey]) ? $this->status($marks[$row->studentKey]) : null,
            $recent[$row->studentKey] ?? 0,
            $onLeave[$row->studentKey] ?? null,
        ), $rows);

        return new RegisterData(
            sections: array_map(fn (array $choice) => $choice['data'], $choices),
            section: $section->ulid,
            date: $date,
            today: $today,
            min_date: $minDate,
            timezone: $this->timezone(),
            state: $this->state($day->isSchoolDay, $rights->canEdit, $register !== null, $age, $identity, $section),
            closed_reason: $day->reason,
            can_edit: $day->isSchoolDay && $rights->canEdit,
            needs_reason: $rights->needsReason,
            age_days: $age,
            correction_window_days: $this->access->correctionWindowDays(),
            class_teacher_name: $section->classTeacherName,
            saved: $register === null ? null : new SavedRegisterData(
                $register->saved_by_staff_id === null ? null : ($this->staff->namesByKey([$register->saved_by_staff_id])[$register->saved_by_staff_id] ?? null),
                $register->last_saved_at->toIso8601String(),
                $register->last_reason,
            ),
            students: $students,
            can_manage_settings: $canManageSettings,
        );
    }

    private function state(bool $isSchoolDay, bool $canEdit, bool $hasRegister, int $age, Identity $identity, SectionRefData $section): RegisterState
    {
        if (! $isSchoolDay) {
            return RegisterState::Closed;
        }

        if (! $canEdit) {
            return $this->access->isClassTeacher($identity, $section) ? RegisterState::Locked : RegisterState::ViewOnly;
        }

        if ($hasRegister) {
            return RegisterState::Saved;
        }

        return $age === 0 ? RegisterState::Pending : RegisterState::Missed;
    }

    /**
     * The classes the person may open this year, their own first.
     *
     * @return list<array{ref: SectionRefData, relation: string, data: SectionChoiceData}>
     */
    private function choices(Identity $identity): array
    {
        $allowed = array_values(array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => $this->access->mayView($identity, $section),
        ));

        $severalCampuses = count(array_unique(array_map(fn (SectionRefData $section) => $section->campusUlid, $allowed))) > 1;
        $own = [];
        $others = [];

        foreach ($allowed as $section) {
            $relation = $this->access->isClassTeacher($identity, $section) ? 'yours' : ($this->access->mayCorrect($identity, $section) ? 'correct' : 'view');
            $label = $severalCampuses ? "{$section->label()} · {$section->campusName}" : $section->label();
            $choice = [
                'ref' => $section,
                'relation' => $relation,
                'data' => new SectionChoiceData($section->ulid, $label, $section->campusName, $relation),
            ];

            if ($relation === 'yours') {
                $own[] = $choice;
            } else {
                $others[] = $choice;
            }
        }

        return [...$own, ...$others];
    }

    /**
     * Absent marks per student over the section's last registers before the day.
     *
     * @return array<int, int> student key => times absent
     */
    private function recentAbsences(int $sectionKey, string $date): array
    {
        $registerKeys = AttendanceRegister::query()
            ->where('section_id', $sectionKey)
            ->where('attendance_on', '<', $date)
            ->orderByDesc('attendance_on')
            ->limit(self::RECENT_REGISTERS)
            ->pluck('id')
            ->all();

        if ($registerKeys === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = AttendanceMark::query()
            ->whereIn('attendance_register_id', $registerKeys)
            ->where('status', 'absent')
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id')
            ->map(fn (mixed $total) => (int) $total)
            ->all();

        return $counts;
    }

    private function validDate(?string $date, string $minDate, string $today): string
    {
        if ($date === null || $date === '') {
            return $today;
        }

        try {
            $date = CalendarDate::parse($date);
        } catch (InvalidArgumentException) {
            return $today;
        }

        return $date > $today || $date < $minDate ? $today : $date;
    }

    private function status(mixed $value): ?AttendanceStatus
    {
        return $value instanceof AttendanceStatus ? $value : AttendanceStatus::tryFrom((string) $value);
    }

    private function timezone(): string
    {
        return (string) config('shiksha.timezone');
    }
}
