<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\RoleTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

/** @return list<string> */
function templatePermissions(string $key): array
{
    return RoleTemplate::query()->where('key', $key)->firstOrFail()->currentVersion()->permissionKeys();
}

it('catalogues the student permissions as core, with the TC as critical', function () {
    foreach (['students.view', 'students.manage', 'students.view_sensitive', 'students.issue_tc', 'students.import', 'students.export', 'students.documents', 'students.link_siblings', 'houses.manage'] as $key) {
        expect(Permission::query()->where('key', $key)->value('module'))->toBe('core', $key);
    }

    expect(Permission::query()->where('key', 'students.issue_tc')->sole()->risk->value)->toBe('critical')
        ->and(Permission::query()->where('key', 'students.export')->sole()->risk->value)->toBe('sensitive');
});

it('makes student permissions available without any module row, because students are core', function () {
    expect(DB::connection('landlord')->table('tenant_modules')->where('tenant_id', $this->school->id)->count())->toBe(0);

    $this->actingAs($this->owner)->get(route('students.index'))->assertOk();
});

it('gives the principal every student permission and the coordinator the working set', function () {
    $new = ['students.manage', 'students.view_sensitive', 'students.issue_tc', 'students.import', 'students.export', 'students.documents', 'students.link_siblings', 'houses.manage'];

    expect(templatePermissions('principal'))->toContain(...$new);

    $coordinator = templatePermissions('coordinator');

    expect($coordinator)->toContain('students.view', 'students.manage', 'students.view_sensitive', 'students.import', 'students.export', 'students.documents', 'students.link_siblings', 'houses.manage')
        ->and($coordinator)->not->toContain('students.issue_tc')
        ->and($coordinator)->not->toContain('roles.manage', 'settings.manage');

    expect(templatePermissions('vice_principal'))->toContain('students.manage', 'students.link_siblings')
        ->and(templatePermissions('vice_principal'))->not->toContain('students.issue_tc')
        ->and(templatePermissions('admission_officer'))->toContain('students.manage', 'students.import', 'students.documents')
        ->and(templatePermissions('admission_officer'))->not->toContain('students.export', 'students.link_siblings', 'students.issue_tc')
        ->and(templatePermissions('accountant'))->not->toContain('students.manage')
        ->and(templatePermissions('class_teacher'))->not->toContain('students.manage', 'students.view_sensitive');
});

it('answers the authorization matrix for a coordinator made from the template', function () {
    $identity = $this->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Anjali Khanna');
    $role = app(CreateRole::class)->handle($this->owner, 'Coordinator', [], 'coordinator');
    app(AssignRole::class)->handle($this->owner, $staff, $role, Scope::wing([$this->main->ulid], 1, 1));

    $inside = new ScopeTarget($this->main->ulid, 1, $this->main1A->ulid);
    $otherCampus = new ScopeTarget($this->north->ulid, 1, $this->north1A->ulid);
    $otherClass = new ScopeTarget($this->main->ulid, 2, $this->main2A->ulid);
    $can = fn (string $permission, ScopeTarget $target) => Gate::forUser($identity)->allows($permission, [$target]);

    foreach (['students.view', 'students.manage', 'students.import', 'students.link_siblings', 'students.export'] as $permission) {
        expect($can($permission, $inside))->toBeTrue($permission)
            ->and($can($permission, $otherCampus))->toBeFalse($permission)
            ->and($can($permission, $otherClass))->toBeFalse($permission);
    }

    expect($can('students.issue_tc', $inside))->toBeFalse()
        ->and(Gate::forUser($identity)->allows('houses.manage'))->toBeTrue();
});

it('lets the principal template holder issue a TC but a coordinator never', function () {
    $student = $this->makeStudent($this->main1A);
    $coordinator = $this->staffWith(['students.view', 'students.manage', 'students.link_siblings']);

    expect(Gate::forUser($this->owner)->allows('students.issue_tc'))->toBeTrue()
        ->and(Gate::forUser($coordinator)->allows('students.issue_tc'))->toBeFalse()
        ->and($student->refresh()->tc_number)->toBeNull();
});

it('refuses student pages and actions to someone with no student permission at all', function () {
    $student = $this->makeStudent($this->main1A);
    $outsider = $this->staffWith(['staff.view']);

    $this->actingAs($outsider);

    $this->get(route('students.index'))->assertForbidden();
    $this->get(route('students.show', $student))->assertForbidden();
    $this->get(route('students.export'))->assertForbidden();
    $this->get(route('students.settings'))->assertForbidden();
    $this->postJson(route('students.import.preview'), [])->assertForbidden();
    $this->post(route('students.section', $student), ['section' => $this->main1B->ulid])->assertForbidden();
});
