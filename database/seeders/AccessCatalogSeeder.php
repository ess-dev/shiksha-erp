<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\RoleTemplate;
use App\Domain\Access\Models\RoleTemplateVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Platform data: the permission catalog and the versioned role templates.
 * Safe to re-run: permissions are updated in place; a template version that
 * already exists is left alone, so changing a template means adding a new
 * version (a new entry under its "versions" with a changelog). Schools then
 * see "update available" for roles copied from the older version.
 *
 * illustrative — verify: the permission list and template contents are
 * starting points until the product domain is settled.
 */
class AccessCatalogSeeder extends Seeder
{
    /**
     * key => [module, risk, owner-only, description, since template version]
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: string, 4: int}>
     */
    private const PERMISSIONS = [
        'roles.manage' => ['core', 'critical', true, 'Create and edit roles and their permissions', 1],
        'roles.assign' => ['core', 'sensitive', false, 'Give roles to staff within your own reach', 1],
        'roles.view' => ['core', 'normal', false, 'See roles and who holds them', 1],
        'staff.view' => ['core', 'normal', false, 'See staff records', 1],
        'staff.manage' => ['core', 'sensitive', false, 'Add and edit staff records', 1],
        'settings.manage' => ['core', 'sensitive', false, 'Change school settings', 1],
        'audit.view' => ['core', 'sensitive', false, 'See the audit trail', 1],
        'structure.view' => ['core', 'normal', false, 'See campuses, academic years, classes, sections and subjects', 2],
        'structure.manage' => ['core', 'sensitive', false, 'Set up campuses, academic years, classes, sections and subjects', 2],
        // Students are part of the core product, not a sellable module: a "students" module row would never
        // exist in tenant_modules, so these permissions are always available (changed 2026-10-30).
        'students.view' => ['core', 'normal', false, 'See students', 1],
        'students.manage' => ['core', 'sensitive', false, 'Add and edit students, change sections and roll numbers', 1],
        'students.view_sensitive' => ['core', 'sensitive', false, 'See guardian phones, documents and medical notes', 1],
        'students.issue_tc' => ['core', 'critical', false, 'Issue transfer certificates', 3],
        'students.import' => ['core', 'sensitive', false, 'Import students from a CSV file', 3],
        'students.export' => ['core', 'sensitive', false, 'Export students with guardian phone numbers', 3],
        'students.documents' => ['core', 'sensitive', false, 'Upload and verify student documents', 3],
        'students.link_siblings' => ['core', 'sensitive', false, 'Link students as siblings', 3],
        'students.view_past_sessions' => ['core', 'sensitive', false, 'View students of past academic sessions (read only)', 4],
        'houses.manage' => ['core', 'normal', false, 'Add, rename and reorder school houses', 3],
        'admissions.view' => ['admissions', 'normal', false, 'See admission applications', 1],
        'admissions.manage' => ['admissions', 'sensitive', false, 'Process admissions', 1],
        'admissions.enroll' => ['admissions', 'sensitive', false, 'Enroll accepted applicants as students', 5],
        'attendance.view' => ['attendance', 'normal', false, 'See attendance', 1],
        'attendance.mark' => ['attendance', 'normal', false, 'Mark attendance', 1],
        'attendance.correct' => ['attendance', 'sensitive', false, 'Correct attendance registers, including those past the class teacher\'s window', 6],
        'leave.approve' => ['leave', 'normal', false, 'Approve student leave', 1],
        'fees.view' => ['fees', 'normal', false, 'See fee invoices and payments', 1],
        'fees.collect' => ['fees', 'sensitive', false, 'Record fee payments', 1],
        'fees.refund' => ['fees', 'critical', false, 'Refund fee payments', 1],
        'fee_structures.manage' => ['fees', 'sensitive', false, 'Edit fee structures', 1],
        'concessions.apply' => ['fees', 'sensitive', false, 'Apply fee concessions', 1],
        'exams.view' => ['exams', 'normal', false, 'See exams and marks', 1],
        'marks.enter' => ['exams', 'normal', false, 'Enter marks', 1],
        'results.publish' => ['exams', 'sensitive', false, 'Publish results', 1],
        'notices.view' => ['communication', 'normal', false, 'See notices', 1],
        'notices.send' => ['communication', 'normal', false, 'Send notices', 1],
        'homework.view' => ['homework', 'normal', false, 'See homework', 1],
        'homework.assign' => ['homework', 'normal', false, 'Assign homework', 1],
    ];

