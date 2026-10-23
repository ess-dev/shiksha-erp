<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Admissions\Actions\AddEnquiry;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Models\Holiday;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Models\Student;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class);

beforeEach(function () {
    $this->enableModules(['attendance', 'admissions']);
    $this->setUpStudentSchool();

    // Every weekday counts as a school day, so tests do not depend on the day they run.
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5, 6, 7], 7);

    $this->aditya = $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $this->bhavna = $this->makeStudent($this->main1A, 'Bhavna', 'Rao');
    $this->nikhil = $this->makeStudent($this->north1A, 'Nikhil', 'Kaur');
    $this->teacherName = 'Neha Verma';
    $this->teacherStaff = app(CreateStaffRecord::class)->handle($this->staff()->ulid, $this->teacherName);
});

function dashboardDay(int $daysAgo): string
{
    return (new DateTimeImmutable(CalendarDate::today()))->modify("-{$daysAgo} days")->format('Y-m-d');
}

/**
 * Saves a register directly (the dashboard only reads registers).
 *
 * @param  array<int, string>  $marks  student key => status
 */
function dashboardRegister(Section $section, string $date, array $marks): void
{
    $register = AttendanceRegister::query()->create([
        'ulid' => strtolower((string) Str::ulid()),
        'section_id' => $section->id,
        'attendance_on' => $date,
        'saved_by_staff_id' => test()->teacherStaff->id,
        'first_saved_at' => now(),
        'last_saved_at' => now(),
    ]);

    foreach ($marks as $student => $status) {
        AttendanceMark::query()->create(['attendance_register_id' => $register->id, 'student_id' => $student, 'status' => $status]);
    }
}

function dashboardEnquiry(string $campusUlid, string $first): AdmissionApplication
{
    $test = test();

    return app(AddEnquiry::class)->handle($test->owner, new NewEnquiry(
        firstName: $first, lastName: 'Bansal', gender: 'female', bornOn: null,
        classUlid: DB::connection('tenant')->table('school_classes')->where('id', $test->main1A->school_class_id)->value('ulid'),
        campusUlid: $campusUlid, parentName: 'Komal Bansal', parentRelation: 'Mother',
        phone: $test->nextPhone(), source: AdmissionSource::Website,
    ));
}

function primaryPhone(Student $student): string
{
    return (string) DB::connection('tenant')->table('guardians')
        ->join('guardian_student', 'guardian_student.guardian_id', '=', 'guardians.id')
        ->where('guardian_student.student_id', $student->id)
        ->value('guardians.phone');
}

it('shows today\'s attendance, unmarked sections and absentees with ulids only', function () {
    dashboardRegister($this->main1A, CalendarDate::today(), [$this->aditya->id => 'present', $this->bhavna->id => 'absent']);

    $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.academic_year_name', '2026-27')
        ->where('dashboard.campus_name', null)
        ->where('dashboard.attendance.closed_reason', null)
        ->where('dashboard.attendance.percent_tenths', 500)
        ->where('dashboard.attendance.absent', 1)
        ->where('dashboard.attendance.sections_marked', 1)
        ->where('dashboard.attendance.sections_total', 4)
        ->where('dashboard.attendance.unmarked_sections', ['Class 1-B (Main Campus)', 'Class 1-A (North Campus)', 'Class 2-A (Main Campus)'])
        ->where('dashboard.attendance.trend', fn ($trend) => collect($trend)->last()['date'] === CalendarDate::today()
            && collect($trend)->last()['percent_tenths'] === 500)
        ->has('dashboard.attendance.absentees', 1)
        ->where('dashboard.attendance.absentees.0.student_ulid', $this->bhavna->ulid)
        ->where('dashboard.attendance.absentees.0.name', 'Bhavna Rao')
        ->where('dashboard.attendance.absentees.0.section_label', 'Class 1-A')
        ->where('dashboard.attendance.absentees.0.guardian_phone', primaryPhone($this->bhavna))
        ->where('dashboard.attendance.absentees.0.days_in_a_row', 1)
        ->where('dashboard.attendance.absentees.0.marked_by', 'Neha Verma'));

    expect(json_encode($response->viewData('page')['props']['dashboard']))->not->toContain('"id"');
});

it('counts absences in a row and frequent absentees over past school days', function () {
    foreach ([3, 2, 1] as $daysAgo) {
        dashboardRegister($this->main1A, dashboardDay($daysAgo), [$this->aditya->id => 'present', $this->bhavna->id => 'absent']);
    }
    dashboardRegister($this->main1A, dashboardDay(4), [$this->aditya->id => 'absent', $this->bhavna->id => 'present']);
    dashboardRegister($this->main1A, CalendarDate::today(), [$this->aditya->id => 'present', $this->bhavna->id => 'absent']);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attendance.absentees.0.days_in_a_row', 4)
            ->where('dashboard.attendance.frequent_absent_count', 1)
            ->where('dashboard.attendance.frequent_absentees', ['Bhavna Rao'])
            ->where('dashboard.attendance.frequent_absence_threshold', 3)
            ->where('dashboard.attendance.frequent_absence_days', 10)
            ->has('dashboard.attendance.trend', 11)
            ->where('dashboard.attendance.trend.9.percent_tenths', 500)
            ->where('dashboard.attendance.trend.0.percent_tenths', null));
});

