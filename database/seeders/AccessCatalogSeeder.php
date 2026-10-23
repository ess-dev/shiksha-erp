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
 * version (bump its "version" and describe the change in "changelog").
 *
 * illustrative — verify: the permission list and template contents are
 * starting points until the product domain is settled.
 */
class AccessCatalogSeeder extends Seeder
{
    /**
     * key => [module, risk, owner-only, description]
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: string}>
     */
    private const PERMISSIONS = [
        'roles.manage' => ['core', 'critical', true, 'Create and edit roles and their permissions'],
        'roles.assign' => ['core', 'sensitive', false, 'Give roles to staff within your own reach'],
        'roles.view' => ['core', 'normal', false, 'See roles and who holds them'],
        'staff.view' => ['core', 'normal', false, 'See staff records'],
        'staff.manage' => ['core', 'sensitive', false, 'Add and edit staff records'],
        'settings.manage' => ['core', 'sensitive', false, 'Change school settings'],
        'audit.view' => ['core', 'sensitive', false, 'See the audit trail'],
        'students.view' => ['students', 'normal', false, 'See students'],
        'students.manage' => ['students', 'sensitive', false, 'Add and edit students'],
        'students.view_sensitive' => ['students', 'sensitive', false, 'See guardian phones, documents and medical notes'],
        'admissions.view' => ['admissions', 'normal', false, 'See admission applications'],
        'admissions.manage' => ['admissions', 'sensitive', false, 'Process admissions'],
        'attendance.view' => ['attendance', 'normal', false, 'See attendance'],
        'attendance.mark' => ['attendance', 'normal', false, 'Mark attendance'],
        'leave.approve' => ['leave', 'normal', false, 'Approve student leave'],
        'fees.view' => ['fees', 'normal', false, 'See fee invoices and payments'],
        'fees.collect' => ['fees', 'sensitive', false, 'Record fee payments'],
        'fees.refund' => ['fees', 'critical', false, 'Refund fee payments'],
        'fee_structures.manage' => ['fees', 'sensitive', false, 'Edit fee structures'],
        'concessions.apply' => ['fees', 'sensitive', false, 'Apply fee concessions'],
        'exams.view' => ['exams', 'normal', false, 'See exams and marks'],
        'marks.enter' => ['exams', 'normal', false, 'Enter marks'],
        'results.publish' => ['exams', 'sensitive', false, 'Publish results'],
        'notices.view' => ['communication', 'normal', false, 'See notices'],
        'notices.send' => ['communication', 'normal', false, 'Send notices'],
        'homework.view' => ['homework', 'normal', false, 'See homework'],
        'homework.assign' => ['homework', 'normal', false, 'Assign homework'],
    ];

    /**
     * key => [name, is owner template, version, changelog, permissions|'*'|'*-restricted']
     * '*' is every permission; '*-restricted' is every permission that is
     * neither owner-only nor critical.
     *
     * @var array<string, array{0: string, 1: bool, 2: int, 3: string, 4: array<int, string>|string}>
     */
    private const TEMPLATES = [
        'principal' => ['Principal / owner', true, 1, 'Initial version', '*'],
        'vice_principal' => ['Vice principal', false, 1, 'Initial version', '*-restricted'],
        'coordinator' => ['Coordinator', false, 1, 'Initial version', [
            'roles.view', 'staff.view', 'students.view', 'attendance.view', 'leave.approve', 'exams.view',
            'results.publish', 'notices.view', 'notices.send', 'homework.view',
        ]],
        'class_teacher' => ['Class teacher', false, 1, 'Initial version', [
            'students.view', 'attendance.view', 'attendance.mark', 'leave.approve', 'exams.view', 'marks.enter',
            'notices.view', 'notices.send', 'homework.view', 'homework.assign',
        ]],
        'subject_teacher' => ['Subject teacher', false, 1, 'Initial version', [
            'students.view', 'attendance.mark', 'exams.view', 'marks.enter', 'notices.view', 'homework.view', 'homework.assign',
        ]],
        'accountant' => ['Accountant', false, 1, 'Initial version', [
            'students.view', 'fees.view', 'fees.collect', 'fees.refund', 'fee_structures.manage', 'concessions.apply',
        ]],
        'admission_officer' => ['Admission officer', false, 1, 'Initial version', [
            'students.view', 'admissions.view', 'admissions.manage',
        ]],
        'transport_incharge' => ['Transport in-charge', false, 1, 'Initial version', [
            'students.view', 'notices.view', 'notices.send',
        ]],
        'librarian' => ['Librarian', false, 1, 'Initial version', ['students.view', 'notices.view']],
        'front_office' => ['Front office', false, 1, 'Initial version', [
            'students.view', 'admissions.view', 'attendance.view', 'notices.view',
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

            foreach (self::TEMPLATES as $key => [$name, $isOwner, $version, $changelog, $permissions]) {
                $template = RoleTemplate::query()->firstOrCreate(['key' => $key], ['name' => $name, 'current_version' => $version]);

                if ($template->versions()->where('version', $version)->exists()) {
                    continue;
                }

                $record = RoleTemplateVersion::query()->create([
                    'role_template_id' => $template->id,
                    'version' => $version,
                    'changelog' => $changelog,
                    'is_owner_template' => $isOwner,
                ]);

                foreach ($this->permissionsFor($permissions) as $permissionKey) {
                    DB::connection(config('multitenancy.landlord_database_connection_name'))
                        ->table('role_template_version_permissions')
                        ->insert(['role_template_version_id' => $record->id, 'permission_key' => $permissionKey]);
                }

                $template->update(['name' => $name, 'current_version' => max($template->current_version, $version)]);
            }
        });
    }

    /**
     * @param  array<int, string>|string  $permissions
     * @return list<string>
     */
    private function permissionsFor(array|string $permissions): array
    {
        if (is_array($permissions)) {
            return array_values($permissions);
        }

        if ($permissions === '*') {
            return array_keys(self::PERMISSIONS);
        }

        if ($permissions === '*-restricted') {
            return array_keys(array_filter(
                self::PERMISSIONS,
                fn (array $entry) => ! $entry[2] && $entry[1] !== 'critical',
            ));
        }

        throw new InvalidArgumentException("Unknown permission set [{$permissions}].");
    }
}
