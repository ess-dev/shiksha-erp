<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
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
    $this->enableModules(['attendance']);
    $this->setUpStudentSchool();

    $this->class2 = SchoolClass::query()->where('level', 2)->firstOrFail();
    $this->class3 = app(CreateSchoolClass::class)->handle($this->owner, 'Class 3', 3);
    $this->year = AcademicYear::query()->where('is_current', true)->firstOrFail();
});

function newSectionPayload(array $overrides = []): array
{
    $test = test();

    return [
        'academic_year' => $test->year->ulid,
        'campus' => $test->north->ulid,
        'class' => $test->class2->ulid,
        'name' => 'a',
        'capacity' => 35,
        'room' => 'Room 215',
        ...$overrides,
    ];
}

function freeStaff(string $name = 'Deepak Saini'): Staff
{
    return app(CreateStaffRecord::class)->handle(test()->staff()->ulid, $name);
}

it('lists sections with strength, girls, class teacher and today\'s register using ulids only', function () {
    $girl = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    DB::connection('tenant')->table('students')->where('id', $girl->id)->update(['gender' => 'female']);

    $register = AttendanceRegister::query()->create([
        'ulid' => strtolower((string) Str::ulid()),
        'section_id' => $this->main1A->id,
        'attendance_on' => CalendarDate::today(),
        'first_saved_at' => now(),
        'last_saved_at' => now(),
    ]);
    AttendanceMark::query()->create(['attendance_register_id' => $register->id, 'student_id' => $girl->id, 'status' => 'absent']);

    $this->actingAs($this->owner)
        ->get(route('school.classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/classes')
            ->where('overview.academic_year_name', '2026-27')
            ->where('overview.can_manage', true)
            ->where('overview.has_attendance', true)
            ->has('overview.sections', 4)
            ->where('overview.sections.0.ulid', $this->main1A->ulid)
            ->where('overview.sections.0.students', 2)
            ->where('overview.sections.0.girls', 1)
            ->where('overview.sections.0.register_absent', 1)
            ->whereNot('overview.sections.0.register_saved_at', null)
            ->where('overview.sections.1.register_saved_at', null)
            ->missing('overview.sections.0.id'));
});

it('offers only classes a campus does not have yet, and the next free section letter', function () {
    $this->actingAs($this->owner)
        ->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.campuses.0.name', 'Main Campus')
            ->where('overview.campuses.0.addable_classes', [['level' => 3, 'name' => 'Class 3']])
            ->where('overview.campuses.1.name', 'North Campus')
            ->where('overview.campuses.1.addable_classes', [['level' => 2, 'name' => 'Class 2'], ['level' => 3, 'name' => 'Class 3']])
            ->where('overview.sections.0.next_section_name', 'C')
            ->where('overview.class_ulids.3', $this->class3->ulid));
});

it('narrows to the campus chosen in the top bar', function () {
    $this->actingAs($this->owner)
        ->withSession(['school.campus' => $this->north->ulid])
        ->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.campus_name', 'North Campus')
            ->has('overview.sections', 1)
            ->has('overview.campuses', 1));
});

it('adds a class at a campus with room, seats and a class teacher, and audits it', function () {
    $teacher = freeStaff();

    $this->actingAs($this->owner)
        ->post(route('school.structure.sections.store'), newSectionPayload(['class_teacher' => $teacher->ulid]))
        ->assertSessionHasNoErrors();

    $section = Section::query()->where('campus_id', $this->north->id)->where('school_class_id', $this->class2->id)->firstOrFail();

    expect($section->name)->toBe('A')
        ->and($section->capacity)->toBe(35)
        ->and($section->room)->toBe('Room 215')
        ->and($section->class_teacher_staff_id)->toBe($teacher->id)
        ->and(RoleAssignment::query()->where('staff_id', $teacher->id)->where('source', 'auto')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'structure.section_created')->where('subject_ulid', $section->ulid)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'structure.class_teacher_set')->where('subject_ulid', $section->ulid)->exists())->toBeTrue();

    $this->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.campuses.1.addable_classes', [['level' => 3, 'name' => 'Class 3']])
            ->where('overview.free_teachers', fn ($teachers) => collect($teachers)->doesntContain('ulid', $teacher->ulid)));
});

