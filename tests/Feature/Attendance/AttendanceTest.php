<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Attendance\Contracts\AbsenceNotifier;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateHoliday;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Models\Student;
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

    // Every weekday counts as a school day, so tests do not depend on the day they run.
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5, 6, 7], 7);

    // Aditya, Bhavna and Chirag are in Class 1 A; Dev is in Class 1 B.
    $this->kids = [
        'aditya' => $this->makeStudent($this->main1A, 'Aditya', 'Gill'),
        'bhavna' => $this->makeStudent($this->main1A, 'Bhavna', 'Rao'),
        'chirag' => $this->makeStudent($this->main1A, 'Chirag', 'Sethi'),
    ];
    $this->dev = $this->makeStudent($this->main1B, 'Dev', 'Malik');

    // Students are admitted today by default; move the start back so past days have a roster.
    DB::connection('tenant')->table('enrollments')->update(['started_on' => '2026-04-01']);

    $this->teacher = classTeacherOf($this->main1A);
});

/** A staff member made class teacher of a section through the real Action (which also gives the role). */
function classTeacherOf(Section $section, string $name = 'Neha Verma'): Identity
{
    $test = test();
    $identity = $test->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, $name);
    app(SetClassTeacher::class)->handle($test->owner, $section, $staff->ulid);

    return $identity;
}

function daysAgo(int $days): string
{
    return (new DateTimeImmutable(CalendarDate::today()))->modify("-{$days} days")->format('Y-m-d');
}

/** @param array<string, string> $marks student first name (lowercase) => status */
function saveRegister(Identity $actor, Section $section, string $date, array $marks, ?string $reason = null)
{
    $byUlid = [];

    foreach ($marks as $key => $status) {
        $student = test()->kids[$key] ?? test()->dev;
        $byUlid[$student->ulid] = $status;
    }

    return test()->actingAs($actor)->post(route('attendance.store'), [
        'section' => $section->ulid, 'date' => $date, 'marks' => $byUlid, 'reason' => $reason,
    ]);
}

function allPresent(): array
{
    return ['aditya' => 'present', 'bhavna' => 'present', 'chirag' => 'present'];
}

it('gives the class teacher the Class teacher role over exactly their sections, and moves it with the teacher', function () {
    $assignment = RoleAssignment::query()->whereHas('staff', fn ($q) => $q->where('identity_ulid', $this->teacher->ulid))->first();

    expect($assignment->source)->toBe('auto')
        ->and($assignment->role->source_template_key)->toBe('class_teacher')
        ->and($assignment->toScope()->sectionUlids())->toBe([$this->main1A->ulid]);

    // A second section for the same teacher widens the scope; a new teacher takes over 1A.
    $staff = $assignment->staff;
    app(SetClassTeacher::class)->handle($this->owner, $this->main1B, $staff->ulid);
    expect($assignment->fresh()->toScope()->sectionUlids())->toEqualCanonicalizing([$this->main1A->ulid, $this->main1B->ulid]);

    $other = classTeacherOf($this->main1A, 'Ritu Anand');
    expect($assignment->fresh()->toScope()->sectionUlids())->toBe([$this->main1B->ulid])
        ->and(RoleAssignment::query()->where('source', 'auto')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'structure.class_teacher_set')->count())->toBeGreaterThanOrEqual(3);

    app(SetClassTeacher::class)->handle($this->owner, $this->main1B, null);
    expect(RoleAssignment::query()->whereKey($assignment->id)->exists())->toBeFalse()
        ->and($other)->toBeInstanceOf(Identity::class);
});

it('does not let the automatic assignment be removed by hand', function () {
    $assignment = RoleAssignment::query()->where('source', 'auto')->first();

    $this->actingAs($this->owner)
        ->delete(route('school.roles.assignments.destroy', $assignment))
        ->assertSessionHasErrors('roles');

    expect(RoleAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('saves the register as the class teacher, with audit and an absence notice', function () {
    $notified = [];
    $this->app->bind(AbsenceNotifier::class, function () use (&$notified) {
        return new class($notified) implements AbsenceNotifier
        {
            public function __construct(private array &$sink) {}

            public function notify(string $sectionUlid, string $date, array $newlyAbsent, array $noLongerAbsent): void
            {
                $this->sink[] = compact('sectionUlid', 'newlyAbsent', 'noLongerAbsent');
            }
        };
    });

    saveRegister($this->teacher, $this->main1A, CalendarDate::today(), ['aditya' => 'present', 'bhavna' => 'absent', 'chirag' => 'late'])
        ->assertSessionHasNoErrors();

    $register = AttendanceRegister::query()->firstOrFail();

    expect($register->marks()->count())->toBe(3)
        ->and(AttendanceMark::query()->where('status', 'absent')->count())->toBe(1)
        ->and($register->last_reason)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'attendance.register_saved')->count())->toBe(1)
        ->and($notified)->toHaveCount(1)
        ->and($notified[0]['newlyAbsent'])->toBe([$this->kids['bhavna']->ulid]);

    $this->actingAs($this->teacher)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
        ->where('register.state', 'saved')
        ->where('register.can_edit', true)
        ->where('register.needs_reason', false)
        ->where('register.sections.0.relation', 'yours')
        ->has('register.students', 3)
        ->where('register.students.1.status', 'absent')
        ->missing('register.students.0.id'));
});

