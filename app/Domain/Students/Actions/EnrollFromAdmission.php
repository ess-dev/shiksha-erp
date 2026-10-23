<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\HousesQuery;
use App\Domain\Shared\Action;
use App\Domain\Students\Data\AdmissionEnrolment;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\DocumentKind;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentDocument;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnrollFromAdmission extends Action
{
    public function __construct(
        private readonly CreateStudent $createStudent,
        private readonly HousesQuery $houses,
    ) {}

    /**
     * Creates the student, the guardian and the enrolment for an accepted
     * applicant (through CreateStudent, so the admission number, roll number,
     * one-phone-one-guardian rule and section checks all apply), then copies
     * the scans collected during admission onto the student's record as
     * verified documents. Needs `admissions.enroll` over the section.
     */
    public function handle(Identity $actor, AdmissionEnrolment $input): Student
    {
        return $this->transaction(function () use ($actor, $input) {
            $student = $this->createStudent->handle($actor, new NewStudent(
                firstName: $input->firstName,
                lastName: $input->lastName,
                gender: Gender::from($input->gender),
                bornOn: $input->bornOn,
                bloodGroup: BloodGroup::from($input->bloodGroup),
                houseKey: $input->houseUlid === null ? null : $this->houses->activeKeyByUlid($input->houseUlid),
                address: $input->address,
                sectionUlid: $input->sectionUlid,
                guardians: [new NewGuardian(
                    name: $input->guardianName,
                    relation: $input->guardianRelation,
                    phone: $input->guardianPhone,
                    email: $input->guardianEmail,
                    isPrimary: true,
                    hasPortalAccess: true,
                )],
            ), 'admissions.enroll', 'admission');

            $tenant = Tenant::current();
            $copied = [];

            foreach ($input->files as $file) {
                if ($tenant === null || ! Storage::disk('tenant')->exists($file->path)) {
                    continue;
                }

                $ulid = strtolower((string) Str::ulid());
                $path = "tenants/{$tenant->ulid}/students/{$student->ulid}/{$ulid}.".pathinfo($file->path, PATHINFO_EXTENSION);

                Storage::disk('tenant')->copy($file->path, $path);

                StudentDocument::query()->create([
                    'ulid' => $ulid,
                    'student_id' => $student->id,
                    'kind' => DocumentKind::from($file->kind),
                    'original_name' => $file->originalName,
                    'path' => $path,
                    'mime_type' => $file->mimeType,
                    'size_bytes' => $file->sizeBytes,
                    'source' => 'admission',
                    'uploaded_by_identity_ulid' => $file->uploadedByIdentityUlid,
                    'verified_at' => now(),
                    'verified_by_identity_ulid' => $actor->ulid,
                ]);

                $copied[] = $file->kind;
            }

            $this->audit(new AuditEntry('students.admission_documents_moved', 'student', $student->ulid, null, [
                'documents' => $copied,
            ]));

            return $student;
        });
    }
}
