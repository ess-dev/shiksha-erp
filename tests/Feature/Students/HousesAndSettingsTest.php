<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Models\House;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

it('shows houses with student counts and the number prefixes', function () {
    $this->makeStudent($this->main1A);

    $this->actingAs($this->owner)->get(route('students.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('students/settings')
            ->has('houses', 2)
            ->where('houses.0.name', 'Raman')
            ->where('houses.0.student_count', 1)
            ->where('houses.1.student_count', 0)
            ->where('numbering.admission_prefix', 'ADM')
            ->where('numbering.tc_prefix', 'TC')
            ->missing('houses.0.id'));
});

it('adds, renames and deactivates houses, never deleting them', function () {
    $this->actingAs($this->owner);

    $this->post(route('students.houses.store'), ['name' => 'Vivekananda'])->assertSessionHasNoErrors();
    $this->patch(route('students.houses.update', $this->raman), ['name' => 'Raman House', 'status' => 'inactive'])->assertSessionHasNoErrors();

    expect(House::query()->count())->toBe(3)
        ->and($this->raman->refresh()->name)->toBe('Raman House')
        ->and($this->raman->status)->toBe('inactive')
        ->and(House::query()->where('name', 'Vivekananda')->sole()->sort_order)->toBe(3);

    expect(AuditEvent::query()->whereIn('action', ['houses.house_created', 'houses.house_updated'])->count())->toBe(4);
});

it('refuses a repeated house name in any case, and bad input', function () {
    $this->actingAs($this->owner);

    $this->post(route('students.houses.store'), ['name' => 'raman'])->assertSessionHasErrors('house');
    $this->post(route('students.houses.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->patch(route('students.houses.update', $this->tagore), ['name' => 'RAMAN', 'status' => 'active'])->assertSessionHasErrors('house');
    $this->patch(route('students.houses.update', $this->tagore), ['name' => 'Tagore', 'status' => 'deleted'])->assertSessionHasErrors('status');

    expect(House::query()->count())->toBe(2);
});

it('reorders houses, and refuses an order that does not list each house once', function () {
    $this->actingAs($this->owner);

    $this->put(route('students.houses.reorder'), ['order' => [$this->tagore->ulid, $this->raman->ulid]])->assertSessionHasNoErrors();

    expect(House::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Tagore', 'Raman']);

    $this->put(route('students.houses.reorder'), ['order' => [$this->tagore->ulid]])->assertSessionHasErrors('house');
    $this->put(route('students.houses.reorder'), ['order' => [$this->tagore->ulid, $this->tagore->ulid]])->assertSessionHasErrors('house');

    expect(AuditEvent::query()->where('action', 'houses.houses_reordered')->count())->toBe(1);
});

it('lets a coordinator manage houses by default but not school settings', function () {
    $coordinator = $this->staffWith(['students.view', 'houses.manage'], Scope::wing([$this->main->ulid], 1, 5));

    $this->actingAs($coordinator)->post(route('students.houses.store'), ['name' => 'Gandhi'])->assertSessionHasNoErrors();
    $this->put(route('students.settings.numbering'), ['admission_prefix' => 'X', 'tc_prefix' => 'Y'])->assertForbidden();

    $viewer = $this->staffWith(['students.view']);

    $this->actingAs($viewer)->post(route('students.houses.store'), ['name' => 'Nehru'])->assertForbidden();
    $this->patch(route('students.houses.update', $this->raman), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    $this->put(route('students.houses.reorder'), ['order' => [$this->raman->ulid, $this->tagore->ulid]])->assertForbidden();
    $this->get(route('students.settings'))->assertOk();

    expect(House::query()->where('name', 'Nehru')->exists())->toBeFalse();
});

it('saves the number prefixes in capitals and uses them for the next numbers', function () {
    $this->actingAs($this->owner)
        ->put(route('students.settings.numbering'), ['admission_prefix' => 'gps', 'tc_prefix' => 'gps-tc'])
        ->assertSessionHasNoErrors();

    expect(app(SchoolSettingsQuery::class)->admissionPrefix())->toBe('GPS')
        ->and(app(SchoolSettingsQuery::class)->tcPrefix())->toBe('GPS-TC')
        ->and($this->makeStudent($this->main1A)->admission_number)->toBe('GPS/2026/0001');

    $this->put(route('students.settings.numbering'), ['admission_prefix' => 'bad prefix', 'tc_prefix' => 'TC'])->assertSessionHasErrors('admission_prefix');
    $this->put(route('students.settings.numbering'), ['admission_prefix' => '', 'tc_prefix' => 'TC'])->assertSessionHasErrors('admission_prefix');

    expect(AuditEvent::query()->where('action', 'settings.student_numbers_updated')->count())->toBe(1);
});

it('lets a student be admitted without any house', function () {
    House::query()->update(['status' => 'inactive']);

    $student = app(CreateStudent::class)->handle($this->owner, new NewStudent(
        'No', 'House', Gender::Female, '2019-01-01', BloodGroup::Unknown, null, 'Mohali', $this->main1A->ulid,
        [new NewGuardian('Some Parent', 'Mother', $this->nextPhone())],
    ));

    expect($student->house_id)->toBeNull();

    $this->actingAs($this->owner)->get(route('students.show', $student))
        ->assertInertia(fn (Assert $page) => $page->where('student.house_name', null)->has('options.houses', 0));
});
