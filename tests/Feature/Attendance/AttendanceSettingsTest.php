<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Models\Holiday;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\School\Queries\SchoolSettingsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(function () {
    DB::connection('landlord')->table('tenant_modules')->insert([
        'tenant_id' => $this->school->id, 'module' => 'attendance', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->setUpStudentSchool();
});

it('starts with Monday to Friday and a 7 day window until the school changes them', function () {
    $this->actingAs($this->owner)->get(route('attendance.settings'))->assertInertia(fn (Assert $page) => $page
        ->where('calendar.working_days', [1, 2, 3, 4, 5])
        ->where('calendar.correction_window_days', 7)
        ->where('calendar.holidays', []));
});

it('saves working days and the correction window with an audit event', function () {
    $this->actingAs($this->owner)
        ->put(route('attendance.settings.update'), ['working_days' => [1, 2, 3, 4, 5, 6], 'correction_window_days' => 3])
        ->assertSessionHasNoErrors();

    expect(app(SchoolSettingsQuery::class)->attendanceWorkingDays())->toBe([1, 2, 3, 4, 5, 6])
        ->and(app(SchoolSettingsQuery::class)->attendanceCorrectionWindowDays())->toBe(3)
        ->and(AuditEvent::query()->where('action', 'settings.attendance_calendar_updated')->sole()->before['working_days'])->toBe([1, 2, 3, 4, 5]);

    $this->actingAs($this->owner)
        ->put(route('attendance.settings.update'), ['working_days' => [], 'correction_window_days' => 3])
        ->assertSessionHasErrors('working_days');

    $this->actingAs($this->owner)
        ->put(route('attendance.settings.update'), ['working_days' => [1], 'correction_window_days' => 90])
        ->assertSessionHasErrors('correction_window_days');

    expect(SchoolSetting::query()->where('key', SchoolSettingsQuery::ATTENDANCE_CORRECTION_DAYS)->value('value'))->toBe(3);
});

it('adds and removes holidays, one per date', function () {
    $this->actingAs($this->owner)
        ->post(route('attendance.holidays.store'), ['date' => '2026-11-08', 'name' => 'Diwali'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->post(route('attendance.holidays.store'), ['date' => '2026-11-08', 'name' => 'Diwali again'])
        ->assertSessionHasErrors('calendar');

    $this->actingAs($this->owner)
        ->post(route('attendance.holidays.store'), ['date' => '2026-02-30', 'name' => 'Not a day'])
        ->assertSessionHasErrors('date');

    expect(Holiday::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'settings.holiday_added')->count())->toBe(1);

    $holiday = Holiday::query()->firstOrFail();
    $this->actingAs($this->owner)->delete(route('attendance.holidays.destroy', $holiday))->assertSessionHasNoErrors();

    expect(Holiday::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'settings.holiday_removed')->count())->toBe(1);
});

it('keeps the settings and holidays to people who may manage settings', function () {
    $coordinator = $this->staffWith(['attendance.view', 'attendance.correct'], Scope::school());

    $this->actingAs($coordinator)->get(route('attendance.settings'))->assertForbidden();
    $this->actingAs($coordinator)->put(route('attendance.settings.update'), ['working_days' => [1], 'correction_window_days' => 1])->assertForbidden();
    $this->actingAs($coordinator)->post(route('attendance.holidays.store'), ['date' => '2026-11-08', 'name' => 'Diwali'])->assertForbidden();

    $this->actingAs($coordinator)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->where('register.can_manage_settings', false));
    $this->actingAs($this->owner)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->where('register.can_manage_settings', true));
});

it('sets the class teacher from the structure screen', function () {
    $staff = app(CreateStaffRecord::class)->handle($this->staff()->ulid, 'Neha Verma');

    $this->actingAs($this->owner)
        ->put(route('school.structure.sections.class-teacher', $this->main1A), ['staff' => $staff->ulid])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->get(route('school.structure.index'))->assertInertia(fn (Assert $page) => $page
        ->where('staffOptions', fn ($options) => collect($options)->pluck('name')->contains('Neha Verma'))
        ->where('sections.data', fn ($sections) => collect($sections)->contains(fn ($section) => $section['ulid'] === $this->main1A->ulid
            && ($section['class_teacher']['name'] ?? null) === 'Neha Verma')));

    $this->actingAs($this->owner)
        ->put(route('school.structure.sections.class-teacher', $this->main1A), ['staff' => 'not-a-real-staff'])
        ->assertSessionHasErrors('calendar');

    $viewer = $this->staffWith(['structure.view'], Scope::school());
    $this->actingAs($viewer)
        ->put(route('school.structure.sections.class-teacher', $this->main1A), ['staff' => null])
        ->assertForbidden();
    $this->actingAs($viewer)->get(route('school.structure.index'))->assertInertia(fn (Assert $page) => $page->where('staffOptions', []));
});
