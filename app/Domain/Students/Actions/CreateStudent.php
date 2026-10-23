<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\EnrollmentResult;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\GuardianLinker;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Student;
use App\Domain\Students\NumberSequences;
use App\Domain\Students\StudentGuard;
use Illuminate\Support\Str;

class CreateStudent extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly GuardianLinker $guardians,
        private readonly NumberSequences $numbers,
        private readonly SectionDirectoryQuery $sections,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * Enrols a student in a section of the current academic year, with their
     * guardians. A guardian whose phone is already on file is linked, not
     * duplicated (see GuardianLinker). Needs the permission (default
     * `students.manage`; the import passes `students.import`) over the section.
     */
    public function handle(Identity $actor, NewStudent $input, string $permission = 'students.manage', string $source = 'manual'): Student
    {
        $section = $this->sections->find($input->sectionUlid) ?? throw StudentException::sectionNotFound();

        $this->guard->assertMayAt($actor, $permission, $section->scopeTarget());

        if ($input->guardians === []) {
            throw StudentException::noGuardian();
        }

        $this->guardians->assertDistinctPhones($input->guardians);

        return $this->transaction(function () use ($input, $section, $source) {
            $year = $this->sections->currentYearRef() ?? throw StudentException::noCurrentYear();

            if ($section->academicYearKey !== $year['key'] || $section->status !== 'active') {
                throw StudentException::sectionNotFound();
            }

            $admissionNumber = $input->admissionNumber !== null && trim($input->admissionNumber) !== ''
                ? trim($input->admissionNumber)
                : $this->numbers->formatted($this->settings->admissionPrefix(), $year['starts_on'], 'admission');

            if (Student::query()->whereRaw('lower(admission_number) = ?', [mb_strtolower($admissionNumber)])->exists()) {
                throw StudentException::admissionNumberTaken($admissionNumber);
            }

            $student = Student::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'admission_number' => $admissionNumber,
                'first_name' => trim($input->firstName),
                'last_name' => trim($input->lastName),
                'gender' => $input->gender,
                'born_on' => CalendarDate::parse($input->bornOn),
                'blood_group' => $input->bloodGroup,
                'house_id' => $input->houseKey,
                'address' => trim($input->address),
                'status' => StudentStatus::Enrolled,
                'admitted_on' => CalendarDate::parse($input->admittedOn ?? CalendarDate::today()),
            ]);

            $linkedGuardians = [];
            $newGuardians = [];
            $primaryIndex = 0;

            // Exactly one primary contact: the first one flagged, else the first listed.
            foreach ($input->guardians as $index => $guardianInput) {
                if ($guardianInput->isPrimary) {
                    $primaryIndex = $index;

                    break;
                }
            }

            foreach ($input->guardians as $index => $guardianInput) {
                $resolved = $this->guardians->resolve($guardianInput);
                $isPrimary = $index === $primaryIndex;

                $student->guardians()->attach($resolved['guardian']->id, [
                    'relation' => trim($guardianInput->relation) !== '' ? trim($guardianInput->relation) : 'Guardian',
                    'is_primary' => $isPrimary,
                    'has_portal_access' => $guardianInput->hasPortalAccess,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $resolved['existing'] ? $linkedGuardians[] = $resolved['guardian']->ulid : $newGuardians[] = $resolved['guardian']->ulid;
            }

            $roll = (int) Enrollment::query()->where('section_id', $section->sectionKey)->where('is_current', true)->max('roll_number') + 1;

            Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $section->academicYearKey,
                'section_id' => $section->sectionKey,
                'roll_number' => $roll,
                'started_on' => $student->admitted_on,
                'result' => EnrollmentResult::InProgress,
                'is_current' => true,
            ]);

            $this->audit(new AuditEntry('students.student_created', 'student', $student->ulid, null, [
                'admission_number' => $admissionNumber,
                'section' => $section->ulid,
                'roll_number' => $roll,
                'source' => $source,
                'new_guardians' => $newGuardians,
                'linked_guardians' => $linkedGuardians,
            ]));

            return $student;
        });
    }
}