it('refuses a register with unmarked students and stores nothing', function () {
    saveRegister($this->teacher, $this->main1A, CalendarDate::today(), ['aditya' => 'present'])
        ->assertSessionHasErrors('register');

    expect(AttendanceRegister::query()->count())->toBe(0);
});

it('lets the class teacher change today\'s register freely and refuses a no-op', function () {
    $today = CalendarDate::today();
    saveRegister($this->teacher, $this->main1A, $today, allPresent())->assertSessionHasNoErrors();

    saveRegister($this->teacher, $this->main1A, $today, allPresent())->assertSessionHasErrors('register');

    saveRegister($this->teacher, $this->main1A, $today, [...allPresent(), 'chirag' => 'absent'])->assertSessionHasNoErrors();

    expect(AttendanceMark::query()->where('status', 'absent')->count())->toBe(1)
        ->and(AttendanceRegister::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'attendance.register_corrected')->count())->toBe(1);
});

it('needs a reason for an earlier day inside the window, and locks it after', function () {
    $recent = daysAgo(3);

    saveRegister($this->teacher, $this->main1A, $recent, allPresent())->assertSessionHasErrors('register');
    expect(AttendanceRegister::query()->count())->toBe(0);

    saveRegister($this->teacher, $this->main1A, $recent, allPresent(), 'Oops')->assertSessionHasErrors('register');
    saveRegister($this->teacher, $this->main1A, $recent, allPresent(), 'Register was on paper')->assertSessionHasNoErrors();

    expect(AttendanceRegister::query()->first()->last_reason)->toBe('Register was on paper');

    saveRegister($this->teacher, $this->main1A, daysAgo(8), allPresent(), 'Too late to ask')->assertSessionHasErrors('register');
    expect(AttendanceRegister::query()->count())->toBe(1);
});

it('lets someone who can correct attendance change a locked day, always with a reason', function () {
    $old = daysAgo(12);
    $coordinator = $this->staffWith(['attendance.view', 'attendance.correct'], Scope::campuses([$this->main->ulid]));

    saveRegister($coordinator, $this->main1A, $old, allPresent())->assertSessionHasErrors('register');
    saveRegister($coordinator, $this->main1A, $old, allPresent(), 'Principal asked for a late entry')->assertSessionHasNoErrors();

    expect(AttendanceRegister::query()->count())->toBe(1);

    $this->actingAs($coordinator)->get(route('attendance.index', ['section' => $this->main1A->ulid, 'date' => $old]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.state', 'saved')
            ->where('register.needs_reason', true)
            ->where('register.sections.0.relation', 'correct'));
});

it('refuses viewers and other classes\' teachers', function () {
    $viewer = $this->staffWith(['attendance.view'], Scope::school());
    saveRegister($viewer, $this->main1A, CalendarDate::today(), allPresent())->assertForbidden();

    // The teacher of 1A cannot see 1B at all: not found, not forbidden.
    saveRegister($this->teacher, $this->main1B, CalendarDate::today(), ['dev' => 'present'])->assertNotFound();

    // A teacher with view over 1B but who is not its class teacher is refused.
    $both = $this->staffWith(['attendance.view', 'attendance.mark'], Scope::sections([$this->main1B->ulid]));
    saveRegister($both, $this->main1B, CalendarDate::today(), ['dev' => 'present'])->assertForbidden();

    $this->actingAs($both)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
        ->where('register.state', 'view_only')
        ->where('register.sections.0.relation', 'view')
        ->where('register.students', []));

    expect(AttendanceRegister::query()->count())->toBe(0);
});

