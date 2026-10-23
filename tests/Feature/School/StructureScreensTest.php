<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\Subject;
use App\Tenancy\Tenant;
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

/** A staff identity with a role holding the permissions over a scope. */
function screenStaff(array $permissions, ?Scope $scope = null): Identity
{
    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Staff Member');
    $role = app(CreateRole::class)->handle(test()->owner, 'Role '.uniqid(), $permissions);
    app(AssignRole::class)->handle(test()->owner, $staff, $role, $scope ?? Scope::school());

    return $identity;
}

/** @return array{year: AcademicYear, main: Campus, north: Campus, one: SchoolClass} */
function screenStructure(): array
{
    $owner = test()->owner;

    $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
    $main = app(CreateCampus::class)->handle($owner, 'Main Campus', 'main');
    $north = app(CreateCampus::class)->handle($owner, 'North Campus', 'north');
    $one = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);

    foreach ([$main, $north] as $campus) {
        app(CreateSection::class)->handle($owner, $year, $campus, $one, 'A', 40);
    }

    return compact('year', 'main', 'north', 'one');
}

it('shows the structure page with only ulids to someone who may view it', function () {
    $s = screenStructure();

    $this->actingAs(screenStaff(['structure.view']))
        ->get(route('school.structure.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/structure')
            ->has('campuses', 2)
            ->has('classes', 1)
            ->has('sections.data', 2)
            ->where('sections.data.0.campus.ulid', fn (string $ulid) => in_array($ulid, [$s['main']->ulid, $s['north']->ulid], true))
            ->missing('sections.data.0.id')
            ->missing('campuses.0.id')
            ->where('auth.abilities.permissions', ['structure.view']));
});

it('limits the sections listed to the viewer\'s campus scope', function () {
    $s = screenStructure();
    $viewer = screenStaff(['structure.view'], Scope::campuses([$s['main']->ulid]));

    $this->actingAs($viewer)
        ->get(route('school.structure.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.data', 1)
            ->where('sections.data.0.campus.ulid', $s['main']->ulid)
            ->has('campuses', 1));
});

it('refuses the page without structure.view and to guests', function () {
    $this->get(route('school.structure.index'))->assertRedirect(route('login'));

    $this->actingAs(screenStaff(['staff.view']))
        ->get(route('school.structure.index'))
        ->assertForbidden();
});

it('lets a manager add every kind of record and audits each', function () {
    $this->actingAs($this->owner);

    $this->post(route('school.structure.academic-years.store'), ['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'])
        ->assertSessionHasNoErrors();
    $this->post(route('school.structure.campuses.store'), ['name' => 'Main Campus', 'code' => 'main'])
        ->assertSessionHasNoErrors();
    $this->post(route('school.structure.classes.store'), ['name' => 'Class 5', 'level' => 5])
        ->assertSessionHasNoErrors();
    $this->post(route('school.structure.subjects.store'), ['name' => 'Mathematics', 'code' => 'mat'])
        ->assertSessionHasNoErrors();

    $year = AcademicYear::query()->firstOrFail();
    $campus = Campus::query()->firstOrFail();
    $class = SchoolClass::query()->firstOrFail();

    $this->post(route('school.structure.sections.store'), [
        'academic_year' => $year->ulid, 'campus' => $campus->ulid, 'class' => $class->ulid, 'name' => 'a', 'capacity' => 35,
    ])->assertSessionHasNoErrors();

    $this->patch(route('school.structure.academic-years.current', $year))->assertSessionHasNoErrors();
    $this->patch(route('school.structure.campuses.update', $campus), ['name' => 'Main', 'status' => 'inactive'])
        ->assertSessionHasNoErrors();

    expect(Subject::query()->count())->toBe(1)
        ->and(Section::query()->firstOrFail()->name)->toBe('A')
        ->and($year->fresh()->is_current)->toBeTrue()
        ->and($campus->fresh()->status)->toBe('inactive');

    foreach (['academic_year_created', 'campus_created', 'class_created', 'subject_created', 'section_created', 'academic_year_made_current', 'campus_updated'] as $action) {
        expect(AuditEvent::query()->where('action', 'structure.'.$action)->exists())->toBeTrue($action);
    }
});

it('shows a domain rule as a form error instead of failing', function () {
    $this->actingAs($this->owner);
    $payload = ['name' => 'Class 5', 'level' => 5];

    $this->post(route('school.structure.classes.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('school.structure.classes.store'), $payload)->assertSessionHasErrors('structure');

    expect(SchoolClass::query()->count())->toBe(1);
});

it('validates the input', function () {
    $this->actingAs($this->owner);

    $this->post(route('school.structure.academic-years.store'), ['name' => 'x', 'starts_on' => '2027-03-31', 'ends_on' => '2026-04-01'])
        ->assertSessionHasErrors('ends_on');
    $this->post(route('school.structure.campuses.store'), ['name' => '', 'code' => 'a b'])
        ->assertSessionHasErrors(['name', 'code']);
    $this->post(route('school.structure.classes.store'), ['name' => 'Class', 'level' => 99])
        ->assertSessionHasErrors('level');
    $this->post(route('school.structure.sections.store'), ['academic_year' => 'nope', 'campus' => 'nope', 'class' => 'nope', 'name' => 'A'])
        ->assertSessionHasErrors(['academic_year', 'campus', 'class']);
});

it('refuses changes to someone who can only view, or manages only one campus', function () {
    $s = screenStructure();
    $viewer = screenStaff(['structure.view']);

    $this->actingAs($viewer)
        ->post(route('school.structure.campuses.store'), ['name' => 'East', 'code' => 'east'])
        ->assertForbidden();
    $this->actingAs($viewer)
        ->patch(route('school.structure.academic-years.current', $s['year']))
        ->assertForbidden();

    $campusManager = screenStaff(['structure.view', 'structure.manage'], Scope::campuses([$s['main']->ulid]));
    $newSection = fn (Campus $campus) => [
        'academic_year' => $s['year']->ulid, 'campus' => $campus->ulid, 'class' => $s['one']->ulid, 'name' => 'B',
    ];

    $this->actingAs($campusManager)->post(route('school.structure.sections.store'), $newSection($s['north']))->assertForbidden();
    $this->actingAs($campusManager)->post(route('school.structure.sections.store'), $newSection($s['main']))->assertSessionHasNoErrors();
    $this->actingAs($campusManager)->post(route('school.structure.campuses.store'), ['name' => 'East', 'code' => 'east'])->assertForbidden();

    expect(Campus::query()->count())->toBe(2)
        ->and(Section::query()->count())->toBe(3);
});

it('is blocked while the school is suspended', function () {
    $this->school->forceFill(['status' => 'suspended'])->save();
    Tenant::forgetCurrent();
    $this->school->makeCurrent();

    $this->actingAs($this->owner)
        ->get(route('school.structure.index'))
        ->assertStatus(403);
});
