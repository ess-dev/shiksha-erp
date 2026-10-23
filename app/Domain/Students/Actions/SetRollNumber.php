<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Student;
use App\Domain\Students\StudentGuard;

class SetRollNumber extends Action
{
    public function __construct(private readonly StudentGuard $guard) {}

    /**
     * Sets a custom roll number for a current student. Unique within the
     * section; needs `students.manage` over the student.
     */
    public function handle(Identity $actor, Student $student, int $rollNumber): void
    {
        $this->guard->assertMayOn($actor, 'students.manage', $student);

        $this->transaction(function () use ($student, $rollNumber) {
            $current = Enrollment::query()->where('student_id', $student->id)->where('is_current', true)->first()
                ?? throw StudentException::notCurrentStudent();

            if ($rollNumber < 1 || $rollNumber > 9999) {
                throw new StudentException('A roll number is a whole number from 1 to 9999.');
            }

            $taken = Enrollment::query()
                ->where('section_id', $current->section_id)
                ->where('is_current', true)
                ->where('roll_number', $rollNumber)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($taken) {
                throw StudentException::rollTaken($rollNumber);
            }

            $before = $current->roll_number;
            $current->update(['roll_number' => $rollNumber]);

            $this->audit(new AuditEntry('students.roll_number_set', 'student', $student->ulid, ['roll_number' => $before], ['roll_number' => $rollNumber]));
        });
    }
}
