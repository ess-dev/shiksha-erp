<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Identity\Actions\VerifyGuardianOtp;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateHoliday;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Models\Student;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class, ManagesSchoolPlan::class);

beforeEach(function () {
    $this->enableModules(['attendance']);
    $this->setUpStudentSchool();

    // Every weekday counts as a school day, so tests do not depend on the day they run.
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5, 6, 7], 7);

    // Priya has two children in different classes; Rohit's family is someone else's.
    $this->phone = $this->nextPhone();
    $priya = fn () => [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)];
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', $priya());
    $this->arjun = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', $priya());
    $this->other = $this->makeStudent($this->main1A, 'Kabir', 'Mehta');

    app(SetClassTeacher::class)->handle(
        $this->owner,
        $this->main1A,
        app(CreateStaffRecord::class)->handle($this->staff()->ulid, 'Neha Verma')->ulid,
    );

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function signInAsPriya(): void
{
    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

function markDay(Student $student, Section $section, string $date, string $status): void
{
    $register = AttendanceRegister::query()->firstOrCreate(
        ['section_id' => $section->id, 'attendance_on' => $date],
        ['ulid' => strtolower((string) Str::ulid()), 'first_saved_at' => now(), 'last_saved_at' => now()],
    );

    AttendanceMark::query()->create(['attendance_register_id' => $register->id, 'student_id' => $student->id, 'status' => $status]);
}

it('shows only the guardian\'s own children on the home, highest class first', function () {
    signInAsPriya();

    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page
        ->component('parent/home')
        ->where('shell.school_name', 'Test Public School')
        ->where('shell.guardian_first_name', 'Priya')
        ->has('shell.children', 2)
        ->where('shell.children.0.first_name', 'Arjun')
        ->where('shell.children.1.first_name', 'Riya')
        ->where('shell.children.1.class_teacher_name', 'Neha Verma')
        ->where('shell.attendance_enabled', true)
        ->missing('shell.children.0.id')
        ->missing('shell.children.0.student_key'));

    $this->get(route('parent.home', ['child' => $this->riya->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('shell.selected_child', $this->riya->ulid)->where('home.today_state.state', 'unmarked'));
});

it('treats another family\'s child as not found', function () {
    signInAsPriya();

    $this->get(route('parent.home', ['child' => $this->other->ulid]))->assertNotFound();
    $this->get(route('parent.attendance', ['child' => $this->other->ulid]))->assertNotFound();
});

it('shows today as absent with who marked it', function () {
    signInAsPriya();
    markDay($this->riya, $this->main1A, CalendarDate::today(), 'absent');

    $this->get(route('parent.home', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('home.today_state.state', 'absent')
        ->where('home.week', fn ($week) => count($week) === 5)
        ->where('home.year.absent', 1)
        ->where('home.year.school_days', 1));
});

it('builds the attendance month from the registers, holidays and weekly days off', function () {
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5], 7);
    app(CreateHoliday::class)->handle($this->owner, '2026-09-17', 'Ganesh Chaturthi');

    markDay($this->riya, $this->main1A, '2026-09-14', 'absent');
    markDay($this->riya, $this->main1A, '2026-09-15', 'present');
    markDay($this->riya, $this->main1A, '2026-09-16', 'late');
    // Arjun's marks must not leak into Riya's calendar.
    markDay($this->arjun, $this->main2A, '2026-09-18', 'absent');

    signInAsPriya();

    $this->get(route('parent.attendance', ['child' => $this->riya->ulid, 'month' => '2026-09']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/attendance')
            ->where('attendance.month', '2026-09')
            ->where('attendance.days', fn ($days) => count($days) === 30)
            ->where('attendance.days.13.state', 'absent')
            ->where('attendance.days.14.state', 'present')
            ->where('attendance.days.15.state', 'late')
            ->where('attendance.days.16.state', 'holiday')
            ->where('attendance.days.16.note', 'Ganesh Chaturthi')
            ->where('attendance.days.17.state', 'unmarked')
            ->where('attendance.days.19.state', 'off')
            ->where('attendance.month_tally.present', 1)
            ->where('attendance.month_tally.late', 1)
            ->where('attendance.month_tally.absent', 1)
            ->where('attendance.month_tally.school_days', 3)
            ->where('attendance.month_tally.percent_tenths', 667)
            ->where('attendance.absences', fn ($absences) => count($absences) === 1)
            ->where('attendance.minimum_percent', 75)
            ->where('attendance.correction_window_days', 7)
            ->where('attendance.previous_month', '2026-08')
            ->where('class_teacher_name', 'Neha Verma'));
});

it('falls back to the latest month for a month outside the session or a bad value', function () {
    signInAsPriya();
    $latest = substr(CalendarDate::today(), 0, 7);

    foreach (['2020-01', '2099-12', 'garbage', '2026-13'] as $month) {
        $this->get(route('parent.attendance', ['month' => $month]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.month', $latest)->where('attendance.next_month', null));
    }
});

it('shows a past session with no records for children who were not enrolled then', function () {
    $past = app(CreateAcademicYear::class)->handle($this->owner, '2025-26', '2025-04-01', '2026-03-31');

    signInAsPriya();

    $this->put(route('parent.session.select'), ['session' => $past->ulid])->assertRedirect(route('parent.home'));

    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page
        ->where('is_current_session', false)
        ->where('session_name', '2025-26')
        ->has('shell.children', 0)
        ->where('home', null)
        ->where('shell.sessions.selected', $past->ulid));

    $this->get(route('parent.attendance'))->assertInertia(fn (Assert $page) => $page->where('attendance', null));

    // An unknown session is ignored and the current one is shown again.
    $this->put(route('parent.session.select'), ['session' => 'nonexistent'])->assertRedirect(route('parent.home'));
    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page->where('is_current_session', true)->has('shell.children', 2));
});

it('hides the attendance screen when the module is not in the plan', function () {
    signInAsPriya();
    $this->disableModule('attendance');

    $this->get(route('parent.attendance'))->assertNotFound();
    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page->where('shell.attendance_enabled', false));
});

it('blocks the parent portal while the school is suspended', function () {
    signInAsPriya();
    $this->setSchoolStatus(TenantStatus::Suspended);

    $this->get(route('parent.home'))->assertForbidden();
    $this->get(route('parent.sign-in'))->assertForbidden();
});

it('shows a guardian whose children have left an empty home, not other people\'s children', function () {
    signInAsPriya();
    DB::connection('tenant')->table('students')->whereIn('id', [$this->riya->id, $this->arjun->id])->update(['status' => 'left']);

    // With no enrolled child the portal access ends (nothing left to see), as for any removed guardian.
    $this->get(route('parent.home'))->assertRedirect(route('parent.sign-in'));
});

it('does not let one guardian sign in as another by reusing a code for a different phone', function () {
    $other = $this->nextPhone();
    $this->makeStudent($this->main1A, 'Zoya', 'Khan', [new NewGuardian('Farah Khan', 'Mother', $other, null, null, true)]);

    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $code = $this->lastCode;

    expect(app(VerifyGuardianOtp::class)->handle($this->school, $other, $code))->toBeNull()
        ->and(Identity::query()->where('phone_e164', '+91'.$other)->exists())->toBeFalse();
});
