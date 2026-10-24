<?php

use App\Domain\Access\Actions\ApplyRoleTemplateUpdate;
use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\RemoveRoleAssignment;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Actions\UpdateRolePermissions;
use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Exceptions\GuardrailViolation;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Access\Queries\RoleTemplateUpdatesQuery;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class);

beforeEach(function () {
    $this->seed(AccessCatalogSeeder::class);

    foreach (['students', 'admissions', 'attendance', 'leave', 'fees', 'exams', 'communication', 'homework'] as $module) {
        DB::connection('landlord')->table('tenant_modules')->insert([
            'tenant_id' => $this->school->id, 'module' => $module, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

/** The school's owner (a staff identity with the owner role over the whole school). */
function accessOwner(): Identity
{
    $owner = test()->staff();
    app(SetUpSchoolOwner::class)->handle(test()->school, $owner, 'Meera Nair');

    return $owner;
}

/** A staff identity with a staff record and no roles yet. */
function accessPerson(string $name = 'Staff Member'): array
{
    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, $name);

    return [$identity, $staff];
}

function accessRole(Identity $owner, string $template, ?string $name = null, array $extra = [], bool $confirm = false): Role
{
    return app(CreateRole::class)->handle($owner, $name ?? $template, $extra, $template, $confirm);
}

function accessGive(Identity $actor, Staff $staff, Role $role, Scope $scope, bool $confirm = false): RoleAssignment
{
    return app(AssignRole::class)->handle($actor, $staff, $role, $scope, $confirm);
}

it('gives the owner every permission in the plan over the whole school', function () {
    $owner = accessOwner();

    expect(Gate::forUser($owner)->allows('roles.manage'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('fees.refund', [new ScopeTarget(campusUlid: 'any')]))->toBeTrue()
        ->and(app(Authorizer::class)->holdsOwnerRole($owner))->toBeTrue()
        ->and(app(Authorizer::class)->heldPermissions($owner))->toHaveCount(42);
});

it('denies people without a staff record or without the permission', function () {
    $owner = accessOwner();
    [$teacher, $staff] = accessPerson();
    $noRecord = $this->staff();

    expect(Gate::forUser($noRecord)->allows('students.view'))->toBeFalse();
    expect(Gate::forUser($teacher)->allows('students.view'))->toBeFalse();

    accessGive($owner, $staff, accessRole($owner, 'subject_teacher'), Scope::school());

    expect(Gate::forUser($teacher)->allows('marks.enter'))->toBeTrue()
        ->and(Gate::forUser($teacher)->allows('fees.refund'))->toBeFalse()
        ->and(Gate::forUser($teacher)->allows('roles.manage'))->toBeFalse();
});

it('limits a permission to the scope of the assignment', function () {
    $owner = accessOwner();
    [$teacher, $staff] = accessPerson();

    accessGive($owner, $staff, accessRole($owner, 'class_teacher'), Scope::sections(['sec-1', 'sec-2']));

    $can = fn (string $permission, ?ScopeTarget $target = null) => Gate::forUser($teacher)->allows($permission, $target ? [$target] : []);

    expect($can('attendance.mark'))->toBeTrue()
        ->and($can('attendance.mark', new ScopeTarget(sectionUlid: 'sec-2')))->toBeTrue()
        ->and($can('attendance.mark', new ScopeTarget(sectionUlid: 'sec-9')))->toBeFalse()
        ->and($can('attendance.mark', new ScopeTarget(campusUlid: 'c1')))->toBeFalse();
});

it('unions roles, each permission staying within its own role\'s scope', function () {
    $owner = accessOwner();
    [$person, $staff] = accessPerson();

    $attendance = accessRole($owner, 'subject_teacher', 'Attendance only', []);
    app(UpdateRolePermissions::class)->handle($owner, $attendance, ['attendance.mark']);
    $marks = accessRole($owner, 'subject_teacher', 'Marks only');
    app(UpdateRolePermissions::class)->handle($owner, $marks, ['marks.enter']);

    accessGive($owner, $staff, $attendance, Scope::campuses(['c1']));
    accessGive($owner, $staff, $marks, Scope::campuses(['c2']));

    $can = fn (string $permission, string $campus) => Gate::forUser($person)->allows($permission, [new ScopeTarget(campusUlid: $campus)]);

    expect($can('attendance.mark', 'c1'))->toBeTrue()
        ->and($can('attendance.mark', 'c2'))->toBeFalse()
        ->and($can('marks.enter', 'c2'))->toBeTrue()
        ->and($can('marks.enter', 'c1'))->toBeFalse();
});

it('denies permissions of modules outside the school\'s plan', function () {
    $owner = accessOwner();

    expect(Gate::forUser($owner)->allows('fees.collect'))->toBeTrue();

    DB::connection('landlord')->table('tenant_modules')->where('module', 'fees')->delete();
    app(Authorizer::class)->flush();

    expect(Gate::forUser($owner)->allows('fees.collect'))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('students.view'))->toBeTrue()
        ->and(app(Authorizer::class)->heldPermissions($owner))->not->toContain('fees.refund', 'fees.collect');
});

it('denies everything while the school is suspended or expired', function (string $status) {
    $owner = accessOwner();

    $this->school->update(['status' => $status]);
    app(Authorizer::class)->flush();

    expect(Gate::forUser($owner)->allows('students.view'))->toBeFalse();
})->with(['suspended', 'expired']);

it('leaves ability names that are not permission keys to ordinary gates', function () {
    Gate::define('something-else', fn () => true);

    expect(Gate::forUser($this->staff())->allows('something-else'))->toBeTrue();
});

it('copies a template into a school role, leaving out modules outside the plan', function () {
    $owner = accessOwner();

    DB::connection('landlord')->table('tenant_modules')->where('module', 'homework')->delete();

    $role = accessRole($owner, 'class_teacher', 'Class 5 teacher');

    expect($role->source_template_key)->toBe('class_teacher')
        ->and($role->source_template_version)->toBe(3)
        ->and($role->is_owner)->toBeFalse()
        ->and($role->permissionKeys())->toContain('attendance.mark', 'marks.enter')
        ->and($role->permissionKeys())->not->toContain('homework.assign', 'homework.view');
});

it('keeps owner-only permissions out of non-owner roles', function () {
    $owner = accessOwner();

    expect(fn () => app(CreateRole::class)->handle($owner, 'Sneaky', ['roles.manage']))
        ->toThrow(GuardrailViolation::class, 'owner role')
        ->and(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse();

    $role = accessRole($owner, 'subject_teacher');

    expect(fn () => app(UpdateRolePermissions::class)->handle($owner, $role, ['roles.manage']))
        ->toThrow(GuardrailViolation::class, 'owner role');
});

it('requires confirmation to grant a critical permission and audits it', function () {
    $owner = accessOwner();

    expect(fn () => app(CreateRole::class)->handle($owner, 'Refunder', ['fees.refund']))
        ->toThrow(GuardrailViolation::class, 'confirmation');

    $role = app(CreateRole::class)->handle($owner, 'Refunder', ['fees.refund'], null, true);

    $event = AuditEvent::query()->where('action', 'roles.created')->where('subject_ulid', $role->ulid)->sole();

    expect($event->after['critical_confirmed'])->toBeTrue()
        ->and($event->after['permissions'])->toBe(['fees.refund']);
});

it('only lets managers of roles create or edit them', function () {
    $owner = accessOwner();
    [$vp, $staff] = accessPerson();
    accessGive($owner, $staff, accessRole($owner, 'vice_principal'), Scope::school());

    expect(fn () => app(CreateRole::class)->handle($vp, 'My role', ['students.view']))
        ->toThrow(GuardrailViolation::class, 'not allowed');
});

it('stops anyone granting a permission or a scope they do not hold', function () {
    $owner = accessOwner();
    [$assigner, $assignerStaff] = accessPerson('Assigner');
    [, $target] = accessPerson('Target');

    $assignerRole = accessRole($owner, 'subject_teacher', 'Assigner role', ['roles.assign']);
    accessGive($owner, $assignerStaff, $assignerRole, Scope::campuses(['c1']));

    $teacherRole = accessRole($owner, 'subject_teacher', 'Teacher');
    $accountantRole = accessRole($owner, 'accountant', 'Accountant', [], true);

    // Holds the permissions, but the scope must lie within their own.
    accessGive($assigner, $target, $teacherRole, Scope::campuses(['c1']));

    expect(fn () => accessGive($assigner, $target, $teacherRole, Scope::campuses(['c2'])))
        ->toThrow(GuardrailViolation::class, 'wider than your own')
        ->and(fn () => accessGive($assigner, $target, $teacherRole, Scope::school()))
        ->toThrow(GuardrailViolation::class, 'wider than your own')
        ->and(fn () => accessGive($assigner, $target, $accountantRole, Scope::campuses(['c1'])))
        ->toThrow(GuardrailViolation::class, 'do not hold');
});

it('does not allow assigning the same role twice for one scope', function () {
    $owner = accessOwner();
    [, $staff] = accessPerson();
    $role = accessRole($owner, 'subject_teacher');

    accessGive($owner, $staff, $role, Scope::campuses(['c1']));
    accessGive($owner, $staff, $role, Scope::campuses(['c1']));
})->throws(InvalidArgumentException::class, 'already assigned');

it('keeps at least one owner', function () {
    $owner = accessOwner();
    $assignment = RoleAssignment::query()->whereHas('role', fn ($q) => $q->where('is_owner', true))->sole();

    expect(fn () => app(RemoveRoleAssignment::class)->handle($owner, $assignment))
        ->toThrow(GuardrailViolation::class, 'at least one owner');

    [, $second] = accessPerson('Second owner');
    $ownerRole = Role::query()->where('is_owner', true)->sole();
    accessGive($owner, $second, $ownerRole, Scope::school(), true);

    app(RemoveRoleAssignment::class)->handle($owner, $assignment);

    expect(RoleAssignment::query()->whereHas('role', fn ($q) => $q->where('is_owner', true))->count())->toBe(1);
});

it('audits role changes with who did them and in which roles', function () {
    $owner = accessOwner();

    $this->actingAs($owner);
    accessRole($owner, 'subject_teacher', 'Audited role');

    $event = AuditEvent::query()->where('action', 'roles.created')->sole();

    expect($event->actor_type)->toBe('staff')
        ->and($event->actor_identity_ulid)->toBe($owner->ulid)
        ->and($event->actor_membership_ulid)->not->toBeNull()
        ->and($event->actor_role_keys)->toBe(['Principal / owner']);
});

it('records every grant, change and removal in the school audit trail', function () {
    $owner = accessOwner();
    [, $staff] = accessPerson();
    $role = accessRole($owner, 'subject_teacher');
    app(UpdateRolePermissions::class)->handle($owner, $role, ['marks.enter']);
    $assignment = accessGive($owner, $staff, $role, Scope::school());
    app(RemoveRoleAssignment::class)->handle($owner, $assignment);

    expect(AuditEvent::query()->orderBy('id')->pluck('action')->all())->toBe([
        'access.owner_set_up', 'staff.created', 'roles.created', 'roles.permissions_updated', 'roles.assigned', 'roles.assignment_removed',
    ]);
});

it('requires two-step for owners and holders of critical permissions', function () {
    $owner = accessOwner();

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    $this->actingAs($owner)->get('/settings/security')->assertRedirect(route('password.confirm'));

    [$accountant, $staff] = accessPerson();
    accessGive($owner, $staff, accessRole($owner, 'accountant', 'Accountant', [], true), Scope::school(), true);

    $this->actingAs($accountant)->get(route('dashboard'))->assertRedirect(route('security.edit'));

    $accountant->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($accountant->fresh())->get(route('dashboard'))->assertOk();
});

it('does not require two-step for people without owner or critical roles', function () {
    $owner = accessOwner();
    [$teacher, $staff] = accessPerson();
    accessGive($owner, $staff, accessRole($owner, 'subject_teacher'), Scope::school());

    $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
});

it('shares only the effective abilities with the browser', function () {
    $owner = accessOwner();
    [$teacher, $staff] = accessPerson();
    accessGive($owner, $staff, accessRole($owner, 'subject_teacher'), Scope::campuses(['c1']));

    $this->actingAs($teacher)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('auth.abilities.is_owner', false)
        ->where('auth.abilities.permissions', fn ($permissions) => collect($permissions)->contains('marks.enter')
            && ! collect($permissions)->contains('fees.refund')));
});

it('seeds ten templates, their versions and the catalog idempotently', function () {
    $this->seed(AccessCatalogSeeder::class);

    $landlord = DB::connection('landlord');
    $principalVersions = fn (int $version) => $landlord->table('role_template_versions')
        ->where('is_owner_template', true)->where('version', $version)->value('id');

    expect($landlord->table('role_templates')->count())->toBe(10)
        ->and($landlord->table('permissions')->count())->toBe(42)
        ->and($landlord->table('role_template_versions')->count())->toBe(47)
        ->and($landlord->table('role_templates')->where('key', 'principal')->value('current_version'))->toBe(10)
        ->and($landlord->table('role_templates')->where('key', 'librarian')->value('current_version'))->toBe(1)
        ->and($landlord->table('role_template_version_permissions')->where('role_template_version_id', $principalVersions(1))->count())->toBe(27)
        ->and($landlord->table('role_template_version_permissions')->where('role_template_version_id', $principalVersions(2))->count())->toBe(29)
        ->and($landlord->table('role_template_version_permissions')->where('role_template_version_id', $principalVersions(3))->count())->toBe(35);

    $vice = $landlord->table('role_templates')->where('key', 'vice_principal')->value('id');
    $viceKeys = $landlord->table('role_template_version_permissions')
        ->where('role_template_version_id', $landlord->table('role_template_versions')->where('role_template_id', $vice)->where('version', 2)->value('id'))
        ->pluck('permission_key');

    expect($viceKeys)->not->toContain('roles.manage', 'fees.refund')
        ->and($viceKeys)->toContain('structure.manage');
});

it('offers a newer template version and applies it keeping the edits a school made', function () {
    $owner = accessOwner();

    // A role copied when the template was at version 1: no structure permission yet, plus a school edit.
    $role = accessRole($owner, 'subject_teacher', 'Maths teacher');
    $role->permissions()->where('permission_key', 'structure.view')->delete();
    $role->forceFill(['source_template_version' => 1])->save();
    app(UpdateRolePermissions::class)->handle($owner, $role, [...$role->permissionKeys(), 'notices.send']);

    $updates = app(RoleTemplateUpdatesQuery::class)->get();
    $mine = collect($updates)->firstWhere('role_ulid', $role->ulid);

    expect($mine)->not->toBeNull()
        ->and($mine['from_version'])->toBe(1)
        ->and($mine['to_version'])->toBe(3)
        ->and($mine['added'])->toBe(['structure.view', 'subjects.update'])
        ->and($mine['removed'])->toBe([]);

    app(ApplyRoleTemplateUpdate::class)->handle($owner, $role);

    $role->refresh();

    expect($role->source_template_version)->toBe(3)
        ->and($role->permissionKeys())->toContain('structure.view', 'subjects.update', 'notices.send')
        ->and(app(RoleTemplateUpdatesQuery::class)->get())->toBe([])
        ->and(AuditEvent::query()->where('action', 'roles.template_update_applied')->sole()->after['added'])->toBe(['structure.view', 'subjects.update']);
});

it('refuses to apply an update when none is waiting', function () {
    $owner = accessOwner();
    $role = accessRole($owner, 'subject_teacher');

    app(ApplyRoleTemplateUpdate::class)->handle($owner, $role);
})->throws(InvalidArgumentException::class, 'no template update');

it('keeps roles and staff in the own database of each school', function () {
    expect(Schema::connection('landlord')->hasTable('roles'))->toBeFalse()
        ->and(Schema::connection('landlord')->hasTable('role_assignments'))->toBeFalse()
        ->and(Schema::connection('landlord')->hasTable('staff'))->toBeFalse()
        ->and(Schema::connection('tenant')->hasTable('role_assignments'))->toBeTrue();
});
