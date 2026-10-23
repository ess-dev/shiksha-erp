<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class);

beforeEach(function () {
    $this->seed(AccessCatalogSeeder::class);

    $this->owner = test()->staff(twoFactor: true);
    app(SetUpSchoolOwner::class)->handle($this->school, $this->owner, 'Meera Nair');
});

/** @return array{0: Identity, 1: Staff} A staff identity holding a new role with the permissions over a scope. */
function rolesScreenPerson(array $permissions, ?Scope $scope = null): array
{
    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Person '.uniqid());

    if ($permissions !== []) {
        $role = app(CreateRole::class)->handle(test()->owner, 'Role '.uniqid(), $permissions);
        app(AssignRole::class)->handle(test()->owner, $staff, $role, $scope ?? Scope::school());
    }

    return [$identity, $staff];
}

it('shows roles, staff and scope options with ulids only', function () {
    $campus = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
    [, $staff] = rolesScreenPerson(['structure.view']);

    $this->actingAs($this->owner)
        ->get(route('school.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/roles')
            ->has('roles', 2)
            ->has('staff', 2)
            ->has('permissions')
            ->where('scopeOptions.campuses.0.ulid', $campus->ulid)
            ->missing('roles.0.id')
            ->missing('staff.0.id')
            ->missing('staff.0.assignments.0.id')
            ->where('roles.0.is_owner', true));

    expect($staff->ulid)->toBe($staff->fresh()->ulid);
});

