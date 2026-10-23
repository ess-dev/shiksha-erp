<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\SetClassSubjects;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\SectionSubjectTeacher;
use App\Domain\School\Models\Subject;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class);

beforeEach(function () {
    $this->setUpStudentSchool();

    $this->class1 = SchoolClass::query()->where('level', 1)->firstOrFail();
    $this->class2 = SchoolClass::query()->where('level', 2)->firstOrFail();

    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$this->class1, $this->class2]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$this->class1, $this->class2]);
    $this->sanskrit = $add->handle($this->owner, 'Sanskrit', 'san', SubjectType::Elective);
    $this->music = $add->handle($this->owner, 'Music', 'mus', SubjectType::CoScholastic, [$this->class1]);
});

function subjectStaff(string $name = 'Simranjeet Kaur')
{
    return app(CreateStaffRecord::class)->handle(test()->staff()->ulid, $name);
}

function subjectAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('shows classes, subjects, who teaches what and the class teacher as the automatic choice, with ulids only', function () {
    $teacher = subjectStaff('Anjali Khanna');
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, $teacher->ulid);

    $this->actingAs($this->owner)
        ->get(route('school.subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/subjects')
            ->where('overview.can_configure', true)
            ->has('overview.classes', 2)
            ->where('overview.classes.0.name', 'Class 1')
            ->where('overview.classes.0.section_count', 3)
            ->where('overview.classes.0.subject_ulids', [$this->english->ulid, $this->maths->ulid, $this->music->ulid])
            ->where('overview.classes.0.can_change', true)
            ->where('overview.classes.1.section_count', 1)
            ->has('overview.subjects', 4)
            ->where('overview.subjects.2.name', 'Sanskrit')
            ->where('overview.subjects.2.type', 'elective')
            ->where('overview.subjects.2.taught_in', [])
            ->where('overview.subjects.2.can_delete', true)
            ->where('overview.subjects.0.taught_in', ['Class 1', 'Class 2'])
            ->where('overview.subjects.0.can_delete', false)
            ->where('overview.selected_section.ulid', $this->main1A->ulid)
            ->where('overview.selected_section.name', 'Class 1-A')
            ->where('overview.selected_section.class_teacher_name', 'Anjali Khanna')
            ->where('overview.selected_section.rows.0.subject_name', 'English')
            ->where('overview.selected_section.rows.0.teacher_ulid', null)
            ->where('overview.selected_section.rows.0.automatic_name', 'Anjali Khanna')
            ->has('overview.sections', 4)
            ->has('overview.staff')
            ->missing('overview.classes.0.id')
            ->missing('overview.subjects.0.id'));
});

