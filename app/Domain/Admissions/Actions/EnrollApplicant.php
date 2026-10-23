<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\ApplicationHistory;
use App\Domain\Admissions\Data\EnrolledApplicantData;
use App\Domain\Admissions\Enums\AdmissionDecision;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\EnrollFromAdmission;
use App\Domain\Students\Data\AdmissionEnrolment;
use App\Domain\Students\Data\AdmissionFile;
use Illuminate\Support\Facades\DB;

class EnrollApplicant extends Action
{
    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly SectionDirectoryQuery $sections,
        private readonly EnrollFromAdmission $enroll,
        private readonly ApplicationHistory $history,
    ) {}

    /**
     * Turns an accepted application into a student: the student record (with
     * admission and roll number), the parent as guardian with portal access,
     * and the collected scans on the student's record. The section must be of
     * the class applied for. Needs `admissions.enroll` over the application
     * and the section. Fees are not created yet (the Fees module is not built).
     */
    public function handle(Identity $actor, AdmissionApplication $application, string $sectionUlid): EnrolledApplicantData
    {
        $this->guard->assertMayOn($actor, 'admissions.enroll', $application);

        return $this->transaction(function () use ($actor, $application, $sectionUlid) {
            // Lock the row so a double click cannot enrol the same child twice.
            $application = AdmissionApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->isLocked()) {
                throw AdmissionException::closed();
            }

            if ($application->stage !== AdmissionStage::Decision || $application->decision !== AdmissionDecision::Accepted) {
                throw AdmissionException::notAccepted();
            }

            if (! $this->history->detailsComplete($application)) {
                throw AdmissionException::detailsIncomplete();
            }

            $section = $this->sections->find($sectionUlid);
            $classUlid = (string) DB::connection(config('multitenancy.tenant_database_connection_name'))
                ->table('school_classes')->where('id', $application->school_class_id)->value('ulid');

            if ($section === null || $section->classUlid !== $classUlid || $section->status !== 'active') {
                throw AdmissionException::sectionNotAvailable();
            }

            if ($section->isFull()) {
                throw AdmissionException::sectionFull($section->label());
            }

            $houseUlid = $application->house_id === null ? null : DB::connection(config('multitenancy.tenant_database_connection_name'))
                ->table('houses')->where('id', $application->house_id)->value('ulid');

            $files = [];

            foreach (AdmissionDocument::query()->where('admission_application_id', $application->id)->whereNotNull('path')->get() as $document) {
                $files[] = new AdmissionFile(
                    kind: $document->kind->value,
                    originalName: (string) $document->original_name,
                    path: (string) $document->path,
                    mimeType: (string) $document->mime_type,
                    sizeBytes: (int) $document->size_bytes,
                    uploadedByIdentityUlid: $document->uploaded_by_identity_ulid,
                );
            }

            $student = $this->enroll->handle($actor, new AdmissionEnrolment(
                firstName: $application->first_name,
                lastName: $application->last_name,
                gender: $application->gender,
                bornOn: (string) $application->born_on,
                bloodGroup: (string) $application->blood_group,
                houseUlid: $houseUlid === null ? null : (string) $houseUlid,
                address: (string) $application->address,
                sectionUlid: $section->ulid,
                guardianName: $application->parent_name,
                guardianRelation: $application->parent_relation,
                guardianPhone: $application->phone,
                guardianEmail: $application->email,
                files: $files,
            ));

            $application->update([
                'stage' => AdmissionStage::Enrolled,
                'student_id' => $student->id,
                'enrolled_on' => CalendarDate::today(),
            ]);

            $this->history->record($application, AdmissionStage::Enrolled, $actor);

            $this->audit(new AuditEntry('admissions.applicant_enrolled', 'admission_application', $application->ulid, null, [
                'student' => $student->ulid,
                'section' => $section->ulid,
            ]));

            $roll = (int) DB::connection(config('multitenancy.tenant_database_connection_name'))
                ->table('enrollments')->where('student_id', $student->id)->where('is_current', true)->value('roll_number');

            return new EnrolledApplicantData($student->ulid, $application->name(), $section->label(), $student->admission_number, $roll);
        });
    }
}