it('lets a viewer look but gives them no scope options or templates', function () {
    [$viewer] = rolesScreenPerson(['roles.view']);

    $this->actingAs($viewer)
        ->get(route('school.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scopeOptions', null)
            ->where('templates', []));

    $this->post(route('school.roles.store'), ['name' => 'Sneaky'])->assertForbidden();
    expect(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

it('refuses the page without roles.view, to guests and to a suspended school', function () {
    $this->get(route('school.roles.index'))->assertRedirect(route('login'));

    [$other] = rolesScreenPerson(['structure.view']);
    $this->actingAs($other)->get(route('school.roles.index'))->assertForbidden();

    $this->school->update(['status' => 'suspended']);
    $this->actingAs($this->owner)->get(route('school.roles.index'))
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('creates a role from a template and audits it', function () {
    $this->actingAs($this->owner)
        ->post(route('school.roles.store'), ['name' => 'Front desk', 'template' => 'front_office'])
        ->assertSessionHasNoErrors();

    $role = Role::query()->where('name', 'Front desk')->firstOrFail();

    expect($role->source_template_key)->toBe('front_office')
        ->and($role->permissionKeys())->not->toBeEmpty()
        ->and(AuditEvent::query()->where('action', 'roles.created')->exists())->toBeTrue();
});

it('needs the critical confirmation to switch on a critical permission', function () {
    $role = app(CreateRole::class)->handle($this->owner, 'Office', ['structure.view']);
    $payload = ['permissions' => ['structure.view', 'students.issue_tc']];

    $this->actingAs($this->owner)
        ->put(route('school.roles.permissions', $role), $payload)
        ->assertSessionHasErrors('roles');
    expect($role->permissionKeys())->toBe(['structure.view']);

    $this->put(route('school.roles.permissions', $role), [...$payload, 'confirm_critical' => true])
        ->assertSessionHasNoErrors();
    expect($role->fresh()->permissionKeys())->toBe(['structure.view', 'students.issue_tc']);
});

it('assigns a role to a campus, rejects a duplicate and removes it', function () {
    $campus = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
    $role = app(CreateRole::class)->handle($this->owner, 'Office', ['structure.view']);
    [, $staff] = rolesScreenPerson([]);
    $payload = ['scope_type' => 'campus', 'campuses' => [$campus->ulid]];

    $this->actingAs($this->owner)
        ->post(route('school.roles.assign', [$role, $staff]), $payload)
        ->assertSessionHasNoErrors();

    $assignment = RoleAssignment::query()->where('staff_id', $staff->id)->firstOrFail();
    expect($assignment->toScope()->campusUlids())->toBe([$campus->ulid]);

    $this->post(route('school.roles.assign', [$role, $staff]), $payload)->assertSessionHasErrors('roles');

    $this->delete(route('school.roles.assignments.destroy', $assignment))->assertSessionHasNoErrors();
    expect(RoleAssignment::query()->where('staff_id', $staff->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'roles.assignment_removed')->exists())->toBeTrue();
});

it('assigns a wing and sections, and validates the scope input', function () {
    $year = app(CreateAcademicYear::class)->handle($this->owner, '2026-27', '2026-04-01', '2027-03-31');
    $campus = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
    $class = app(CreateSchoolClass::class)->handle($this->owner, 'Class 1', 1);
    $section = app(CreateSection::class)->handle($this->owner, $year, $campus, $class, 'A', 40);
    $role = app(CreateRole::class)->handle($this->owner, 'Office', ['structure.view']);
    [, $staff] = rolesScreenPerson([]);

    $this->actingAs($this->owner);
    $url = route('school.roles.assign', [$role, $staff]);

    $this->post($url, ['scope_type' => 'wing', 'campuses' => [$campus->ulid], 'from_level' => 3, 'to_level' => 1])
        ->assertSessionHasErrors('to_level');
    $this->post($url, ['scope_type' => 'campus', 'campuses' => []])->assertSessionHasErrors('campuses');
    $this->post($url, ['scope_type' => 'sections', 'sections' => ['01unknownulid']])->assertSessionHasErrors('sections.0');

    $this->post($url, ['scope_type' => 'wing', 'campuses' => [$campus->ulid], 'from_level' => 1, 'to_level' => 3])
        ->assertSessionHasNoErrors();
    $this->post($url, ['scope_type' => 'sections', 'sections' => [$section->ulid]])->assertSessionHasNoErrors();

    expect(RoleAssignment::query()->where('staff_id', $staff->id)->count())->toBe(2);
});

it('does not let an assigner give a role with permissions they lack or more scope than theirs', function () {
    $main = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
    $north = app(CreateCampus::class)->handle($this->owner, 'North Campus', 'north');
    $criticalRole = app(CreateRole::class)->handle($this->owner, 'Registrar', ['structure.view', 'students.issue_tc'], null, true);
    $viewRole = app(CreateRole::class)->handle($this->owner, 'Viewer', ['structure.view']);
    [$assigner] = rolesScreenPerson(['roles.view', 'roles.assign', 'structure.view'], Scope::campuses([$main->ulid]));
    [, $target] = rolesScreenPerson([]);

    $this->actingAs($assigner);

    $this->post(route('school.roles.assign', [$criticalRole, $target]), ['scope_type' => 'campus', 'campuses' => [$main->ulid]])
        ->assertSessionHasErrors('roles');
    $this->post(route('school.roles.assign', [$viewRole, $target]), ['scope_type' => 'campus', 'campuses' => [$north->ulid]])
        ->assertSessionHasErrors('roles');
    $this->post(route('school.roles.assign', [$viewRole, $target]), ['scope_type' => 'campus', 'campuses' => [$main->ulid]])
        ->assertSessionHasNoErrors();
});

it('keeps the last owner', function () {
    $assignment = RoleAssignment::query()->firstOrFail();

    $this->actingAs($this->owner)
        ->delete(route('school.roles.assignments.destroy', $assignment))
        ->assertSessionHasErrors('roles');

    expect(RoleAssignment::query()->count())->toBe(1);
});

it('offers a template update, keeps the school\'s own edits and applies it on request', function () {
    $role = app(CreateRole::class)->handle($this->owner, 'Coordinator', [], 'coordinator');
    $role->forceFill(['source_template_version' => 1])->save();
    $role->permissions()->firstOrCreate(['permission_key' => 'structure.view']);

    $this->actingAs($this->owner)
        ->get(route('school.roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('updates', 1)
            ->where('updates.0.role_ulid', $role->ulid)
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('ulid', $role->ulid)['update_pending'] === true));

    $this->post(route('school.roles.template-update', $role), ['confirm_critical' => true])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->source_template_version)->toBeGreaterThan(1)
        ->and($role->fresh()->permissionKeys())->toContain('structure.view')
        ->and(AuditEvent::query()->where('action', 'roles.template_update_applied')->exists())->toBeTrue();

    $this->post(route('school.roles.template-update', $role))->assertSessionHasErrors('roles');
});

it('refuses template updates without roles.manage', function () {
    $role = app(CreateRole::class)->handle($this->owner, 'Coordinator', [], 'coordinator');
    [$viewer] = rolesScreenPerson(['roles.view']);

    $this->actingAs($viewer)->post(route('school.roles.template-update', $role))->assertForbidden();
});
