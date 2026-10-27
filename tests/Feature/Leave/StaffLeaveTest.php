<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Actions\SaveRegister;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Exceptions\AttendanceException;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\StaffLeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\UpdateStaffLeavePolicy;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma');

    // Neha leads Class 1-A; the accountant leads nothing.
    $this->teacher = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma')->ulid);
    $this->accountant = $this->staffWith(['students.view']);

    $this->coordinator = $this->staffWith(['staff.leave_approve', 'staff.view'], Scope::wing([$this->main->ulid], 1, 1));
    $this->principal = $this->staffWith(['staff.leave_approve', 'staff.view'], Scope::school());

    $this->notifier = new class implements StaffLeaveNotifier
    {
        /** @var list<string> */
        public array $events = [];

        public function notify(string $event, string $leaveUlid, string $staffUlid): void
        {
            $this->events[] = $event;
        }
    };
    $this->app->instance(StaffLeaveNotifier::class, $this->notifier);
});

/** The weekday that is `$count` school days after today (Monday to Friday), or today when it is one and $count is 0. */
function slDay(int $count = 1): string
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

function slApply(Identity $actor, string $from, ?string $to = null, array $extra = [])
{
    return test()->actingAs($actor)->post(route('my-leave.store'), [
        'leave_type' => 'casual',
        'from_on' => $from,
        'to_on' => $to ?? $from,
        'reason' => 'Family function in Ambala',
        ...$extra,
    ]);
}

function slMake(Identity $actor, string $from, ?string $to = null, array $extra = []): StaffLeaveRequest
{
    slApply($actor, $from, $to, $extra)->assertSessionHasNoErrors();

    return StaffLeaveRequest::query()->latest('id')->firstOrFail();
}

function slDecide(Identity $actor, StaffLeaveRequest $leave, string $decision, ?string $note = null, ?string $cover = null)
{
    return test()->actingAs($actor)->post(route('staff-leave.decide', $leave->ulid), ['decision' => $decision, 'note' => $note, 'cover' => $cover]);
}

function slStaffUlid(Identity $identity): string
{
    return app(StaffRefQuery::class)->forIdentity($identity)->ulid;
}

