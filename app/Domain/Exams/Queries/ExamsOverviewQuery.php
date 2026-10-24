<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Exams\Data\ExamCampusOptionData;
use App\Domain\Exams\Data\ExamClassOptionData;
use App\Domain\Exams\Data\ExamClassSubjectsData;
use App\Domain\Exams\Data\ExamClosedDayData;
use App\Domain\Exams\Data\ExamDateSheetRowData;
use App\Domain\Exams\Data\ExamFormOptionsData;
use App\Domain\Exams\Data\ExamRowData;
use App\Domain\Exams\Data\ExamsOverviewData;
use App\Domain\Exams\Data\ExamSubjectOptionData;
use App\Domain\Exams\Data\ExamTypeOptionData;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\ExamReach;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\HolidayData;
use App\Domain\School\Queries\AttendanceCalendarQuery;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The Exams and tests screen. Lists this academic year's exams that touch a
 * section in the person's `exams.schedule` scope; every flag on a row comes
 * from that scope (change: it covers everything the exam applies to) and from
 * `results.publish` (publish).
 */
class ExamsOverviewQuery
{
    public function __construct(
        private readonly ExamReach $reach,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly AttendanceCalendarQuery $calendar,
        private readonly SchoolDayQuery $days,
        private readonly ExamMarksProgressQuery $progress,
    ) {}

    public function get(Identity $actor): ExamsOverviewData
    {
        $schedule = $this->reach->scopeFor($actor, 'exams.schedule');
        $publish = $this->reach->scopeFor($actor, 'results.publish');
        $year = $this->sections->currentYearRef();

        $exams = $year === null ? collect() : Exam::query()->where('academic_year_id', $year['key'])->orderBy('starts_on')->orderBy('id')->get();
        $ids = array_values(array_map(intval(...), $exams->pluck('id')->all()));

        $classIdsByExam = $this->pivot('exam_school_class', 'school_class_id', $ids);
        $campusIdsByExam = $this->pivot('campus_exam', 'campus_id', $ids);
        $sheets = $this->sheets($ids);
        $creators = $this->staff->namesByKey(array_values($exams->pluck('created_by_staff_id')->filter()->map(fn ($id) => (int) $id)->all()));

        $allClassIds = array_column($this->reach->classes(), 'id');
        $allCampusIds = array_column($this->reach->campuses(), 'id');
        $classNames = array_column($this->reach->classes(), 'name', 'id');
        $classUlids = array_column($this->reach->classes(), 'ulid', 'id');
        $campusUlids = array_column($this->reach->campuses(), 'ulid', 'id');
        $campusNames = array_column($this->reach->campuses(), 'name', 'id');

        $classIdsOf = $campusIdsOf = [];

        foreach ($exams as $exam) {
            $classIdsOf[$exam->id] = $exam->applies_to_all_classes ? $allClassIds : ($classIdsByExam[$exam->id] ?? []);
            $campusIdsOf[$exam->id] = $exam->applies_to_all_campuses ? $allCampusIds : ($campusIdsByExam[$exam->id] ?? []);
        }

        $progress = $this->progress->forExams($exams, $classIdsOf, $campusIdsOf);
        $withMarks = $this->progress->withMarks($ids);

        $rows = [];

        foreach ($exams as $exam) {
            $classIds = $classIdsOf[$exam->id];
            $campusIds = $campusIdsOf[$exam->id];
            $hasMarks = isset($withMarks[$exam->id]);

            if (! $this->reach->touches($schedule, $classIds, $campusIds)) {
                continue;
            }

            $canChange = $this->reach->covers($schedule, $classIds, $campusIds);

            $rows[] = new ExamRowData(
                ulid: $exam->ulid,
                name: $exam->name,
                type: $exam->type,
                type_label: $exam->type->label(),
                created_by: $exam->created_by_staff_id === null ? null : ($creators[$exam->created_by_staff_id] ?? null),
                starts_on: $exam->starts_on,
                ends_on: $exam->ends_on,
                status: $exam->status,
                max_marks: $exam->max_marks,
                max_marks_locked: $hasMarks,
                instructions: $exam->instructions,
                show_on_calendar: $exam->show_on_calendar,
                all_classes: $exam->applies_to_all_classes,
                class_ulids: $exam->applies_to_all_classes ? [] : array_map(fn (int $id) => (string) $classUlids[$id], $classIds),
                all_campuses: $exam->applies_to_all_campuses,
                campus_ulids: $exam->applies_to_all_campuses ? [] : array_map(fn (int $id) => (string) $campusUlids[$id], $campusIds),
                classes_label: $this->classesLabel($exam, $classIds, $campusIds, $classNames, $campusNames),
                date_sheet: $sheets[$exam->id] ?? [],
                can_change: $canChange,
                can_publish: $canChange && $this->reach->covers($publish, $classIds, $campusIds),
                can_delete: $canChange && $exam->status !== ExamStatus::Published && ! $hasMarks,
                marks_progress: $progress[$exam->id] ?? null,
            );
        }

        $count = fn (ExamStatus $status): int => count(array_filter($rows, fn (ExamRowData $row) => $row->status === $status));

        return new ExamsOverviewData(
            scheduled_count: $count(ExamStatus::Scheduled),
            marking_count: $count(ExamStatus::Marking),
            published_count: $count(ExamStatus::Published),
            cancelled_count: $count(ExamStatus::Cancelled),
            exams: $rows,
            form: $this->formOptions($schedule),
        );
    }

