<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Enums\EnrollmentResult;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Student;
use App\Domain\Students\StudentGuard;
use App\Domain\Students\StudentVisibility;

class ChangeSection extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly StudentVisibility $visibility,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    /**
     * Moves a current student to another section of the same class. Needs
     * `students.manage` over both the section they leave and the one they join
     * (a move outside the person's own reach would put the student where they
     * can no longer see them). The old enrolment is closed, so earlier
     * attendance and marks stay with the old section; the new roll number is
     * the next free one.
     */
    public function handle(Identity $actor, Student $student, string $sectionUlid, ?string $reason = null): Student
    {
        $this->guard->assertMayOn($actor, 'students.manage', $student);

        $target = $this->sections->find($sectionUlid) ?? throw StudentException::sectionNotFound();

        $this->guard->assertMayAt($actor, 'students.manage', $target->scopeTarget());

        return $this->transaction(function () use ($student, $target, $reason) {
            $student->refresh();

            if (! $student->status->isCurrent()) {
                throw StudentException::notCurrentStudent();
            }

            $current = Enrollment::query()->where('student_id', $student->id)->where('is_current', true)->first()
                ?? throw StudentException::notCurrentStudent();

            $from = $this->visibility->targets([$student->id])[$student->id] ?? null;

            if ($from === null || $target->status !== 'active') {
                throw StudentException::sectionNotFound();
            }

            if ($from->sectionUlid === $target->ulid) {
                throw StudentException::alreadyInSection();
            }

            if ($from->classLevel !== $target->classLevel) {
                throw StudentException::wrongClass();
            }

            if ($target->isFull()) {
                throw StudentException::sectionFull($target->label());
            }

            $today = CalendarDate::today();
            $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

            $current->update([
                'is_current' => false,
                'ended_on' => $today,
                'result' => EnrollmentResult::Moved,
                'note' => "Moved to {$target->label()}",
            ]);

            $roll = (int) Enrollment::query()->where('section_id', $target->sectionKey)->where('is_current', true)->max('roll_number') + 1;

            Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $target->academicYearKey,
                'section_id' => $target->sectionKey,
                'roll_number' => $roll,
                'started_on' => $today,
                'result' => EnrollmentResult::InProgress,
                'note' => $reason,
                'is_current' => true,
            ]);

            $this->audit(new AuditEntry('students.section_changed', 'student', $student->ulid, [
                'section' => $from->sectionUlid,
            ], [
                'section' => $target->ulid,
                'roll_number' => $roll,
                'reason' => $reason,
            ]));

            return $student;
        });
    }
}