it('lists only the classes in the person\'s scope and ignores a class outside it', function () {
    $this->actingAs($this->teacher)
        ->get(route('attendance.index', ['section' => $this->main1B->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('register.sections', 1)
            ->where('register.section', $this->main1A->ulid));

    $this->actingAs($this->owner)->get(route('attendance.index'))
        ->assertInertia(fn (Assert $page) => $page->has('register.sections', 4)->where('register.sections.0.relation', 'correct'));
});

it('has no register on a holiday, a weekly day off, a future day or before the year', function () {
    app(CreateHoliday::class)->handle($this->owner, daysAgo(2), 'Founders Day');
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5, 6, 7], 7);

    saveRegister($this->teacher, $this->main1A, daysAgo(2), allPresent(), 'Holiday')->assertSessionHasErrors('register');
    saveRegister($this->teacher, $this->main1A, (new DateTimeImmutable(CalendarDate::today()))->modify('+1 day')->format('Y-m-d'), allPresent())->assertSessionHasErrors('register');
    saveRegister($this->teacher, $this->main1A, '2026-03-30', allPresent(), 'Before the year')->assertSessionHasErrors('register');

    $this->actingAs($this->teacher)->get(route('attendance.index', ['date' => daysAgo(2)]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.state', 'closed')
            ->where('register.closed_reason', 'Founders Day')
            ->where('register.students', []));

    $weekday = (int) (new DateTimeImmutable(daysAgo(1)))->format('N');
    app(UpdateAttendanceCalendar::class)->handle($this->owner, array_values(array_diff([1, 2, 3, 4, 5, 6, 7], [$weekday])), 7);

    saveRegister($this->teacher, $this->main1A, daysAgo(1), allPresent(), 'Day off')->assertSessionHasErrors('register');
    expect(AttendanceRegister::query()->count())->toBe(0);
});

it('flags a student who was absent on several recent days', function () {
    foreach ([3, 2, 1] as $ago) {
        saveRegister($this->teacher, $this->main1A, daysAgo($ago), [...allPresent(), 'bhavna' => 'absent'], 'Backfilled')->assertSessionHasNoErrors();
    }

    $this->actingAs($this->teacher)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
        ->where('register.state', 'pending')
        ->where('register.students.1.name', 'Bhavna Rao')
        ->where('register.students.1.recent_absences', 3)
        ->where('register.students.0.recent_absences', 0));
});

it('follows a student who changed section: on the new section\'s register from that day', function () {
    DB::connection('tenant')->table('enrollments')->where('student_id', $this->dev->id)->update(['started_on' => '2026-04-01']);
    $today = CalendarDate::today();

    $this->actingAs($this->owner)->post(route('students.section', $this->dev), ['section' => $this->main1A->ulid])->assertSessionHasNoErrors();

    $this->actingAs($this->teacher)->get(route('attendance.index'))
        ->assertInertia(fn (Assert $page) => $page->has('register.students', 4));

    $yesterday = daysAgo(1);
    $this->actingAs($this->owner)->get(route('attendance.index', ['section' => $this->main1B->ulid, 'date' => $yesterday]))
        ->assertInertia(fn (Assert $page) => $page->has('register.students', 1)->where('register.students.0.name', 'Dev Malik'));

    expect($today)->not->toBe($yesterday);
});

it('hides the module when it is not in the plan and blocks a suspended school', function () {
    $this->actingAs($this->teacher)->get(route('attendance.index'))->assertOk();

    $this->school->update(['status' => 'suspended']);
    $this->actingAs($this->teacher)->get(route('attendance.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));

    $this->school->update(['status' => 'active']);
    DB::connection('landlord')->table('tenant_modules')->where('module', 'attendance')->delete();
    $this->actingAs($this->owner)->get(route('attendance.index'))->assertNotFound();
});

it('keeps the class teacher out of the register screen without any attendance permission', function () {
    $none = $this->staffWith(['students.view']);

    $this->actingAs($none)->get(route('attendance.index'))->assertForbidden();
});

it('gives the new correct permission to the right templates only', function () {
    $has = fn (string $template) => in_array('attendance.correct', DB::connection('landlord')->table('role_templates')
        ->join('role_template_versions', fn ($join) => $join->on('role_template_versions.role_template_id', '=', 'role_templates.id')->on('role_template_versions.version', '=', 'role_templates.current_version'))
        ->join('role_template_version_permissions', 'role_template_version_permissions.role_template_version_id', '=', 'role_template_versions.id')
        ->where('role_templates.key', $template)->pluck('permission_key')->all(), true);

    expect($has('principal'))->toBeTrue()
        ->and($has('vice_principal'))->toBeTrue()
        ->and($has('coordinator'))->toBeTrue()
        ->and($has('class_teacher'))->toBeFalse()
        ->and($has('front_office'))->toBeFalse()
        ->and(Student::query()->count())->toBe(4);
});