    private function formOptions(ScopeSet $schedule): ExamFormOptionsData
    {
        $classes = $this->reach->classes();
        $campuses = $this->reach->campuses();
        $allClassIds = array_column($classes, 'id');
        $allCampusIds = array_column($campuses, 'id');

        // A class or campus is offered when the scope covers it with at least one counterpart.
        $classes = array_values(array_filter($classes, fn (array $class) => $this->coversSome($schedule, $class['id'], null)));
        $campuses = array_values(array_filter($campuses, fn (array $campus) => $this->coversSome($schedule, null, $campus['id'])));

        $today = CalendarDate::today();
        $calendar = $this->calendar->get();

        return new ExamFormOptionsData(
            types: array_map(fn (ExamType $type) => new ExamTypeOptionData($type, $type->label(), $type->defaultMaxMarks()), ExamType::cases()),
            classes: array_map(fn (array $class) => new ExamClassOptionData($class['ulid'], $class['name']), $classes),
            campuses: array_map(fn (array $campus) => new ExamCampusOptionData($campus['ulid'], $campus['name']), $campuses),
            may_choose_all_classes: $this->reach->covers($schedule, $allClassIds, $allCampusIds),
            may_choose_all_campuses: $this->reach->covers($schedule, $allClassIds, $allCampusIds),
            subjects_by_class: $this->subjectsByClass(),
            working_days: $calendar->working_days,
            holidays: array_values(array_map(
                fn (HolidayData $holiday) => new ExamClosedDayData($holiday->holiday_on, $holiday->name),
                array_filter($calendar->holidays, fn (HolidayData $holiday) => $holiday->holiday_on >= $today),
            )),
            today: $today,
            default_starts_on: $this->nextSchoolDay((new DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d')),
        );
    }

    /**
     * Whether the scope covers the class (at some campus) or the campus (with some class).
     */
    private function coversSome(ScopeSet $schedule, ?int $classId, ?int $campusId): bool
    {
        $classIds = $classId === null ? array_column($this->reach->classes(), 'id') : [$classId];
        $campusIds = $campusId === null ? array_column($this->reach->campuses(), 'id') : [$campusId];

        foreach ($classIds as $one) {
            foreach ($campusIds as $other) {
                if ($this->reach->covers($schedule, [$one], [$other])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function nextSchoolDay(string $from): string
    {
        $date = new DateTimeImmutable($from);

        for ($i = 0; $i < 60; $i++) {
            if ($this->days->check($date->format('Y-m-d'))->isSchoolDay) {
                break;
            }

            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return list<ExamClassSubjectsData>
     */
    private function subjectsByClass(): array
    {
        $links = DB::connection('tenant')->table('school_class_subject')
            ->join('subjects', 'subjects.id', '=', 'school_class_subject.subject_id')
            ->where('subjects.status', 'active')
            ->where('subjects.type', '!=', 'co_scholastic')
            ->orderBy('subjects.id')
            ->get(['school_class_subject.school_class_id as class_id', 'subjects.ulid', 'subjects.name']);

        $byClass = [];

        foreach ($links as $link) {
            $byClass[(int) $link->class_id][] = new ExamSubjectOptionData((string) $link->ulid, (string) $link->name);
        }

        $rows = [];

        foreach ($this->reach->classes() as $class) {
            $rows[] = new ExamClassSubjectsData($class['ulid'], $byClass[$class['id']] ?? []);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $examIds
     * @return array<int, list<int>>
     */
    private function pivot(string $table, string $column, array $examIds): array
    {
        if ($examIds === []) {
            return [];
        }

        $map = [];

        foreach (DB::connection('tenant')->table($table)->whereIn('exam_id', $examIds)->get(['exam_id', $column]) as $row) {
            $map[(int) $row->exam_id][] = (int) $row->{$column};
        }

        return $map;
    }

    /**
     * @param  list<int>  $examIds
     * @return array<int, list<ExamDateSheetRowData>>
     */
    private function sheets(array $examIds): array
    {
        if ($examIds === []) {
            return [];
        }

        $map = [];

        $rows = DB::connection('tenant')->table('exam_date_sheet_entries')
            ->join('subjects', 'subjects.id', '=', 'exam_date_sheet_entries.subject_id')
            ->whereIn('exam_date_sheet_entries.exam_id', $examIds)
            ->orderBy('exam_date_sheet_entries.held_on')
            ->orderBy('exam_date_sheet_entries.begins_at')
            ->orderBy('subjects.id')
            ->get(['exam_date_sheet_entries.exam_id', 'subjects.ulid', 'subjects.name', 'exam_date_sheet_entries.held_on', 'exam_date_sheet_entries.begins_at']);

        foreach ($rows as $row) {
            $map[(int) $row->exam_id][] = new ExamDateSheetRowData(
                (string) $row->ulid,
                (string) $row->name,
                (string) $row->held_on,
                $row->begins_at === null ? null : substr((string) $row->begins_at, 0, 5),
            );
        }

        return $map;
    }

    /**
     * "All classes", "Class 6, 7, 8" or the names joined; the campuses follow when the exam is not at every one.
     *
     * @param  list<int>  $classIds
     * @param  list<int>  $campusIds
     * @param  array<int, string>  $classNames
     * @param  array<int, string>  $campusNames
     */
    private function classesLabel(Exam $exam, array $classIds, array $campusIds, array $classNames, array $campusNames): string
    {
        $label = 'All classes';

        if (! $exam->applies_to_all_classes) {
            $names = array_values(array_map(fn (int $id) => (string) $classNames[$id], array_filter(array_keys($classNames), fn (int $id) => in_array($id, $classIds, true))));
            $allNumbered = $names !== [] && count(array_filter($names, fn (string $name) => preg_match('/^Class \d+$/', $name) === 1)) === count($names);
            $label = $allNumbered
                ? 'Class '.implode(', ', array_map(fn (string $name) => substr($name, 6), $names))
                : implode(', ', $names);
        }

        if (! $exam->applies_to_all_campuses) {
            $label .= ' · '.implode(', ', array_map(fn (int $id) => (string) $campusNames[$id], array_values(array_filter(array_keys($campusNames), fn (int $id) => in_array($id, $campusIds, true)))));
        }

        return $label;
    }
}
