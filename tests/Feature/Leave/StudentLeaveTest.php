<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Attendance\Actions\SaveRegister;
use App\Domain\Attendance\Contracts\AbsenceNotifier;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Queries\StudentAttendanceQuery;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Leave\Actions\DecideLeave;
use App\Domain\Leave\Contracts\LeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\UpdateLeaveSettings;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewGuardian;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class);

beforeEach(function () {
    Storage::fake('tenant');
    $this->enableModules(['attendance']);
    $this->setUpStudentSchool();

    $this->phone = $this->nextPhone();
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)]);
    $this->kabir = $this->makeStudent($this->main1A, 'Kabir', 'Mehta');

    $this->teacher = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma')->ulid);

    $this->notifier = new class implements LeaveNotifier
    {
        /** @var list<string> */
        public array $events = [];

        public function notify(string $event, string $leaveUlid, string $studentUlid): void
        {
            $this->events[] = $event;
        }
    };
    $this->app->instance(LeaveNotifier::class, $this->notifier);

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function lvSignIn(): void
{
    // A staff member acted in this test: start the parent's sign-in from a clean session.
    app('auth')->forgetGuards();
    test()->flushSession();

    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

/** The weekday that is `$count` school days after today (Monday to Friday), or today when it is one and $count is 0. */
function lvDay(int $count = 1): string
{
    $date = new DateTimeImmutable(CalendarDate::today());

    while ((int) $date->format('N') >= 6) {
        $date = $date->modify('+1 day');
    }

    for ($i = 0; $i < $count; $i++) {
        $date = $date->modify('+1 day');

        while ((int) $date->format('N') >= 6) {
            $date = $date->modify('+1 day');
        }
    }

    return $date->format('Y-m-d');
}

function lvApply(string $from, ?string $to = null, array $extra = [])
{
    return test()->post(route('parent.leave.store'), [
        'child' => test()->riya->ulid,
        'from_on' => $from,
        'to_on' => $to ?? $from,
        'reason_type' => 'sick',
        'reason' => 'Fever since last night; the doctor advised rest.',
        ...$extra,
    ]);
}

function lvMake(string $from, ?string $to = null): StudentLeaveRequest
{
    lvSignIn();
    lvApply($from, $to)->assertSessionHasNoErrors();

    return StudentLeaveRequest::query()->latest('id')->firstOrFail();
}

/** A second request inserted directly, for tests that already signed a staff member in. */
function lvInsert(string $from, ?string $to = null, string $status = 'pending'): StudentLeaveRequest
{
    $first = StudentLeaveRequest::query()->firstOrFail();

    return StudentLeaveRequest::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'academic_year_id' => $first->academic_year_id, 'student_id' => $first->student_id,
        'section_id' => $first->section_id, 'applied_by_guardian_id' => $first->applied_by_guardian_id, 'from_on' => $from, 'to_on' => $to ?? $from,
        'school_days' => 1, 'reason_type' => 'sick', 'reason' => 'Fever since last night.', 'status' => $status, 'applied_at' => now(),
    ]);
}

function lvDecide($actor, StudentLeaveRequest $leave, string $decision, ?string $note = null)
{
    return test()->actingAs($actor)->post(route('leave.decide', $leave->ulid), ['decision' => $decision, 'note' => $note]);
}