it('lets a staff member apply for leave, remembering the class they lead', function () {
    $leave = slMake($this->teacher, slDay(1), slDay(2));

    expect($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->half_days)->toBe(4)
        ->and($leave->section_id)->toBe($this->main1A->id)
        ->and($this->notifier->events)->toBe(['applied'])
        ->and(AuditEvent::query()->where('action', 'staff_leave.applied')->count())->toBe(1);

    $this->actingAs($this->teacher)->get(route('my-leave.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('leave/my-leave')
        ->where('leave.class_label', 'Class 1-A')
        ->where('leave.balances.0.type', 'casual')
        ->where('leave.balances.0.allowed_half_days', 24)
        ->where('leave.balances.0.pending_half_days', 4)
        ->where('leave.requests.0.can_cancel', true)
        ->missing('leave.requests.0.id'));

    $accountantLeave = slMake($this->accountant, slDay(1));
    expect($accountantLeave->section_id)->toBeNull();
});

it('counts a half day as half and limits the balance to the school policy', function () {
    $half = slMake($this->teacher, slDay(1), null, ['half_day' => true, 'to_on' => slDay(3)]);

    expect($half->half_days)->toBe(1)->and($half->to_on)->toBe($half->from_on);

    // Casual leave: 12 days by default. 11.5 are left; 12 days do not fit, 11.5 would.
    slApply($this->teacher, slDay(2), slDay(14))->assertSessionHasErrors('leave');

    app(UpdateStaffLeavePolicy::class)->handle($this->owner, [
        'casual' => ['days' => 40, 'document_after_days' => null],
        'sick' => ['days' => 10, 'document_after_days' => 2],
        'earned' => ['days' => 15, 'document_after_days' => null],
        'duty' => ['days' => null, 'document_after_days' => null],
    ]);
    expect(AuditEvent::query()->where('action', 'settings.staff_leave_policy_updated')->count())->toBe(1);

    slApply($this->teacher, slDay(2), slDay(14))->assertSessionHasNoErrors();
});

it('refuses staff leave the rules do not allow', function () {
    $day = slDay(1);
    $saturday = (new DateTimeImmutable($day))->modify('next saturday')->format('Y-m-d');

    slApply($this->teacher, (new DateTimeImmutable(CalendarDate::today()))->modify('-5 days')->format('Y-m-d'))->assertSessionHasErrors('leave');
    slApply($this->teacher, $saturday)->assertSessionHasErrors('leave');
    slApply($this->teacher, $day, null, ['reason' => 'No'])->assertSessionHasErrors('leave');
    slApply($this->teacher, $day, null, ['leave_type' => 'sabbatical'])->assertSessionHasErrors('leave_type');
    // Sick leave over 2 days needs a certificate.
    slApply($this->teacher, slDay(1), slDay(3), ['leave_type' => 'sick'])->assertSessionHasErrors('leave');
    slApply($this->teacher, slDay(1), slDay(3), ['leave_type' => 'sick', 'attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('leave');
    expect(StaffLeaveRequest::query()->count())->toBe(0);

    slApply($this->teacher, slDay(1), slDay(3), ['leave_type' => 'sick', 'attachment' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
    $leave = StaffLeaveRequest::query()->firstOrFail();
    Storage::disk('tenant')->assertExists((string) $leave->attachment_path);

    slApply($this->teacher, slDay(2))->assertSessionHasErrors('leave');
    expect(StaffLeaveRequest::query()->count())->toBe(1);
});

it('lets staff cancel their own undecided request only', function () {
    $leave = slMake($this->accountant, slDay(1));

    $this->actingAs($this->teacher)->post(route('my-leave.cancel', $leave->ulid))->assertNotFound();
    $this->actingAs($this->accountant)->post(route('my-leave.cancel', $leave->ulid))->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe(LeaveStatus::Cancelled);

    $approved = slMake($this->accountant, slDay(2));
    slDecide($this->principal, $approved, 'approve')->assertSessionHasNoErrors();
    $this->actingAs($this->accountant)->post(route('my-leave.cancel', $approved->ulid))->assertSessionHasErrors('leave');
    expect($approved->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('needs a reason to reject and approves leave of someone without a class straight away', function () {
    $leave = slMake($this->accountant, slDay(1));

    slDecide($this->principal, $leave, 'reject', 'No')->assertSessionHasErrors('leave');
    slDecide($this->principal, $leave, 'approve', 'Take care.')->assertSessionHasNoErrors();

    expect($leave->fresh())->status->toBe(LeaveStatus::Approved)->cover_staff_id->toBeNull()->decision_note->toBe('Take care.')
        ->and(AuditEvent::query()->where('action', 'staff_leave.approved')->count())->toBe(1);

    $other = slMake($this->accountant, slDay(3));
    slDecide($this->principal, $other, 'reject', 'Exam duty that day.')->assertSessionHasNoErrors();
    expect($other->fresh()->status)->toBe(LeaveStatus::Rejected)->and($this->notifier->events)->toContain('approved', 'rejected');
});

it('needs a valid cover when approving a class teacher\'s leave', function () {
    $leave = slMake($this->teacher, slDay(1));
    $vikram = $this->staffWith(['students.view']);
    $busy = $this->staffWith(['students.view']);
    slMake($busy, slDay(1));
    slDecide($this->principal, StaffLeaveRequest::query()->latest('id')->firstOrFail(), 'approve')->assertSessionHasNoErrors();

    slDecide($this->coordinator, $leave, 'approve')->assertSessionHasErrors('leave');
    slDecide($this->coordinator, $leave, 'approve', null, slStaffUlid($this->teacher))->assertSessionHasErrors('leave');
    slDecide($this->coordinator, $leave, 'approve', null, slStaffUlid($busy))->assertSessionHasErrors('leave');
    slDecide($this->coordinator, $leave, 'approve', null, 'not-a-staff-member')->assertSessionHasErrors('leave');
    expect($leave->fresh()->status)->toBe(LeaveStatus::Pending);

    slDecide($this->coordinator, $leave, 'approve', null, slStaffUlid($vikram))->assertSessionHasNoErrors();
    expect($leave->fresh())->status->toBe(LeaveStatus::Approved)->cover_staff_id->not->toBeNull();

    $this->actingAs($this->teacher)->get(route('my-leave.index'))->assertInertia(fn (Assert $page) => $page
        ->where('leave.requests.0.status', 'approved')
        ->where('leave.requests.0.cover_name', fn ($name) => str_starts_with((string) $name, 'Staff '))
        ->where('leave.requests.0.can_cancel', false));
});

it('lets the cover mark the register on the leave dates only', function () {
    $today = slDay(0);
    $cover = $this->staffWith(['students.view']);
    $leave = slMake($this->teacher, $today);
    $marks = [$this->riya->ulid => AttendanceStatus::Present];

    // Before approval the person has no access to the class register at all.
    $this->actingAs($cover)->get(route('attendance.index'))->assertForbidden();
    expect(fn () => app(SaveRegister::class)->handle($cover, $this->main1A->ulid, $today, $marks))->toThrow(AttendanceException::class);

    slDecide($this->coordinator, $leave, 'approve', null, slStaffUlid($cover))->assertSessionHasNoErrors();

    $this->actingAs($cover)->get(route('attendance.index', ['section' => $this->main1A->ulid, 'date' => $today]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.section', $this->main1A->ulid)
            ->where('register.can_edit', true)
            ->where('register.sections.0.relation', 'cover'));

    $this->actingAs($cover)->post(route('attendance.store'), [
        'section' => $this->main1A->ulid, 'date' => $today, 'marks' => [$this->riya->ulid => 'present'],
    ])->assertSessionHasNoErrors();
    expect(AuditEvent::query()->where('action', 'attendance.register_saved')->count())->toBe(1);

    // Another class, and a day outside the leave, stay closed to the cover.
    expect(fn () => app(SaveRegister::class)->handle($cover, $this->main2A->ulid, $today, []))->toThrow(AttendanceException::class);
    $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    $this->actingAs($cover)->get(route('attendance.index', ['section' => $this->main1A->ulid, 'date' => $yesterday]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('register.can_edit', false));

    // A stranger gets nothing from someone else's leave.
    $stranger = $this->staffWith(['students.view']);
    $this->actingAs($stranger)->get(route('attendance.index'))->assertForbidden();
});

it('ends the cover when the leave ends', function () {
    $cover = $this->staffWith(['students.view']);
    $leave = slMake($this->teacher, slDay(0));
    slDecide($this->coordinator, $leave, 'approve', null, slStaffUlid($cover))->assertSessionHasNoErrors();
    $this->actingAs($cover)->get(route('attendance.index'))->assertOk();

    // A leave that finished longer ago than the correction window no longer opens the register.
    $leave->update(['from_on' => '2026-05-04', 'to_on' => '2026-05-05']);
    $this->actingAs($cover)->get(route('attendance.index'))->assertForbidden();
});

it('shows each approver only the staff leave in their scope', function () {
    $leave = slMake($this->teacher, slDay(1));
    $plain = slMake($this->accountant, slDay(1));

    $this->actingAs($this->coordinator)->get(route('staff-leave.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('leave/staff')
        ->where('screen.pending_count', 1)
        ->has('screen.requests', 1)
        ->where('screen.requests.0.staff_name', 'Neha Verma')
        ->where('screen.requests.0.can_decide', true)
        ->missing('screen.requests.0.id'));

    $this->actingAs($this->principal)->get(route('staff-leave.index'))->assertInertia(fn (Assert $page) => $page
        ->has('screen.requests', 2)
        ->where('screen.pending_count', 2));

    $northCoordinator = $this->staffWith(['staff.leave_approve'], Scope::wing([$this->north->ulid], 1, 1));
    slDecide($northCoordinator, $leave, 'approve', null, slStaffUlid($this->accountant))->assertNotFound();
    slDecide($this->coordinator, $plain, 'approve')->assertNotFound();
    slDecide($this->staffWith(['students.view']), $leave, 'approve')->assertNotFound();
    expect($leave->fresh()->status)->toBe(LeaveStatus::Pending);
});

it('never lets someone decide their own leave', function () {
    $own = slMake($this->principal, slDay(1));

    slDecide($this->principal, $own, 'approve')->assertForbidden();
    $this->actingAs($this->principal)->get(route('staff-leave.index'))->assertInertia(fn (Assert $page) => $page
        ->has('screen.requests', 0)
        ->where('screen.pending_count', 0));

    $this->actingAs($this->principal)->get(route('my-leave.index'))->assertInertia(fn (Assert $page) => $page
        ->where('leave.requests.0.can_cancel', true));
});

it('denies the staff leave screens without a permission and when the school is suspended', function () {
    $leave = slMake($this->accountant, slDay(1));

    $this->actingAs($this->accountant)->get(route('staff-leave.index'))->assertForbidden();
    slDecide($this->accountant, $leave, 'approve')->assertNotFound();

    $this->setSchoolStatus(TenantStatus::Suspended);
    slDecide($this->principal, $leave, 'approve')->assertForbidden();
    $this->actingAs($this->accountant)->get(route('my-leave.index'))->assertForbidden();
    $this->setSchoolStatus(TenantStatus::Active);

    slDecide($this->principal, $leave, 'approve')->assertSessionHasNoErrors();
});

it('opens a certificate only through a signed, audited link for the applicant or an approver in scope', function () {
    $leave = slMake($this->teacher, slDay(1), null, ['attachment' => UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf')]);
    $url = URL::temporarySignedRoute('staff-leave.attachment', now()->addMinutes(5), ['leave' => $leave->ulid]);

    $this->actingAs($this->teacher)->get($url)->assertOk();
    $this->actingAs($this->coordinator)->get($url)->assertOk();
    expect(AuditEvent::query()->where('action', 'staff_leave.attachment_opened')->count())->toBe(2);

    $this->actingAs($this->coordinator)->get(route('staff-leave.attachment', $leave->ulid))->assertForbidden();
    $this->actingAs($this->accountant)->get($url)->assertNotFound();
    $northCoordinator = $this->staffWith(['staff.leave_approve'], Scope::wing([$this->north->ulid], 1, 1));
    $this->actingAs($northCoordinator)->get($url)->assertNotFound();
});

it('lists the staff directory with who is on leave today for people who may see staff', function () {
    $today = slDay(0);
    $leave = slMake($this->accountant, $today);
    slDecide($this->principal, $leave, 'approve')->assertSessionHasNoErrors();

    $this->actingAs($this->principal)->get(route('staff-leave.index', ['tab' => 'directory']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('screen.tab', 'directory')
        ->where('screen.directory', fn ($rows) => collect($rows)->contains(fn ($row) => $row['on_leave_today'] === true)
            && collect($rows)->contains(fn ($row) => $row['name'] === 'Neha Verma' && $row['class_label'] === 'Class 1-A' && $row['on_leave_today'] === false)));

    // Without staff.view the directory tab is not served.
    $approverOnly = $this->staffWith(['staff.leave_approve']);
    $this->actingAs($approverOnly)->get(route('staff-leave.index', ['tab' => 'directory']))->assertInertia(fn (Assert $page) => $page
        ->where('screen.tab', 'leave')
        ->where('screen.can_view_directory', false)
        ->has('screen.directory', 0));
});

it('limits the staff leave policy to people who manage settings', function () {
    $policy = ['policy' => [
        'casual' => ['days' => 8, 'document_after_days' => ''],
        'sick' => ['days' => 6, 'document_after_days' => 1],
        'earned' => ['days' => 15, 'document_after_days' => ''],
        'duty' => ['days' => '', 'document_after_days' => ''],
    ]];

    $this->actingAs($this->teacher)->put(route('leave.settings.staff'), $policy)->assertForbidden();
    $this->actingAs($this->owner)->put(route('leave.settings.staff'), ['policy' => [...$policy['policy'], 'casual' => ['days' => 0, 'document_after_days' => '']]])->assertSessionHasErrors('policy.casual.days');
    $this->actingAs($this->owner)->put(route('leave.settings.staff'), $policy)->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->get(route('leave.settings'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.staff_policy.0.days', 8)
        ->where('settings.staff_policy.1.document_after_days', 1)
        ->where('settings.staff_policy.3.days', null));
});

it('keeps one school\'s staff leave out of another school', function () {
    slMake($this->teacher, slDay(1));

    expect($this->otherSchool->execute(fn () => StaffLeaveRequest::query()->count()))->toBe(0)
        ->and(StaffLeaveRequest::query()->count())->toBe(1);
});