it('limits attendance to the viewer\'s scope and the top-bar campus, and masks phones without view_sensitive', function () {
    dashboardRegister($this->main1A, CalendarDate::today(), [$this->bhavna->id => 'absent']);
    dashboardRegister($this->north1A, CalendarDate::today(), [$this->nikhil->id => 'absent']);

    $northViewer = $this->staffWith(['attendance.view'], Scope::campuses([$this->north->ulid]));

    $this->actingAs($northViewer)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attendance.sections_total', 1)
            ->where('dashboard.attendance.absent', 1)
            ->has('dashboard.attendance.absentees', 1)
            ->where('dashboard.attendance.absentees.0.student_ulid', $this->nikhil->ulid)
            ->where('dashboard.attendance.absentees.0.guardian_phone', PhoneNumber::mask(primaryPhone($this->nikhil)))
            ->where('dashboard.admissions', null));

    $this->actingAs($this->owner)
        ->withSession(['school.campus' => $this->main->ulid])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.campus_name', 'Main Campus')
            ->where('dashboard.attendance.sections_total', 3)
            ->where('dashboard.attendance.absentees.0.student_ulid', $this->bhavna->ulid));
});

it('opens for staff without attendance or admissions permissions with those blocks empty', function () {
    $clerk = $this->staffWith(['students.view']);

    $this->actingAs($clerk)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attendance', null)
            ->where('dashboard.admissions', null));
});

it('leaves out modules that are not in the plan', function () {
    $this->disableModule('attendance');
    $this->disableModule('admissions');

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attendance', null)
            ->where('dashboard.admissions', null));
});

it('says when today is not a school day', function () {
    Holiday::query()->create(['ulid' => strtolower((string) Str::ulid()), 'holiday_on' => CalendarDate::today(), 'name' => 'Founders Day']);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attendance.closed_reason', 'Founders Day')
            ->where('dashboard.attendance.percent_tenths', null)
            ->where('dashboard.attendance.unmarked_sections', [])
            ->where('dashboard.holidays.0.name', 'Founders Day')
            ->where('dashboard.holidays.0.days_away', 0));
});

it('builds the admissions funnel from stages reached, within the admissions scope', function () {
    dashboardEnquiry($this->main->ulid, 'Advika');
    dashboardEnquiry($this->main->ulid, 'Ira');
    $enrolled = dashboardEnquiry($this->main->ulid, 'Myra');
    dashboardEnquiry($this->north->ulid, 'Tara');
    DB::connection('tenant')->table('admission_applications')->where('id', $enrolled->id)->update(['stage' => 'enrolled']);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.admissions.open_applications', 3)
            ->where('dashboard.admissions.new_this_week', 4)
            ->where('dashboard.admissions.conversion_percent', 25)
            ->where('dashboard.admissions.stages.0.count', 4)
            ->where('dashboard.admissions.stages.3.count', 1)
            ->where('dashboard.admissions.stages.4.count', 1));

    $mainOfficer = $this->staffWith(['admissions.view'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOfficer)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.admissions.stages.0.count', 3)
            ->where('dashboard.attendance', null));
});

it('lists upcoming holidays and joins consecutive days with the same name', function () {
    foreach ([[5, 'Diwali vacation'], [6, 'Diwali vacation'], [7, 'Diwali vacation'], [10, 'Guru Nanak Jayanti'], [40, 'Too far']] as [$ahead, $name]) {
        Holiday::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'holiday_on' => (new DateTimeImmutable(CalendarDate::today()))->modify("+{$ahead} days")->format('Y-m-d'),
            'name' => $name,
        ]);
    }

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.holidays', 2)
            ->where('dashboard.holidays.0.name', 'Diwali vacation')
            ->where('dashboard.holidays.0.days_away', 5)
            ->where('dashboard.holidays.0.ends_on', (new DateTimeImmutable(CalendarDate::today()))->modify('+7 days')->format('Y-m-d'))
            ->where('dashboard.holidays.1.ends_on', null));
});

it('sends guests to sign in and shows the subscription page for a suspended school', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('keeps one school\'s registers out of another school\'s dashboard', function () {
    dashboardRegister($this->main1A, CalendarDate::today(), [$this->bhavna->id => 'absent']);

    expect($this->otherSchool->execute(fn () => AttendanceRegister::query()->count()))->toBe(0)
        ->and(AttendanceRegister::query()->count())->toBe(1);
});
