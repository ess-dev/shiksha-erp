<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\MarksNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Exceptions\MarksException;
use App\Domain\Exams\MarksAccess;
use App\Domain\Exams\MarkValue;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamMark;
use App\Domain\Exams\Models\ExamMarkSheet;
use App\Domain\Exams\Queries\GradeScaleQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class SaveMarks extends Action
{
    public function __construct(
        private readonly MarksAccess $access,
        private readonly StaffRefQuery $staff,
        private readonly RosterQuery $roster,
        private readonly GradeScaleQuery $scales,
        private readonly MarksNotifier $notifier,
    ) {}

    /**
     * Saves one subject's marks for a section as a draft and, with `$publish`,
     * shows them to parents. `$marks` maps a student's ulid to what was typed
     * (blank, "AB" or 0 to the maximum in halves); students left out keep
     * their marks. Only students on the section's roster today may be named.
     * Publishing needs a value for every student on the roster, and freezes
     * the school's grading scale on the exam if this is its first publish.
     * Only the subject's teacher or someone holding `marks.reopen` over the
     * section may do this, while marking is open and the marks are not
     * published. Returns how many students' marks changed.
     *
     * @param  array<string, string|null>  $marks
     */
    public function handle(Identity $actor, Exam $exam, string $sectionUlid, string $subjectUlid, array $marks, bool $publish = false): int
    {
        [$section, $subject] = $this->access->pairOrFail($actor, $exam, $sectionUlid, $subjectUlid);

        if (! $subject->mayEnter) {
            throw MarksException::notAllowed();
        }

        $this->assertOpen($exam, $section->section->sectionKey, $subject->subjectKey);

        $students = [];

        foreach ($this->roster->for($section->section->sectionKey, CalendarDate::today()) as $row) {
            $students[$row->ulid] = $row->studentKey;
        }

        $values = [];
        $invalid = 0;

        foreach ($marks as $studentUlid => $text) {
            if (! isset($students[$studentUlid])) {
                throw MarksException::notFound();
            }

            $value = MarkValue::parse($text, $exam->max_marks);

            if ($value === null) {
                $invalid++;

                continue;
            }

            $values[$students[$studentUlid]] = $value;
        }

        if ($invalid > 0) {
            throw MarksException::invalid($invalid, $exam->max_marks);
        }

        /** @var array<int, ExamMark> $existing */
        $existing = ExamMark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->subjectKey)
            ->whereIn('student_id', array_values($students))
            ->get()
            ->keyBy('student_id')
            ->all();

        if ($publish) {
            if ($students === []) {
                throw MarksException::nothingToPublish();
            }

            $missing = 0;

            foreach ($students as $studentKey) {
                $final = $values[$studentKey] ?? (isset($existing[$studentKey]) ? $existing[$studentKey]->value() : MarkValue::blank());

                if ($final->isBlank) {
                    $missing++;
                }
            }

            if ($missing > 0) {
                throw MarksException::incomplete($missing);
            }
        }

        $ulidsByKey = array_flip($students);
        $staffKey = $this->staff->forIdentity($actor)?->staffKey;

        $changed = $this->transaction(function () use ($exam, $section, $subject, $values, $existing, $ulidsByKey, $staffKey, $publish) {
            $exam = Exam::query()->whereKey($exam->id)->lockForUpdate()->firstOrFail();

            if ($exam->status !== ExamStatus::Marking) {
                throw MarksException::markingClosed();
            }

            $sheet = ExamMarkSheet::query()->firstOrCreate([
                'exam_id' => $exam->id,
                'section_id' => $section->section->sectionKey,
                'subject_id' => $subject->subjectKey,
            ]);

            $sheet = ExamMarkSheet::query()->whereKey($sheet->id)->lockForUpdate()->firstOrFail();

            if ($sheet->isPublished()) {
                throw MarksException::alreadyPublished();
            }

            $before = [];
            $after = [];

            foreach ($values as $studentKey => $value) {
                $old = isset($existing[$studentKey]) ? $existing[$studentKey]->value() : MarkValue::blank();

                if ($old->equals($value)) {
                    continue;
                }

                $before[$ulidsByKey[$studentKey]] = $old->text();
                $after[$ulidsByKey[$studentKey]] = $value->text();
                $where = ['exam_id' => $exam->id, 'subject_id' => $subject->subjectKey, 'student_id' => $studentKey];

                if ($value->isBlank) {
                    ExamMark::query()->where($where)->delete();
                } else {
                    ExamMark::query()->updateOrCreate($where, [
                        'section_id' => $section->section->sectionKey,
                        'half_marks' => $value->halfMarks,
                        'is_absent' => $value->isAbsent,
                        'entered_by_staff_id' => $staffKey,
                    ]);
                }
            }

            $where = ['section' => $section->section->ulid, 'subject' => $subject->ulid];

            if ($after !== [] || ! $publish) {
                $this->audit(new AuditEntry('marks.saved', 'exam', $exam->ulid, [...$where, 'marks' => $before], [...$where, 'marks' => $after]));
            }

            if ($publish) {
                if ($exam->grade_scale === null) {
                    $exam->update(['grade_scale' => $this->scales->current()->toRows()]);
                }

                $sheet->update(['published_at' => now(), 'published_by_staff_id' => $staffKey]);

                $this->audit(new AuditEntry('marks.published', 'exam', $exam->ulid, null, $where));
            }

            return count($after);
        });

        if ($publish) {
            $this->notifier->marksPublished($exam->ulid, $section->section->ulid, $subject->ulid);
        }

        return $changed;
    }

    private function assertOpen(Exam $exam, int $sectionKey, int $subjectKey): void
    {
        if ($exam->status !== ExamStatus::Marking) {
            throw $exam->status === ExamStatus::Published ? MarksException::alreadyPublished() : MarksException::markingClosed();
        }

        $published = ExamMarkSheet::query()
            ->where('exam_id', $exam->id)
            ->where('section_id', $sectionKey)
            ->where('subject_id', $subjectKey)
            ->whereNotNull('published_at')
            ->exists();

        if ($published) {
            throw MarksException::alreadyPublished();
        }
    }
}
