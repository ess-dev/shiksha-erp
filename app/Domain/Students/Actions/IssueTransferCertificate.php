<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Enums\EnrollmentResult;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Student;
use App\Domain\Students\NumberSequences;
use App\Domain\Students\StudentGuard;
use InvalidArgumentException;

class IssueTransferCertificate extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly NumberSequences $numbers,
        private readonly SectionDirectoryQuery $sections,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Issues a transfer certificate: the student becomes "transferred", their
     * current enrolment ends on the last day and they get the next TC number
     * (configurable prefix). Records are kept; nothing is deleted. Needs
     * `students.issue_tc` (critical) over the student. Cancelling future fee
     * installments waits for the Fees module.
     */
    public function handle(Identity $actor, Student $student, string $lastDay, TransferReason $reason): Student
    {
        $this->guard->assertMayOn($actor, 'students.issue_tc', $student);

        try {
            $lastDay = CalendarDate::parse($lastDay);
        } catch (InvalidArgumentException) {
            throw new StudentException('The last day is not a valid date.');
        }

        return $this->transaction(function () use ($student, $lastDay, $reason) {
            $student->refresh();

            if (! $student->status->isCurrent()) {
                throw StudentException::notCurrentStudent();
            }

            if ($lastDay < $student->admitted_on) {
                throw new StudentException('The last day cannot be before the admission date.');
            }

            $year = $this->sections->currentYearRef() ?? throw StudentException::noCurrentYear();
            $tcNumber = $this->numbers->formatted($this->settings->tcPrefix(), $year['starts_on'], 'tc');

            Enrollment::query()->where('student_id', $student->id)->where('is_current', true)->update([
                'is_current' => false,
                'ended_on' => $lastDay,
                'result' => EnrollmentResult::Left,
                'note' => 'Transfer certificate issued',
            ]);

            $student->update([
                'status' => StudentStatus::Transferred,
                'left_on' => $lastDay,
                'leave_reason' => $reason->label(),
                'tc_number' => $tcNumber,
            ]);

            $this->audit(new AuditEntry('students.transfer_certificate_issued', 'student', $student->ulid, [
                'status' => StudentStatus::Enrolled->value,
            ], [
                'status' => StudentStatus::Transferred->value,
                'tc_number' => $tcNumber,
                'left_on' => $lastDay,
                'reason' => $reason->value,
            ]));

            return $student;
        });
    }
}