it('lets a parent apply for short leave, which waits with the class teacher', function () {
    lvSignIn();
    $day = lvDay(1);

    lvApply($day)->assertSessionHasNoErrors();

    $leave = StudentLeaveRequest::query()->firstOrFail();

    expect($leave->student_id)->toBe($this->riya->id)
        ->and($leave->section_id)->toBe($this->main1A->id)
        ->and($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->school_days)->toBe(1)
        ->and($leave->needs_coordinator)->toBeFalse()
        ->and($this->notifier->events)->toBe(['applied'])
        ->and(AuditEvent::query()->where('action', 'student_leave.applied')->count())->toBe(1);

    $this->get(route('parent.leave', ['child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/leave')
            ->where('child_first_name', 'Riya')
            ->where('form.escalation_days', 3)
            ->has('form.reasons', 6)
            ->where('requests.0.status', 'pending')
            ->where('requests.0.can_cancel', true)
            ->missing('requests.0.id'));
});

it('marks leave longer than the school limit as needing the coordinator, and the limit is a school setting', function () {
    lvSignIn();

    lvApply(lvDay(1), lvDay(4))->assertSessionHasNoErrors();
    expect(StudentLeaveRequest::query()->firstOrFail())->school_days->toBe(4)->needs_coordinator->toBeTrue();

    app(UpdateLeaveSettings::class)->handle($this->owner, 5);
    expect(AuditEvent::query()->where('action', 'settings.leave_updated')->count())->toBe(1);

    lvApply(lvDay(6), lvDay(9))->assertSessionHasNoErrors();
    expect(StudentLeaveRequest::query()->latest('id')->firstOrFail())->needs_coordinator->toBeFalse();
});

it('refuses leave the rules do not allow', function () {
    lvSignIn();
    $day = lvDay(1);
    $saturday = (new DateTimeImmutable($day))->modify('next saturday')->format('Y-m-d');

    lvApply((new DateTimeImmutable(CalendarDate::today()))->modify('-5 days')->format('Y-m-d'))->assertSessionHasErrors('leave');
    lvApply($day, (new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d'))->assertSessionHasErrors('leave');
    lvApply($saturday)->assertSessionHasErrors('leave');
    lvApply($day, (new DateTimeImmutable($day))->modify('+40 days')->format('Y-m-d'))->assertSessionHasErrors('leave');
    lvApply($day, null, ['reason' => 'Too short'])->assertSessionHasErrors('leave');
    lvApply($day, null, ['attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('leave');
    expect(StudentLeaveRequest::query()->count())->toBe(0);

    lvApply($day)->assertSessionHasNoErrors();
    lvApply($day)->assertSessionHasErrors('leave');
    expect(StudentLeaveRequest::query()->count())->toBe(1);
});

it('stores a certificate on the tenant disk and lets staff open it from a signed, audited link', function () {
    lvSignIn();
    lvApply(lvDay(1), null, ['attachment' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
    $leave = StudentLeaveRequest::query()->firstOrFail();

    Storage::disk('tenant')->assertExists((string) $leave->attachment_path);
    expect($leave->attachment_path)->toStartWith("tenants/{$this->school->ulid}/leave/");

    $this->actingAs($this->teacher)->get(route('leave.index'))->assertInertia(fn (Assert $page) => $page
        ->where('screen.items.0.attachment.name', 'certificate.pdf'));

    $url = URL::temporarySignedRoute('leave.attachment', now()->addMinutes(5), ['leave' => $leave->ulid]);
    $this->actingAs($this->teacher)->get($url)->assertOk();
    expect(AuditEvent::query()->where('action', 'student_leave.attachment_opened')->count())->toBe(1);

    $this->actingAs($this->teacher)->get(route('leave.attachment', $leave->ulid))->assertForbidden();
    $outsider = $this->staffWith(['leave.approve'], Scope::sections([$this->main2A->ulid]));
    $this->actingAs($outsider)->get($url)->assertNotFound();
});

it('lets the parent cancel an undecided request but not a settled one', function () {
    $pending = lvMake(lvDay(1));

    $this->post(route('parent.leave.cancel', $pending->ulid), ['child' => $this->riya->ulid])->assertSessionHasNoErrors();
    expect($pending->fresh()->status)->toBe(LeaveStatus::Cancelled);

    $approved = lvInsert(lvDay(2));
    app(DecideLeave::class)->handle($this->teacher, $approved->ulid, 'approve', null);

    $this->post(route('parent.leave.cancel', $approved->ulid), ['child' => $this->riya->ulid])->assertSessionHasErrors('leave');
    expect($approved->fresh()->status)->toBe(LeaveStatus::Approved);

    // Another family's request is not theirs.
    $other = StudentLeaveRequest::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'academic_year_id' => $approved->academic_year_id, 'student_id' => $this->kabir->id,
        'section_id' => $this->main1A->id, 'applied_by_guardian_id' => $approved->applied_by_guardian_id, 'from_on' => lvDay(3), 'to_on' => lvDay(3),
        'school_days' => 1, 'reason_type' => 'sick', 'reason' => 'Not your child at all.', 'status' => 'pending', 'applied_at' => now(),
    ]);
    $this->post(route('parent.leave.cancel', $other->ulid), ['child' => $this->riya->ulid])->assertNotFound();
});

it('lets the class teacher approve or reject short leave, and rejecting needs a reason', function () {
    $leave = lvMake(lvDay(1));

    $this->actingAs($this->teacher)->get(route('leave.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('leave/index')
        ->where('screen.review_count', 1)
        ->where('screen.items.0.student_name', 'Riya Sharma')
        ->where('screen.items.0.can_approve', true)
        ->where('screen.items.0.can_recommend', false)
        ->missing('screen.items.0.id'));

    lvDecide($this->teacher, $leave, 'reject', 'No')->assertSessionHasErrors('leave');
    lvDecide($this->teacher, $leave, 'reject', 'This clashes with the unit test.')->assertSessionHasNoErrors();
    expect($leave->fresh())->status->toBe(LeaveStatus::Rejected)->decision_note->toBe('This clashes with the unit test.');

    lvDecide($this->teacher, $leave, 'approve')->assertSessionHasErrors('leave');

    $second = lvInsert(lvDay(2));
    lvDecide($this->teacher, $second, 'approve', 'Get well soon!')->assertSessionHasNoErrors();

    expect($second->fresh())->status->toBe(LeaveStatus::Approved)
        ->and($this->notifier->events)->toContain('rejected', 'approved')
        ->and(AuditEvent::query()->whereIn('action', ['student_leave.approved', 'student_leave.rejected'])->count())->toBe(2);
});

it('sends long leave through a recommendation to someone who may decide it', function () {
    $leave = lvMake(lvDay(1), lvDay(5));
    $coordinator = $this->staffWith(['leave.approve', 'leave.approve_long'], Scope::wing([$this->main->ulid], 1, 1));

    $this->actingAs($this->teacher)->get(route('leave.index'))->assertInertia(fn (Assert $page) => $page
        ->where('screen.items.0.can_approve', false)
        ->where('screen.items.0.can_recommend', true)
        ->where('screen.items.0.can_reject', true));

    lvDecide($this->teacher, $leave, 'approve')->assertForbidden();
    lvDecide($this->teacher, $leave, 'recommend')->assertSessionHasErrors('leave');
    lvDecide($this->teacher, $leave, 'recommend', 'Good attendance otherwise.')->assertSessionHasNoErrors();
    expect($leave->fresh())->status->toBe(LeaveStatus::Recommended)->recommended_by_staff_id->not->toBeNull();

    // Out with the class teacher now; the coordinator sees it on Approvals and decides.
    $this->actingAs($this->teacher)->get(route('leave.index'))->assertInertia(fn (Assert $page) => $page->where('screen.review_count', 0));
    lvDecide($this->teacher, $leave, 'approve')->assertForbidden();

    $this->actingAs($coordinator)->get(route('approvals.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('student_leave.items.0.status', 'recommended')
        ->where('student_leave.items.0.recommended_by', 'Neha Verma')
        ->where('student_leave.items.0.can_approve', true)
        ->where('report_cards', null));

    lvDecide($coordinator, $leave, 'approve', 'Approved.')->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);

    expect($leave->fresh()->decided_by_staff_id)->not->toBeNull()->not->toBe($leave->fresh()->recommended_by_staff_id);
});

it('denies leave decisions outside the permission, the scope, the school status', function () {
    $leave = lvMake(lvDay(1));

    lvDecide($this->staffWith(['students.view']), $leave, 'approve')->assertNotFound();
    $this->actingAs($this->staffWith(['students.view']))->get(route('leave.index'))->assertForbidden();
    $this->actingAs($this->staffWith(['students.view']))->get(route('approvals.index'))->assertForbidden();
    lvDecide($this->staffWith(['leave.approve'], Scope::sections([$this->main2A->ulid])), $leave, 'approve')->assertNotFound();
    lvDecide($this->staffWith(['leave.approve'], Scope::wing([$this->main->ulid], 2, 2)), $leave, 'approve')->assertNotFound();
    expect($leave->fresh()->status)->toBe(LeaveStatus::Pending);

    $this->setSchoolStatus(TenantStatus::Suspended);
    lvDecide($this->teacher, $leave, 'approve')->assertForbidden();
    $this->setSchoolStatus(TenantStatus::Active);

    lvDecide($this->teacher, $leave, 'approve')->assertSessionHasNoErrors();
});

it('limits the leave settings to people who manage settings', function () {
    $this->actingAs($this->teacher)->put(route('leave.settings.update'), ['escalation_days' => 4])->assertForbidden();
    $this->actingAs($this->teacher)->get(route('leave.settings'))->assertForbidden();
    $this->actingAs($this->owner)->put(route('leave.settings.update'), ['escalation_days' => 0])->assertSessionHasErrors('escalation_days');
    $this->actingAs($this->owner)->get(route('leave.settings'))->assertInertia(fn (Assert $page) => $page->where('settings.escalation_days', 3));
});

it('shows approved leave on the register and sends no absence alert for it', function () {
    $day = lvDay(0);
    $leave = lvMake($day);
    lvDecide($this->teacher, $leave, 'approve')->assertSessionHasNoErrors();

    $alerts = new class implements AbsenceNotifier
    {
        /** @var list<string> */
        public array $absent = [];

        public function notify(string $sectionUlid, string $date, array $newlyAbsent, array $noLongerAbsent): void
        {
            $this->absent = [...$this->absent, ...$newlyAbsent];
        }
    };
    $this->app->instance(AbsenceNotifier::class, $alerts);

    $this->actingAs($this->teacher)->get(route('attendance.index', ['section' => $this->main1A->ulid, 'date' => $day]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.students', fn ($students) => collect($students)->firstWhere('name', 'Riya Sharma')['leave_reason'] === 'Sick'
                && collect($students)->firstWhere('name', 'Kabir Mehta')['leave_reason'] === null));

    app(SaveRegister::class)->handle($this->teacher, $this->main1A->ulid, $day, [
        $this->riya->ulid => AttendanceStatus::Absent,
        $this->kabir->ulid => AttendanceStatus::Absent,
    ], 'Late entry for the test.');

    expect($alerts->absent)->toBe([$this->kabir->ulid]);

    $days = app(StudentAttendanceQuery::class)->days($this->riya->id, $day, $day);
    expect($days[0]->state->value)->toBe('absent')->and($days[0]->leave_reason)->toBe('Sick');
});

it('keeps one school\'s leave out of another school', function () {
    lvMake(lvDay(1));

    expect($this->otherSchool->execute(fn () => StudentLeaveRequest::query()->count()))->toBe(0)
        ->and(StudentLeaveRequest::query()->count())->toBe(1);
});
