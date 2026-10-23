<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\AttendanceAccess;
use App\Domain\Attendance\Contracts\AbsenceNotifier;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Exceptions\AttendanceException;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaveRegister extends Action
{
    public function __construct(
        private readonly AttendanceAccess $access,
        private readonly SectionDirectoryQuery $sections,
        private readonly SchoolDayQuery $days,
        private readonly RosterQuery $roster,
        private readonly StaffRefQuery $staff,
        private readonly AbsenceNotifier $notifier,
    ) {}

    /**
     * Saves one class's register for one school day: every student on it
     * marked present, absent or late. The class teacher saves it (free on the
     * day, with a reason on later days inside the school's correction window);
     * anyone holding `attendance.correct` over the class may change it on any
     * day, always with a reason. Writes one audit event (saved, or corrected
     * with the marks that changed). Guardians are told through the
     * AbsenceNotifier after the register is stored.
     *
     * @param  array<string, AttendanceStatus>  $marks  student ulid => status, one per student on the register
     */
    public function handle(Identity $actor, string $sectionUlid, string $date, array $marks, ?string $reason = null): AttendanceRegister
    {
        $year = $this->sections->currentYearRef();
        $section = $this->sections->find($sectionUlid);

        if ($year === null || $section === null || $section->academicYearKey !== $year['key'] || $section->status !== 'active' || ! $this->access->mayView($actor, $section)) {
            throw AttendanceException::notFound();
        }

        try {
            $date = CalendarDate::parse($date);
        } catch (InvalidArgumentException) {
            throw AttendanceException::invalidDate();
        }

        $today = CalendarDate::today();

        if ($date > $today) {
            throw AttendanceException::futureDate();
        }

        if ($date < $year['starts_on']) {
            throw AttendanceException::beforeYear();
        }

        $day = $this->days->check($date);

        if (! $day->isSchoolDay) {
            throw AttendanceException::closedDay((string) $day->reason);
        }

        $age = (int) (new DateTimeImmutable($date))->diff(new DateTimeImmutable($today))->days;
        $rights = $this->access->rights($actor, $section, $age);

        if (! $rights->canEdit) {
            throw $this->access->isClassTeacher($actor, $section)
                ? AttendanceException::locked($this->access->correctionWindowDays())
                : AttendanceException::notAllowed();
        }

        $existing = AttendanceRegister::query()->where('section_id', $section->sectionKey)->where('attendance_on', $date)->first();
        $roster = $this->roster->for($section->sectionKey, $date, $existing?->id);

        $byUlid = [];

        foreach ($roster as $row) {
            $byUlid[$row->ulid] = $row;
        }

        if (array_diff(array_keys($marks), array_keys($byUlid)) !== []) {
            throw AttendanceException::unknownStudent();
        }

        $unmarked = count(array_diff_key($byUlid, $marks));

        if ($unmarked > 0) {
            throw AttendanceException::incomplete($unmarked);
        }

        $before = [];

        if ($existing !== null) {
            foreach ($existing->marks as $mark) {
                $before[$mark->student_id] = $mark->status;
            }
        }

        $changes = [];

        foreach ($roster as $row) {
            $now = $marks[$row->ulid];
            $was = $before[$row->studentKey] ?? null;

            if ($was !== $now) {
                $changes[$row->ulid] = ['from' => $was, 'to' => $now];
            }
        }

        if ($existing !== null && $changes === []) {
            throw AttendanceException::nothingChanged();
        }

        $reason = $reason === null ? null : trim($reason);

        if ($rights->needsReason && ($reason === null || mb_strlen($reason) < 5)) {
            throw AttendanceException::reasonRequired();
        }

        $reason = $rights->needsReason ? mb_substr((string) $reason, 0, 200) : null;
        $savedBy = $this->staff->forIdentity($actor);

        $register = $this->transaction(function () use ($existing, $section, $date, $roster, $marks, $changes, $reason, $savedBy) {
            $now = now();

            if ($existing === null) {
                $register = AttendanceRegister::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'section_id' => $section->sectionKey,
                    'attendance_on' => $date,
                    'saved_by_staff_id' => $savedBy?->staffKey,
                    'first_saved_at' => $now,
                    'last_saved_at' => $now,
                    'last_reason' => $reason,
                ]);
            } else {
                $existing->update([
                    'saved_by_staff_id' => $savedBy?->staffKey,
                    'last_saved_at' => $now,
                    'last_reason' => $reason,
                ]);
                $register = $existing;
            }

            foreach ($roster as $row) {
                AttendanceMark::query()->updateOrCreate(
                    ['attendance_register_id' => $register->id, 'student_id' => $row->studentKey],
                    ['status' => $marks[$row->ulid]],
                );
            }

            if ($existing === null) {
                $this->audit(new AuditEntry('attendance.register_saved', 'attendance_register', $register->ulid, null, [
                    'section' => $section->ulid,
                    'date' => $date,
                    ...$this->counts($marks),
                    'reason' => $reason,
                ]));
            } else {
                $this->audit(new AuditEntry('attendance.register_corrected', 'attendance_register', $register->ulid, [
                    'marks' => array_map(fn (array $change) => $change['from']?->value, $changes),
                ], [
                    'section' => $section->ulid,
                    'date' => $date,
                    'marks' => array_map(fn (array $change) => $change['to']->value, $changes),
                    'reason' => $reason,
                ]));
            }

            return $register;
        });

        $this->notifier->notify(
            $section->ulid,
            $date,
            array_keys(array_filter($changes, fn (array $change) => $change['to'] === AttendanceStatus::Absent)),
            array_keys(array_filter($changes, fn (array $change) => $change['from'] === AttendanceStatus::Absent && $change['to'] !== AttendanceStatus::Absent)),
        );

        return $register;
    }

    /**
     * @param  array<string, AttendanceStatus>  $marks
     * @return array{present: int, absent: int, late: int}
     */
    private function counts(array $marks): array
    {
        $counts = ['present' => 0, 'absent' => 0, 'late' => 0];

        foreach ($marks as $status) {
            $counts[$status->value]++;
        }

        return $counts;
    }
}