it('adds a section without a room or class teacher', function () {
    $this->actingAs($this->owner)
        ->post(route('school.structure.sections.store'), newSectionPayload(['room' => '', 'class_teacher' => '', 'capacity' => '']))
        ->assertSessionHasNoErrors();

    $section = Section::query()->where('campus_id', $this->north->id)->where('school_class_id', $this->class2->id)->firstOrFail();

    expect($section->room)->toBeNull()
        ->and($section->capacity)->toBeNull()
        ->and($section->class_teacher_staff_id)->toBeNull();
});

it('refuses a duplicate section name, a teacher who already leads a class, and a missing campus', function () {
    $teacher = freeStaff();
    $this->actingAs($this->owner)
        ->post(route('school.structure.sections.store'), newSectionPayload(['class_teacher' => $teacher->ulid]))
        ->assertSessionHasNoErrors();

    $this->post(route('school.structure.sections.store'), newSectionPayload())
        ->assertSessionHasErrors('structure');

    $this->post(route('school.structure.sections.store'), newSectionPayload(['class' => $this->class3->ulid, 'class_teacher' => $teacher->ulid]))
        ->assertSessionHasErrors('structure');
    expect(Section::query()->where('school_class_id', $this->class3->id)->exists())->toBeFalse();

    $this->post(route('school.structure.sections.store'), newSectionPayload(['campus' => 'nope']))
        ->assertSessionHasErrors('campus');
});

it('validates seats and room length', function () {
    $this->actingAs($this->owner)
        ->post(route('school.structure.sections.store'), newSectionPayload(['capacity' => 0, 'room' => str_repeat('x', 31)]))
        ->assertSessionHasErrors(['capacity', 'room']);
});

it('lets a viewer see the screen but not manage, and refuses their add', function () {
    $viewer = $this->staffWith(['structure.view']);

    $this->actingAs($viewer)
        ->get(route('school.classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.can_manage', false)
            ->where('overview.campuses.0.addable_classes', [])
            ->where('overview.sections.0.can_manage', false)
            ->where('overview.sections.0.next_section_name', null));

    $this->post(route('school.structure.sections.store'), newSectionPayload())->assertForbidden();
    expect(Section::query()->where('campus_id', $this->north->id)->where('school_class_id', $this->class2->id)->exists())->toBeFalse();
});

it('limits a campus manager to their own campus', function () {
    $manager = $this->staffWith(['structure.view', 'structure.manage'], Scope::campuses([$this->north->ulid]));

    $this->actingAs($manager)
        ->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.campuses', 1)
            ->where('overview.campuses.0.ulid', $this->north->ulid)
            ->has('overview.sections', 1));

    $this->post(route('school.structure.sections.store'), newSectionPayload(['campus' => $this->main->ulid]))->assertForbidden();
    $this->post(route('school.structure.sections.store'), newSectionPayload())->assertSessionHasNoErrors();
    expect(Section::query()->where('campus_id', $this->north->id)->where('school_class_id', $this->class2->id)->exists())->toBeTrue();
});

it('refuses people without structure.view, guests and a suspended school', function () {
    $this->get(route('school.classes.index'))->assertRedirect(route('login'));

    $other = $this->staffWith(['students.view']);
    $this->actingAs($other)->get(route('school.classes.index'))->assertForbidden();

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($this->owner)->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('hides today\'s register when the attendance module is not in the plan', function () {
    $this->disableModule('attendance');

    $this->actingAs($this->owner)
        ->get(route('school.classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.has_attendance', false)
            ->where('overview.sections.0.register_saved_at', null));
});

it('keeps one school\'s sections out of another school\'s screen', function () {
    expect($this->otherSchool->execute(fn () => Section::query()->count()))->toBe(0)
        ->and(Section::query()->count())->toBe(4);
});
