<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Exams\Data\MarkRowData;
use App\Domain\Exams\Data\MarksExamOptionData;
use App\Domain\Exams\Data\MarksScreenData;
use App\Domain\Exams\Data\MarksSection;
use App\Domain\Exams\Data\MarksSectionOptionData;
use App\Domain\Exams\Data\MarksSheetData;
use App\Domain\Exams\Data\MarksSubject;
use App\Domain\Exams\Data\MarksSubjectOptionData;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\MarkSheetState;
use App\Domain\Exams\MarksAccess;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamMark;
use App\Domain\Exams\Models\ExamMarkSheet;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The marks entry screen. Exams with marking open or results published that
 * are for a section the person reaches; for the chosen exam, those sections;
 * for the chosen section, the subjects the person sees (MarksAccess). A
 * requested exam, section or subject the person cannot open falls back to
 * the first sensible one: the exam with marking open, a section and subject
 * they teach.
 */
class MarksScreenQuery
{
    public function __construct(
        private readonly MarksAccess $access,
        private readonly SectionDirectoryQuery $directory,
        private readonly RosterQuery $roster,
        private readonly StaffRefQuery $staff,
        private readonly GradeScaleQuery $scales,
        private readonly PermissionScopesQuery $scopes,
    ) {}

    public function get(Identity $actor, ?string $examUlid, ?string $sectionUlid, ?string $subjectUlid): MarksScreenData
    {
        $sections = $this->access->sections($actor);
        $year = $this->directory->currentYearRef();

        $exams = $year === null || $sections === [] ? [] : array_values(Exam::query()
            ->where('academic_year_id', $year['key'])
            ->whereIn('status', [ExamStatus::Marking->value, ExamStatus::Published->value])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (Exam $exam) => $this->sectionsFor($exam, $sections) !== [])
            ->all());

        $exam = $this->pick($exams, fn (Exam $e) => $e->ulid === $examUlid)
            ?? $this->pick(array_reverse($exams), fn (Exam $e) => $e->status === ExamStatus::Marking)
            ?? ($exams[0] ?? null);

        $examSections = $exam === null ? [] : $this->sectionsFor($exam, $sections);

        $section = $this->pick($examSections, fn (MarksSection $s) => $s->section->ulid === $sectionUlid)
            ?? $this->pick($examSections, fn (MarksSection $s) => $s->hasOwnSubject())
            ?? ($examSections[0] ?? null);

        $subjects = $section === null ? [] : $section->subjects;

        $subject = $this->pick($subjects, fn (MarksSubject $s) => $s->ulid === $subjectUlid)
            ?? $this->pick($subjects, fn (MarksSubject $s) => $s->isMine)
            ?? ($subjects[0] ?? null);

        $scale = $exam === null ? $this->scales->current() : $this->scales->forExam($exam);

        return new MarksScreenData(
            exams: array_map(fn (Exam $e) => new MarksExamOptionData($e->ulid, $e->name, $e->max_marks, $e->status), $exams),
            sections: array_map(fn (MarksSection $s) => new MarksSectionOptionData($s->section->ulid, $s->section->label(), $s->section->campusName), $examSections),
            subjects: array_map(fn (MarksSubject $s) => new MarksSubjectOptionData($s->ulid, $s->name, $s->isMine, $s->mayEnter), $subjects),
            sheet: $exam === null || $section === null || $subject === null ? null : $this->sheet($exam, $section, $subject),
            scale: $scale->data(),
            pass_percent: $scale->passPercent(),
            can_change_scale: $this->scopes->get($actor, 'settings.manage')->allows(),
        );
    }

    private function sheet(Exam $exam, MarksSection $section, MarksSubject $subject): MarksSheetData
    {
        $sheet = ExamMarkSheet::query()
            ->where('exam_id', $exam->id)
            ->where('section_id', $section->section->sectionKey)
            ->where('subject_id', $subject->subjectKey)
            ->first();

        $published = $exam->status === ExamStatus::Published || ($sheet?->isPublished() ?? false);

        $state = match (true) {
            $published => MarkSheetState::Published,
            $exam->status !== ExamStatus::Marking => MarkSheetState::Closed,
            ! $subject->mayEnter => MarkSheetState::ViewOnly,
            default => MarkSheetState::Editable,
        };

        $roster = $this->roster->for($section->section->sectionKey, CalendarDate::today());

        /** @var array<int, ExamMark> $marks */
        $marks = ExamMark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->subjectKey)
            ->whereIn('student_id', array_map(fn ($row) => $row->studentKey, $roster))
            ->get()
            ->keyBy('student_id')
            ->all();

        $names = $this->staff->namesByKey(array_values(array_filter([
            $subject->teacherStaffKey,
            $sheet?->published_by_staff_id,
            $sheet?->reopened_by_staff_id,
        ])));

        return new MarksSheetData(
            exam_ulid: $exam->ulid,
            section_ulid: $section->section->ulid,
            subject_ulid: $subject->ulid,
            exam_name: $exam->name,
            exam_status: $exam->status,
            section_label: $section->section->label(),
            subject_name: $subject->name,
            max_marks: $exam->max_marks,
            state: $state,
            teacher_name: $subject->teacherStaffKey === null ? null : ($names[$subject->teacherStaffKey] ?? null),
            published_on: $this->schoolDay($sheet->published_at ?? ($exam->status === ExamStatus::Published ? $exam->published_at : null)),
            published_by: $sheet?->published_by_staff_id === null ? null : ($names[$sheet->published_by_staff_id] ?? null),
            reopened_by: $published || $sheet?->reopened_by_staff_id === null ? null : ($names[$sheet->reopened_by_staff_id] ?? null),
            reopen_reason: $published ? null : $sheet?->reopen_reason,
            can_reopen: $section->isOverseer && $exam->status === ExamStatus::Marking && ($sheet?->isPublished() ?? false),
            rows: array_map(fn ($row) => new MarkRowData(
                student_ulid: $row->ulid,
                roll_number: $row->rollNumber,
                name: $row->name,
                value: isset($marks[$row->studentKey]) ? $marks[$row->studentKey]->value()->text() : '',
            ), $roster),
        );
    }

    /**
     * @param  list<MarksSection>  $sections
     * @return list<MarksSection>
     */
    private function sectionsFor(Exam $exam, array $sections): array
    {
        return array_values(array_filter($sections, fn (MarksSection $section) => $this->access->appliesTo($exam, $section)));
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): bool  $matches
     * @return T|null
     */
    private function pick(array $items, callable $matches): mixed
    {
        foreach ($items as $item) {
            if ($matches($item)) {
                return $item;
            }
        }

        return null;
    }

    private function schoolDay(?DateTimeInterface $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        return CarbonImmutable::instance($instant)->setTimezone((string) config('shiksha.timezone', 'Asia/Kolkata'))->toDateString();
    }
}
