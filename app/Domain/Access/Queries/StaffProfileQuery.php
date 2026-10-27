<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\StaffProfileData;
use App\Domain\Access\Data\StaffRoleChipData;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\ScopeOptionsData;
use App\Domain\School\Queries\AccessibleCampusesQuery;
use App\Domain\School\Queries\ScopeOptionsQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\School\Queries\StaffSubjectsQuery;
use App\Tenancy\Tenant;

/**
 * The signed-in member of staff's own profile page. Only ever the caller's own
 * record, so no permission scope applies.
 */
class StaffProfileQuery
{
    public const CHANNELS = ['push', 'sms', 'whatsapp', 'email'];

    public function __construct(
        private readonly ScopeOptionsQuery $scopeOptions,
        private readonly AccessibleCampusesQuery $campuses,
        private readonly SectionDirectoryQuery $sections,
        private readonly StaffSubjectsQuery $subjects,
    ) {}

    /**
     * Where the caller's own photo is stored on the tenant disk, if they have one.
     */
    public function photoPath(Identity $identity): ?string
    {
        return Staff::query()->where('identity_ulid', $identity->ulid)->value('photo_path');
    }

    public function forIdentity(Identity $identity): ?StaffProfileData
    {
        $staff = Staff::query()->with('assignments.role')->where('identity_ulid', $identity->ulid)->first();

        if ($staff === null) {
            return null;
        }

        $tenant = Tenant::current();
        $options = $this->scopeOptions->get();
        $whatsappInPlan = $tenant?->hasModule('whatsapp') ?? false;

        $classTeacherOf = array_values(array_map(
            fn ($section) => $section->label(),
            array_filter($this->sections->currentYear(), fn ($section) => $section->classTeacherStaffKey === $staff->id),
        ));

        return new StaffProfileData(
            full_name: $staff->full_name,
            school_name: $tenant->name ?? '',
            has_photo: $staff->photo_path !== null,
            personal_email: $staff->personal_email,
            qualifications: $staff->qualifications,
            languages: $staff->languages,
            home_address: $staff->home_address,
            about: $staff->about,
            emergency_name: $staff->emergency_name,
            emergency_relation: $staff->emergency_relation,
            emergency_phone: $staff->emergency_phone,
            employee_code: $staff->employee_code,
            job_title: $staff->job_title,
            department: $staff->department,
            joined_on: $staff->joined_on,
            campuses: array_map(fn ($campus) => $campus->name, $this->campuses->get($identity)),
            class_teacher_of: $classTeacherOf,
            subjects: $this->subjects->names($staff->id),
            roles: array_values($staff->assignments->map(fn (RoleAssignment $assignment) => new StaffRoleChipData(
                $assignment->role->name,
                $this->scopeLabel($assignment, $options),
            ))->all()),
            channels: self::channelsFor($staff, $whatsappInPlan),
            whatsapp_in_plan: $whatsappInPlan,
        );
    }

    /**
     * Stored choices over the defaults (push, SMS and email on, WhatsApp off).
     * WhatsApp is always off outside the school's plan.
     *
     * @return array{push: bool, sms: bool, whatsapp: bool, email: bool}
     */
    public static function channelsFor(Staff $staff, bool $whatsappInPlan): array
    {
        $stored = $staff->notification_channels ?? [];

        return [
            'push' => (bool) ($stored['push'] ?? true),
            'sms' => (bool) ($stored['sms'] ?? true),
            'whatsapp' => $whatsappInPlan && (bool) ($stored['whatsapp'] ?? false),
            'email' => (bool) ($stored['email'] ?? true),
        ];
    }

    private function scopeLabel(RoleAssignment $assignment, ScopeOptionsData $options): string
    {
        $scope = $assignment->toScope();
        $campusNames = array_map(
            fn (string $ulid) => collect($options->campuses)->pluck('name', 'ulid')->get($ulid, __('a campus')),
            $scope->campusUlids(),
        );

        return match ($assignment->scope_type) {
            ScopeType::School => __('Whole school'),
            ScopeType::Campus => implode(', ', $campusNames),
            ScopeType::Wing => $this->wingLabel($scope->levelRange(), $campusNames, $options),
            ScopeType::Sections => $this->sectionsLabel($scope->sectionUlids(), $options),
        };
    }

    /**
     * @param  array{0: int, 1: int}|null  $range
     * @param  list<string>  $campusNames
     */
    private function wingLabel(?array $range, array $campusNames, ScopeOptionsData $options): string
    {
        $level = fn (int $value) => collect($options->levels)->pluck('name', 'level')->get($value, "level {$value}");

        return $range === null ? implode(', ', $campusNames) : $level($range[0]).' '.__('to').' '.$level($range[1]).' '.__('at').' '.implode(', ', $campusNames);
    }

    /**
     * @param  list<string>  $ulids
     */
    private function sectionsLabel(array $ulids, ScopeOptionsData $options): string
    {
        $names = array_values(array_filter(array_map(
            fn (string $ulid) => collect($options->sections)->pluck('label', 'ulid')->get($ulid),
            $ulids,
        )));

        return count($names) === count($ulids) && count($names) <= 3 ? implode(', ', $names) : count($ulids).' '.__('sections');
    }
}
