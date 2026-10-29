<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
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

    $this->year = app(CreateAcademicYear::class)->handle($this->owner, '2026-27', '2026-04-01', '2027-03-31');
    $this->campus = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
});

function bulkPayload(array $overrides = []): array
{
    return array_merge([
        'academic_year' => test()->year->ulid,
        'campus' => test()->campus->ulid,
        'from_level' => 1,
        'to_level' => 5,
        'sections' => 3,
        'naming' => 'letters',
    ], $overrides);
}

it('creates every class in the range with its sections and audits it', function () {
    $this->actingAs($this->owner)
        ->post(route('school.structure.classes.bulk'), bulkPayload(['capacity' => 40]))
        ->assertSessionHasNoErrors();

    expect(SchoolClass::query()->orderBy('level')->pluck('name')->all())
        ->toBe(['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'])
        ->and(Section::query()->count())->toBe(15)
        ->and(Section::query()->where('capacity', 40)->count())->toBe(15)
        ->and(Section::query()->orderBy('name')->distinct()->pluck('name')->all())->toBe(['A', 'B', 'C'])
        ->and(AuditEvent::query()->where('action', 'structure.classes_created_in_bulk')->count())->toBe(1);
});

it('skips classes and sections that already exist and tops up the rest', function () {
    $two = app(CreateSchoolClass::class)->handle($this->owner, 'Class 2', 2);
    app(CreateSection::class)->handle($this->owner, $this->year, $this->campus, $two, 'A');

    $this->actingAs($this->owner)
        ->post(route('school.structure.classes.bulk'), bulkPayload(['from_level' => 2, 'to_level' => 3, 'sections' => 2]))
        ->assertSessionHasNoErrors();

    expect(SchoolClass::query()->count())->toBe(2)
        ->and(Section::query()->where('school_class_id', $two->id)->count())->toBe(2)
        ->and(Section::query()->count())->toBe(4);

    $this->post(route('school.structure.classes.bulk'), bulkPayload(['from_level' => 2, 'to_level' => 3, 'sections' => 2]))
        ->assertSessionHasNoErrors();

    expect(Section::query()->count())->toBe(4);
});

it('uses a different section count and number names where asked', function () {
    $this->actingAs($this->owner)
        ->post(route('school.structure.classes.bulk'), bulkPayload([
            'from_level' => 11, 'to_level' => 12, 'sections' => 2, 'naming' => 'numbers', 'overrides' => [12 => 4],
        ]))
        ->assertSessionHasNoErrors();

    $names = fn (int $level) => Section::query()
        ->whereHas('schoolClass', fn ($query) => $query->where('level', $level))
        ->orderBy('name')->pluck('name')->all();

    expect($names(11))->toBe(['1', '2'])
        ->and($names(12))->toBe(['1', '2', '3', '4']);
});

it('rejects a bad range, an unknown level and too many sections', function () {
    $this->actingAs($this->owner);

    $this->post(route('school.structure.classes.bulk'), bulkPayload(['from_level' => 5, 'to_level' => 2]))
        ->assertSessionHasErrors('to_level');
    $this->post(route('school.structure.classes.bulk'), bulkPayload(['from_level' => 99]))
        ->assertSessionHasErrors('from_level');
    $this->post(route('school.structure.classes.bulk'), bulkPayload(['sections' => 13]))
        ->assertSessionHasErrors('sections');

    expect(SchoolClass::query()->count())->toBe(0);
});

it('refuses staff without structure.manage and guests', function () {
    $this->post(route('school.structure.classes.bulk'), bulkPayload())->assertRedirect(route('login'));

    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Staff Member');
    $role = app(CreateRole::class)->handle($this->owner, 'Viewer', ['structure.view']);
    app(AssignRole::class)->handle($this->owner, $staff, $role, Scope::school());

    $this->actingAs($identity)->post(route('school.structure.classes.bulk'), bulkPayload())->assertForbidden();

    expect(SchoolClass::query()->count())->toBe(0);
});

it('sends the class ladder to managers only', function () {
    $this->actingAs($this->owner)
        ->get(route('school.structure.index'))
        ->assertInertia(fn (Assert $page) => $page->has('classLadder', count(config('shiksha.class_ladder'))));
});

it('is blocked while the school is suspended', function () {
    $this->school->forceFill(['status' => 'suspended'])->save();
    Tenant::forgetCurrent();
    $this->school->makeCurrent();

    $this->actingAs($this->owner)
        ->post(route('school.structure.classes.bulk'), bulkPayload())
        ->assertStatus(403);
});