    private const COORDINATOR = [
        'roles.view', 'staff.view', 'students.view', 'attendance.view', 'leave.approve', 'exams.view',
        'results.publish', 'notices.view', 'notices.send', 'homework.view',
    ];

    private const CLASS_TEACHER = [
        'students.view', 'attendance.view', 'attendance.mark', 'leave.approve', 'exams.view', 'marks.enter',
        'notices.view', 'notices.send', 'homework.view', 'homework.assign',
    ];

    private const SUBJECT_TEACHER = [
        'students.view', 'attendance.mark', 'exams.view', 'marks.enter', 'notices.view', 'homework.view', 'homework.assign',
    ];

    private const ACCOUNTANT = [
        'students.view', 'fees.view', 'fees.collect', 'fees.refund', 'fee_structures.manage', 'concessions.apply',
    ];

    private const ADMISSION_OFFICER = ['students.view', 'admissions.view', 'admissions.manage'];

    private const STUDENTS_V3 = 'Adds student management: edit, import, export, documents, sibling links and houses';

    private const SESSIONS_V4 = 'Adds viewing of past academic sessions in the Students screens';

    private const COORDINATOR_V3_ADDS = [
        'students.manage', 'students.view_sensitive', 'students.import', 'students.export', 'students.documents',
        'students.link_siblings', 'houses.manage',
    ];

    private const ADMISSION_OFFICER_V3_ADDS = ['students.manage', 'students.view_sensitive', 'students.import', 'students.documents'];

    private const ADMISSIONS_FULL = ['admissions.view', 'admissions.manage', 'admissions.enroll'];

    private const ADMISSIONS_V5 = 'Adds enrolling accepted applicants, and the admissions pipeline for Coordinator and Front office';

    private const ATTENDANCE_V6 = 'Adds correcting attendance registers (Principal, Vice principal, Coordinator)';

    private const TRANSPORT_INCHARGE = ['students.view', 'notices.view', 'notices.send'];

    private const LIBRARIAN = ['students.view', 'notices.view'];

    private const FRONT_OFFICE = ['students.view', 'admissions.view', 'attendance.view', 'notices.view'];

    private const STRUCTURE_V2 = 'Adds viewing of the school structure (campuses, academic years, classes, sections, subjects)';

