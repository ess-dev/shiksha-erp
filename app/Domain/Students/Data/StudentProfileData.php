<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentProfileData extends Data
{
    /**
     * @param  list<SiblingData>  $siblings
     * @param  list<GuardianData>  $guardians
     * @param  list<EnrollmentHistoryData>  $enrollments
     * @param  list<StudentDocumentData>|null  $documents  Null when the viewer may not see documents
     * @param  list<SectionOptionData>  $section_options  Sections the viewer may move this student to (empty without edit rights)
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $admission_number,
        public string $status,
        public bool $is_current,
        public ?string $class_label,
        public ?int $roll_number,
        public ?string $campus_name,
        public string $gender,
        public string $born_on,
        public string $blood_group,
        public ?string $house_name,
        public string $address,
        public string $admitted_on,
        public ?string $left_on,
        public ?string $leave_reason,
        public ?string $tc_number,
        public array $siblings,
        public int $hidden_siblings,
        public array $guardians,
        public array $enrollments,
        public ?array $documents,
        public StudentAbilitiesData $abilities,
        public ?StudentEditData $edit,
        public ?string $session_name,
        public array $section_options,
    ) {}
}
