<?php

use App\Domain\Access\Models\RoleTemplate;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(function () {
    $this->setUpStudentSchool();

    $this->pastYear = app(CreateAcademicYear::class)->handle($this->owner, '2025-26', '2025-04-01', '2026-03-31');
    $this->pastSection = app(CreateSection::class)->handle(
        $this->owner,
        $this->pastYear,
        $this->main,
        SchoolClass::query()->where('level', 1)->firstOrFail(),
        'P',
        40,
    );

    // Aditya was in 1-P last year and is in 1-A now; Riya joined this year.
    $this->aditya = $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Nair');

    DB::connection('tenant')->table('enrollments')->insert([
        'student_id' => $this->aditya->id,
        'academic_year_id' => $this->pastYear->id,
        'section_id' => $this->pastSection->id,
        'roll_number' => 7,
        'started_on' => '2025-04-01',
        'ended_on' => '2026-03-31',
        'result' => 'promoted',
        'is_current' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('lists a past session separately from the current one', function () {
    $this->actingAs($this->owner);
    $this->put(route('school.session.select'), ['session' => $this->pastYear->ulid])->assertSessionHasNoErrors();

    $this->get(route('students.index'))->assertInertia(fn (Assert $page) => $page
        ->has('students.rows', 1)
        ->where('students.rows.0.name', 'Aditya Gill')
        ->where('students.rows.0.class_label', 'Class 1-P')
        ->where('students.rows.0.roll_number', 7)
        ->where('session.name', '2025-26'));

    $this->put(route('school.session.select'), ['session' => ''])->assertSessionHasNoErrors();

    $this->get(route('students.index'))->assertInertia(fn (Assert $page) => $page
        ->has('students.rows', 2)
        ->where('session', null));
});

it('shows the profile as it was in the past session, read only', function () {
    $this->actingAs($this->owner);
    $this->put(route('school.session.select'), ['session' => $this->pastYear->ulid]);

    $this->get(route('students.show', $this->aditya))->assertInertia(fn (Assert $page) => $page
        ->where('student.class_label', 'Class 1-P')
        ->where('student.roll_number', 7)
        ->where('student.session_name', '2025-26')
        ->where('student.abilities.manage', false)
        ->where('student.edit', null));

    $this->get(route('students.show', $this->riya))->assertNotFound();
});

it('keeps past sessions from people without the permission', function () {
    $teacher = $this->staffWith(['students.view']);

    $this->actingAs($teacher)->put(route('school.session.select'), ['session' => $this->pastYear->ulid])
        ->assertSessionHasErrors('session');

    // A stale or forged session value in the browser session is ignored too.
    $this->withSession(['school.session' => $this->pastYear->ulid])
        ->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page->has('students.rows', 2)->where('session', null)->where('sessionSwitcher', null));
});

it('offers the switcher only to people holding the permission', function () {
    $viewer = $this->staffWith(['students.view', 'students.view_past_sessions']);

    $this->actingAs($viewer)->get(route('students.index'))->assertInertia(fn (Assert $page) => $page
        ->has('sessionSwitcher.sessions', 2)
        ->where('sessionSwitcher.sessions.0.is_current', true));

    $this->actingAs($this->staffWith(['students.view']))->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sessionSwitcher', null));
});

it('rejects an unknown session', function () {
    $this->actingAs($this->owner)->put(route('school.session.select'), ['session' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'])
        ->assertSessionHasErrors('session');
});

it('puts the permission in the principal and coordinator templates only', function () {
    $has = fn (string $key) => in_array('students.view_past_sessions', RoleTemplate::query()->where('key', $key)->firstOrFail()->currentVersion()->permissionKeys(), true);

    expect($has('principal'))->toBeTrue()
        ->and($has('vice_principal'))->toBeTrue()
        ->and($has('coordinator'))->toBeTrue()
        ->and($has('class_teacher'))->toBeFalse()
        ->and($has('accountant'))->toBeFalse();
});
