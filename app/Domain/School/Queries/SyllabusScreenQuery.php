<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SyllabusChoiceData;
use App\Domain\School\Data\SyllabusClassData;
use App\Domain\School\Data\SyllabusDetailData;
use App\Domain\School\Data\SyllabusPair;
use App\Domain\School\Data\SyllabusProgressData;
use App\Domain\School\Data\SyllabusScreenData;
use App\Domain\School\Data\SyllabusStudentNoteData;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\SectionSubjectProgress;
use App\Domain\School\Models\StudentSubjectNote;
use App\Domain\School\Models\SubjectSyllabus;
use App\Domain\School\SyllabusAccess;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * The teacher's Subjects screen. Everything it offers comes from
 * SyllabusAccess, so a person only ever reads what they may also change.
 */
class SyllabusScreenQuery
{
    public function __construct(
        private readonly SyllabusAccess $access,
        private readonly StaffRefQuery $staff,
        private readonly RosterQuery $roster,
    ) {}

    public function get(Identity $identity, ?string $sectionUlid, ?string $subjectUlid): SyllabusScreenData
    {
        $pairs = $this->access->pairs($identity);

        $selected = null;

        foreach ($pairs as $pair) {
            if ($pair->section->ulid === $sectionUlid && $pair->subjectUlid === $subjectUlid) {
                $selected = $pair;
            }
        }

        $selected ??= $pairs[0] ?? null;

        $yearName = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('academic_years')->where('is_current', true)->value('name');

        return new SyllabusScreenData(
            year_name: is_string($yearName) ? $yearName : '',
            choices: array_map(fn (SyllabusPair $pair) => new SyllabusChoiceData(
                section_ulid: $pair->section->ulid,
                subject_ulid: $pair->subjectUlid,
                section_name: $pair->section->label(),
                subject_name: $pair->subjectName,
                campus_name: $pair->section->campusName,
                via_class_teacher: ! $pair->isChosenTeacher && $pair->isClassTeacher,
            ), $pairs),
            selected: $selected === null ? null : $this->detail($identity, $selected, $pairs),
        );
    }

    /**
     * @param  list<SyllabusPair>  $pairs
     */
    private function detail(Identity $identity, SyllabusPair $pair, array $pairs): SyllabusDetailData
    {
        $section = $pair->section;
        $class = SchoolClass::query()->where('ulid', $section->classUlid)->firstOrFail();
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $sectionCount = $connection->table('sections')
            ->where('academic_year_id', $section->academicYearKey)
            ->where('school_class_id', $class->id)
            ->where('status', 'active')
            ->count();

        $progress = SectionSubjectProgress::query()
            ->where('section_id', $section->sectionKey)
            ->where('subject_id', $pair->subjectKey)
            ->first();

        $syllabus = SubjectSyllabus::query()
            ->where('academic_year_id', $section->academicYearKey)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $pair->subjectKey)
            ->first();

        $copiedFrom = null;
        $source = $syllabus;

        if ($syllabus === null) {
            $previous = $connection->table('subject_syllabi as s')
                ->join('academic_years as y', 'y.id', '=', 's.academic_year_id')
                ->where('s.school_class_id', $class->id)
                ->where('s.subject_id', $pair->subjectKey)
                ->where('s.academic_year_id', '!=', $section->academicYearKey)
                ->orderByDesc('y.starts_on')
                ->first(['s.id', 'y.name as year_name']);

            if ($previous !== null) {
                $source = SubjectSyllabus::query()->find((int) $previous->id);
                $copiedFrom = (string) $previous->year_name;
            }
        }

        $staffNames = $this->staff->namesByKey(array_values(array_filter([$syllabus?->updated_by_staff_id, $progress?->updated_by_staff_id])));

        $notes = StudentSubjectNote::query()
            ->where('academic_year_id', $section->academicYearKey)
            ->where('subject_id', $pair->subjectKey)
            ->pluck('note', 'student_id')
            ->all();

        return new SyllabusDetailData(
            section_ulid: $section->ulid,
            subject_ulid: $pair->subjectUlid,
            section_name: $section->label(),
            class_name: $section->className,
            subject_name: $pair->subjectName,
            section_count: $sectionCount,
            can_edit_class: $this->access->mayEditClass($identity, $pair, $pairs),
            class_syllabus: new SyllabusClassData(
                book: $source?->book,
                chapters: $source->chapters ?? [],
                assessment: $source?->assessment,
                is_saved: $syllabus !== null,
                copied_from: $copiedFrom,
                updated_on: $this->schoolDay($syllabus?->updated_at),
                updated_by: $syllabus?->updated_by_staff_id === null ? null : ($staffNames[$syllabus->updated_by_staff_id] ?? null),
            ),
            progress: new SyllabusProgressData(
                done_chapters: $progress->done_chapters ?? [],
                current_chapter: $progress?->current_chapter,
                periods_per_week: $progress?->periods_per_week,
                note: (string) ($progress->note ?? ''),
                updated_on: $this->schoolDay($progress?->updated_at),
                updated_by: $progress?->updated_by_staff_id === null ? null : ($staffNames[$progress->updated_by_staff_id] ?? null),
            ),
            students: array_map(fn ($row) => new SyllabusStudentNoteData(
                ulid: $row->ulid,
                roll_number: $row->rollNumber,
                name: $row->name,
                note: (string) ($notes[$row->studentKey] ?? ''),
            ), $this->roster->for($section->sectionKey, CalendarDate::today())),
        );
    }

    /**
     * The calendar day an instant falls on in the school's time zone.
     */
    private function schoolDay(?DateTimeInterface $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        return CarbonImmutable::instance($instant)->setTimezone((string) config('shiksha.timezone', 'Asia/Kolkata'))->toDateString();
    }
}