    /**
     * key => [name, is owner template, [version => [changelog, permissions]]]
     * Permissions are a list, '*' (every permission that exists in that
     * version) or '*-restricted' (the same, minus owner-only and critical).
     *
     * @var array<string, array{0: string, 1: bool, 2: array<int, array{0: string, 1: array<int, string>|string}>}>
     */
    private const TEMPLATES = [
        'principal' => ['Principal / owner', true, [
            1 => ['Initial version', '*'],
            2 => ['Adds school structure permissions', '*'],
            3 => [self::STUDENTS_V3, '*'],
            4 => [self::SESSIONS_V4, '*'],
            5 => [self::ADMISSIONS_V5, '*'],
            6 => [self::ATTENDANCE_V6, '*'],
        ]],
        'vice_principal' => ['Vice principal', false, [
            1 => ['Initial version', '*-restricted'],
            2 => ['Adds school structure permissions', '*-restricted'],
            3 => [self::STUDENTS_V3, '*-restricted'],
            4 => [self::SESSIONS_V4, '*-restricted'],
            5 => [self::ADMISSIONS_V5, '*-restricted'],
            6 => [self::ATTENDANCE_V6, '*-restricted'],
        ]],
        'coordinator' => ['Coordinator', false, [
            1 => ['Initial version', self::COORDINATOR],
            2 => [self::STRUCTURE_V2, [...self::COORDINATOR, 'structure.view']],
            3 => [self::STUDENTS_V3, [...self::COORDINATOR, 'structure.view', ...self::COORDINATOR_V3_ADDS]],
            4 => [self::SESSIONS_V4, [...self::COORDINATOR, 'structure.view', ...self::COORDINATOR_V3_ADDS, 'students.view_past_sessions']],
            5 => [self::ADMISSIONS_V5, [...self::COORDINATOR, 'structure.view', ...self::COORDINATOR_V3_ADDS, 'students.view_past_sessions', ...self::ADMISSIONS_FULL]],
            6 => [self::ATTENDANCE_V6, [...self::COORDINATOR, 'structure.view', ...self::COORDINATOR_V3_ADDS, 'students.view_past_sessions', ...self::ADMISSIONS_FULL, 'attendance.correct']],
        ]],
        'class_teacher' => ['Class teacher', false, [
            1 => ['Initial version', self::CLASS_TEACHER],
            2 => [self::STRUCTURE_V2, [...self::CLASS_TEACHER, 'structure.view']],
        ]],
        'subject_teacher' => ['Subject teacher', false, [
            1 => ['Initial version', self::SUBJECT_TEACHER],
            2 => [self::STRUCTURE_V2, [...self::SUBJECT_TEACHER, 'structure.view']],
        ]],
        'accountant' => ['Accountant', false, [
            1 => ['Initial version', self::ACCOUNTANT],
            2 => [self::STRUCTURE_V2, [...self::ACCOUNTANT, 'structure.view']],
        ]],
        'admission_officer' => ['Admission officer', false, [
            1 => ['Initial version', self::ADMISSION_OFFICER],
            2 => [self::STRUCTURE_V2, [...self::ADMISSION_OFFICER, 'structure.view']],
            3 => [self::STUDENTS_V3, [...self::ADMISSION_OFFICER, 'structure.view', ...self::ADMISSION_OFFICER_V3_ADDS]],
            5 => [self::ADMISSIONS_V5, [...self::ADMISSION_OFFICER, 'structure.view', ...self::ADMISSION_OFFICER_V3_ADDS, 'admissions.enroll']],
        ]],
        'transport_incharge' => ['Transport in-charge', false, [
            1 => ['Initial version', self::TRANSPORT_INCHARGE],
        ]],
        'librarian' => ['Librarian', false, [
            1 => ['Initial version', self::LIBRARIAN],
        ]],
        'front_office' => ['Front office', false, [
            1 => ['Initial version', self::FRONT_OFFICE],
            2 => [self::STRUCTURE_V2, [...self::FRONT_OFFICE, 'structure.view']],
            5 => [self::ADMISSIONS_V5, [...self::FRONT_OFFICE, 'structure.view', 'admissions.manage']],
        ]],
    ];

    public function run(): void
    {
        DB::connection(config('multitenancy.landlord_database_connection_name'))->transaction(function () {
            foreach (self::PERMISSIONS as $key => [$module, $risk, $ownerOnly, $description]) {
                Permission::query()->updateOrCreate(['key' => $key], [
                    'module' => $module,
                    'risk' => $risk,
                    'is_owner_only' => $ownerOnly,
                    'description' => $description,
                ]);
            }

            foreach (self::TEMPLATES as $key => [$name, $isOwner, $versions]) {
                $template = RoleTemplate::query()->firstOrCreate(['key' => $key], ['name' => $name, 'current_version' => 1]);

                foreach ($versions as $version => [$changelog, $permissions]) {
                    if ($template->versions()->where('version', $version)->exists()) {
                        continue;
                    }

                    $record = RoleTemplateVersion::query()->create([
                        'role_template_id' => $template->id,
                        'version' => $version,
                        'changelog' => $changelog,
                        'is_owner_template' => $isOwner,
                    ]);

                    foreach ($this->permissionsFor($permissions, $version) as $permissionKey) {
                        DB::connection(config('multitenancy.landlord_database_connection_name'))
                            ->table('role_template_version_permissions')
                            ->insert(['role_template_version_id' => $record->id, 'permission_key' => $permissionKey]);
                    }
                }

                $template->update(['name' => $name, 'current_version' => max(array_keys($versions))]);
            }
        });
    }

    /**
     * @param  array<int, string>|string  $permissions
     * @return list<string>
     */
    private function permissionsFor(array|string $permissions, int $version): array
    {
        if (is_array($permissions)) {
            return array_values($permissions);
        }

        $existing = array_filter(self::PERMISSIONS, fn (array $entry) => $entry[4] <= $version);

        if ($permissions === '*') {
            return array_keys($existing);
        }

        if ($permissions === '*-restricted') {
            return array_keys(array_filter($existing, fn (array $entry) => ! $entry[2] && $entry[1] !== 'critical'));
        }

        throw new InvalidArgumentException("Unknown permission set [{$permissions}].");
    }
}