it('lists the sections of the chosen campus and opens the section asked for', function () {
    $this->actingAs($this->owner)
        ->withSession(['school.campus' => $this->north->ulid])
        ->get(route('school.subjects.index', ['section' => $this->main1B->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.campus_name', 'North Campus')
            ->has('overview.sections', 1)
            ->where('overview.selected_section.ulid', $this->north1A->ulid));

    $this->get(route('school.subjects.index', ['section' => $this->main2A->ulid]))
        ->assertInertia(fn (Assert $page) => $page->has('overview.sections', 1));

    $this->withSession(['school.campus' => null])
        ->get(route('school.subjects.index', ['section' => $this->main2A->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.sections', 4)
            ->where('overview.selected_section.ulid', $this->main2A->ulid));
});

it('adds a subject with a type and classes, and audits it', function () {
    $this->actingAs($this->owner)
        ->post(route('school.subjects.store'), ['name' => ' Punjabi ', 'code' => 'pun', 'type' => 'elective', 'classes' => [$this->class2->ulid]])
        ->assertSessionHasNoErrors();

    $subject = Subject::query()->where('code', 'PUN')->firstOrFail();

    expect($subject->name)->toBe('Punjabi')
        ->and($subject->type)->toBe(SubjectType::Elective)
        ->and($this->class2->subjects()->pluck('subjects.id')->all())->toContain($subject->id)
        ->and($this->class1->subjects()->pluck('subjects.id')->all())->not->toContain($subject->id)
        ->and(subjectAudit('subjects.subject_added')->where('subject_ulid', $subject->ulid)->exists())->toBeTrue();
});

it('refuses a duplicate name or code, and validates the form', function () {
    $this->actingAs($this->owner);

    $this->post(route('school.subjects.store'), ['name' => 'english', 'code' => 'XYZ', 'type' => 'core'])
        ->assertSessionHasErrors('structure');
    $this->post(route('school.subjects.store'), ['name' => 'Urdu', 'code' => 'eng', 'type' => 'core'])
        ->assertSessionHasErrors('structure');
    $this->post(route('school.subjects.store'), ['name' => '', 'code' => 'a b', 'type' => 'nonsense', 'classes' => ['nope']])
        ->assertSessionHasErrors(['name', 'code', 'type', 'classes.0']);

    expect(Subject::query()->count())->toBe(4);
});

it('renames a subject and changes its code and type', function () {
    $this->actingAs($this->owner)
        ->patch(route('school.subjects.update', $this->sanskrit), ['name' => 'Sanskrit Studies', 'code' => 'sans', 'type' => 'co_scholastic'])
        ->assertSessionHasNoErrors();

    $this->sanskrit->refresh();

    expect($this->sanskrit->name)->toBe('Sanskrit Studies')
        ->and($this->sanskrit->code)->toBe('SANS')
        ->and($this->sanskrit->type)->toBe(SubjectType::CoScholastic)
        ->and(subjectAudit('subjects.subject_updated')->where('subject_ulid', $this->sanskrit->ulid)->exists())->toBeTrue();

    $this->patch(route('school.subjects.update', $this->sanskrit), ['name' => 'Mathematics', 'code' => 'SANS', 'type' => 'core'])
        ->assertSessionHasErrors('structure');
});

it('refuses to make the only examined subject of a class co-scholastic', function () {
    $this->actingAs($this->owner);

    app(SetClassSubjects::class)->handle($this->owner, $this->class1, [$this->english, $this->music]);

    $this->patch(route('school.subjects.update', $this->english), ['name' => 'English', 'code' => 'ENG', 'type' => 'co_scholastic'])
        ->assertSessionHasErrors('structure');

    expect($this->english->refresh()->type)->toBe(SubjectType::Core);
});

it('deletes a subject nothing uses and refuses one that is in use', function () {
    $this->actingAs($this->owner);

    $this->delete(route('school.subjects.destroy', $this->sanskrit))->assertSessionHasNoErrors();

    expect(Subject::query()->where('code', 'SAN')->exists())->toBeFalse()
        ->and(subjectAudit('subjects.subject_deleted')->exists())->toBeTrue();

    $this->delete(route('school.subjects.destroy', $this->english))->assertSessionHasErrors('structure');

    app(SetClassSubjects::class)->handle($this->owner, $this->class1, [$this->english]);
    app(SetClassSubjects::class)->handle($this->owner, $this->class2, [$this->english]);
    SectionSubjectTeacher::query()->create(['section_id' => $this->main1A->id, 'subject_id' => $this->maths->id, 'staff_id' => subjectStaff()->id]);

    $this->delete(route('school.subjects.destroy', $this->maths))->assertSessionHasErrors('structure');
    expect(Subject::query()->where('code', 'MAT')->exists())->toBeTrue();
});

it('replaces the subjects of a class, needs one examined subject, and clears removed subjects\' teachers', function () {
    $teacher = subjectStaff();
    SectionSubjectTeacher::query()->create(['section_id' => $this->main1A->id, 'subject_id' => $this->maths->id, 'staff_id' => $teacher->id]);
    SectionSubjectTeacher::query()->create(['section_id' => $this->main1A->id, 'subject_id' => $this->english->id, 'staff_id' => $teacher->id]);
    SectionSubjectTeacher::query()->create(['section_id' => $this->main2A->id, 'subject_id' => $this->maths->id, 'staff_id' => $teacher->id]);

    $this->actingAs($this->owner);

    $this->put(route('school.subjects.classes.update', $this->class1), ['subjects' => [$this->music->ulid]])
        ->assertSessionHasErrors('structure');
    $this->put(route('school.subjects.classes.update', $this->class1), ['subjects' => []])
        ->assertSessionHasErrors('subjects');

    $this->put(route('school.subjects.classes.update', $this->class1), ['subjects' => [$this->english->ulid, $this->sanskrit->ulid]])
        ->assertSessionHasNoErrors();

    expect($this->class1->subjects()->pluck('subjects.code')->sort()->values()->all())->toBe(['ENG', 'SAN'])
        ->and(SectionSubjectTeacher::query()->where('section_id', $this->main1A->id)->pluck('subject_id')->all())->toBe([$this->english->id])
        ->and(SectionSubjectTeacher::query()->where('section_id', $this->main2A->id)->count())->toBe(1);

    $event = subjectAudit('structure.class_subjects_set')->where('subject_ulid', $this->class1->ulid)->latest('id')->firstOrFail();

    expect($event->after['teacher_choices_cleared'])->toBe(1);
});

it('chooses, replaces and clears a subject teacher, and audits each change', function () {
    $first = subjectStaff('Simranjeet Kaur');
    $second = subjectStaff('Amit Tiwari');
    $url = fn () => route('school.subjects.teachers.update', [$this->main1A, $this->english]);

    $this->actingAs($this->owner);

    $this->put($url(), ['staff' => $first->ulid])->assertSessionHasNoErrors();
    expect(SectionSubjectTeacher::query()->where('section_id', $this->main1A->id)->value('staff_id'))->toBe($first->id);

    $this->get(route('school.subjects.index', ['section' => $this->main1A->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.selected_section.rows.0.teacher_ulid', $first->ulid));

    $this->put($url(), ['staff' => $second->ulid])->assertSessionHasNoErrors();
    $this->put($url(), ['staff' => $second->ulid])->assertSessionHasNoErrors();
    expect(SectionSubjectTeacher::query()->where('section_id', $this->main1A->id)->count())->toBe(1)
        ->and(subjectAudit('subjects.teacher_set')->count())->toBe(2);

    $this->put($url(), ['staff' => null])->assertSessionHasNoErrors();
    expect(SectionSubjectTeacher::query()->count())->toBe(0)
        ->and(subjectAudit('subjects.teacher_set')->count())->toBe(3);
});

it('refuses a subject the class does not study, an unknown teacher and a former teacher', function () {
    $gone = subjectStaff('Deepak Saini');
    DB::connection('tenant')->table('staff')->where('id', $gone->id)->update(['status' => 'inactive']);

    $this->actingAs($this->owner);

    $this->put(route('school.subjects.teachers.update', [$this->main2A, $this->music]), ['staff' => subjectStaff()->ulid])
        ->assertSessionHasErrors('structure');
    $this->put(route('school.subjects.teachers.update', [$this->main1A, $this->english]), ['staff' => 'nobody'])
        ->assertSessionHasErrors('structure');
    $this->put(route('school.subjects.teachers.update', [$this->main1A, $this->english]), ['staff' => $gone->ulid])
        ->assertSessionHasErrors('structure');

    expect(SectionSubjectTeacher::query()->count())->toBe(0);
});

it('lets a viewer see the screen but nothing to change, and refuses their changes', function () {
    $viewer = $this->staffWith(['structure.view']);

    $this->actingAs($viewer)
        ->get(route('school.subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.can_configure', false)
            ->where('overview.classes.0.can_change', false)
            ->where('overview.subjects.0.can_change', false)
            ->where('overview.subjects.2.can_delete', false)
            ->where('overview.selected_section.can_change', false)
            ->where('overview.staff', []));

    $this->post(route('school.subjects.store'), ['name' => 'Urdu', 'code' => 'URD', 'type' => 'core'])->assertForbidden();
    $this->patch(route('school.subjects.update', $this->sanskrit), ['name' => 'X', 'code' => 'X', 'type' => 'core'])->assertForbidden();
    $this->delete(route('school.subjects.destroy', $this->sanskrit))->assertForbidden();
    $this->put(route('school.subjects.classes.update', $this->class1), ['subjects' => [$this->english->ulid]])->assertForbidden();
    $this->put(route('school.subjects.teachers.update', [$this->main1A, $this->english]), ['staff' => null])->assertForbidden();

    expect(Subject::query()->count())->toBe(4);
});

it('limits a wing coordinator to classes whose sections are all inside their wing', function () {
    $coordinator = $this->staffWith(['structure.view', 'subjects.configure'], Scope::wing([$this->main->ulid], 1, 2));

    $this->actingAs($coordinator)
        ->get(route('school.subjects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.can_configure', true)
            ->where('overview.classes.0.name', 'Class 1')
            ->where('overview.classes.0.can_change', false)
            ->where('overview.classes.1.name', 'Class 2')
            ->where('overview.classes.1.can_change', true)
            ->where('overview.subjects.0.can_change', false)
            ->where('overview.subjects.2.can_change', true)
            ->where('overview.subjects.2.can_delete', true)
            ->where('overview.selected_section.can_change', true)
            ->has('overview.sections', 3));

    $this->put(route('school.subjects.classes.update', $this->class1), ['subjects' => [$this->english->ulid]])->assertForbidden();
    $this->put(route('school.subjects.classes.update', $this->class2), ['subjects' => [$this->english->ulid, $this->sanskrit->ulid]])->assertSessionHasNoErrors();
    $this->post(route('school.subjects.store'), ['name' => 'Urdu', 'code' => 'URD', 'type' => 'elective', 'classes' => [$this->class1->ulid]])->assertForbidden();
    $this->post(route('school.subjects.store'), ['name' => 'Urdu', 'code' => 'URD', 'type' => 'elective', 'classes' => [$this->class2->ulid]])->assertSessionHasNoErrors();
    $this->patch(route('school.subjects.update', $this->english), ['name' => 'English', 'code' => 'ENG', 'type' => 'core'])->assertForbidden();
    $this->put(route('school.subjects.teachers.update', [$this->main2A, $this->english]), ['staff' => subjectStaff()->ulid])->assertSessionHasNoErrors();

    expect($this->class1->subjects()->count())->toBe(3);
});

it('refuses a coordinator teachers in a section outside their scope', function () {
    $coordinator = $this->staffWith(['structure.view', 'subjects.configure'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($coordinator)
        ->put(route('school.subjects.teachers.update', [$this->north1A, $this->english]), ['staff' => subjectStaff()->ulid])
        ->assertForbidden();
    $this->put(route('school.subjects.teachers.update', [$this->main1A, $this->english]), ['staff' => subjectStaff('Amit Tiwari')->ulid])
        ->assertSessionHasNoErrors();

    expect(SectionSubjectTeacher::query()->count())->toBe(1);
});

it('lets school-wide structure managers configure subjects without the new permission', function () {
    $manager = $this->staffWith(['structure.view', 'structure.manage']);

    $this->actingAs($manager)
        ->post(route('school.subjects.store'), ['name' => 'Urdu', 'code' => 'URD', 'type' => 'elective'])
        ->assertSessionHasNoErrors();

    expect(Subject::query()->where('code', 'URD')->exists())->toBeTrue();
});

it('refuses people without structure.view, guests and a suspended school', function () {
    $this->get(route('school.subjects.index'))->assertRedirect(route('login'));

    $this->actingAs($this->staffWith(['students.view']))->get(route('school.subjects.index'))->assertForbidden();

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($this->owner)->get(route('school.subjects.index'))
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('keeps one school\'s subjects out of another school', function () {
    expect($this->otherSchool->execute(fn () => Subject::query()->count()))->toBe(0)
        ->and(Subject::query()->count())->toBe(4);
});

it('gives the Coordinator template the permission from version 7', function () {
    $landlord = DB::connection('landlord');
    $versionId = fn (string $template) => $landlord->table('role_template_versions')
        ->join('role_templates', 'role_templates.id', '=', 'role_template_versions.role_template_id')
        ->where('role_templates.key', $template)->where('version', 7)->value('role_template_versions.id');
    $holds = fn (string $template) => $landlord->table('role_template_version_permissions')
        ->where('role_template_version_id', $versionId($template))->where('permission_key', 'subjects.configure')->exists();

    expect($holds('coordinator'))->toBeTrue()
        ->and($holds('principal'))->toBeTrue()
        ->and($holds('vice_principal'))->toBeTrue();
});
