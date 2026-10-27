<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The signed-in staff member's own profile: what they edit, what the school
 * office keeps (read-only here), their roles and their notification channels.
 */
#[TypeScript]
class StaffProfileData extends Data
{
    /**
     * @param  list<string>  $campuses
     * @param  list<string>  $class_teacher_of
     * @param  list<string>  $subjects
     * @param  list<StaffRoleChipData>  $roles
     * @param  array{push: bool, sms: bool, whatsapp: bool, email: bool}  $channels
     */
    public function __construct(
        public string $full_name,
        public string $school_name,
        public bool $has_photo,
        public ?string $personal_email,
        public ?string $qualifications,
        public ?string $languages,
        public ?string $home_address,
        public ?string $about,
        public ?string $emergency_name,
        public ?string $emergency_relation,
        public ?string $emergency_phone,
        public ?string $employee_code,
        public ?string $job_title,
        public ?string $department,
        public ?string $joined_on,
        public array $campuses,
        public array $class_teacher_of,
        public array $subjects,
        public array $roles,
        public array $channels,
        public bool $whatsapp_in_plan,
    ) {}
}
