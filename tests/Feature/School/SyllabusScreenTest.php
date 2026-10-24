<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\SetSectionSubjectTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\SectionSubjectProgress;
use App\Domain\School\Models\StudentSubjectNote;
use App\Domain\School\Models\SubjectSyllabus;
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
    $this->setUpStudentSchool();

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $two = SchoolClass::query()->where('level', 2)->firstOrFail();

    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one, $two]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one, $two]);

    $this->classTeacher = classTeacherOfSection($this->main1A);
});

function classTeacherOfSection(Section $section, string $name = 'Neha Verma'): Identity
{
    $test = test();
    $identity = $test->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, $name);
    app(SetClassTeacher::class)->handle($test->owner, $section, $staff->ulid);

    return $identity;
}

function syllabusUrl(string $route, Section $section, $subject): string
{
    return route("school.syllabus.{$route}", [$section->ulid, $subject->ulid]);
}

function saveSyllabus(Identity $actor, Section $section, $subject, string $chapters = "Patterns\nLines and angles\nNumber play\nFractions"): void
{
    test()->actingAs($actor)
        ->put(syllabusUrl('class', $section, $subject), ['book' => 'Ganita Prakash', 'chapters' => $chapters, 'assessment' => 'Periodic tests 20%'])
        ->assertSessionHasNoErrors();
}

function syllabusAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('shows a class teacher every subject of their section, with ulids only', function () {
    $this->actingAs($this->classTeacher)
        ->get(route('school.syllabus.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/syllabus')
            ->where('screen.year_name', '2026-27')
            ->has('screen.choices', 2)
            ->where('screen.choices.0.section_name', 'Class 1-A')
            ->where('screen.choices.0.subject_name', 'English')
            ->where('screen.choices.0.via_class_teacher', true)
            ->where('screen.selected.section_ulid', $this->main1A->ulid)
            ->where('screen.selected.can_edit_class', true)
            ->where('screen.selected.section_count', 3)
            ->where('screen.selected.class_syllabus.is_saved', false)
            ->where('screen.selected.class_syllabus.chapters', [])
            ->missing('screen.selected.id')
            ->missing('screen.choices.0.id'));
});

it('lets the owner pick any section and subject in the school', function () {
    $this->actingAs($this->owner)
        ->get(route('school.syllabus.index', ['section' => $this->main2A->ulid, 'subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.choices', 8)
            ->where('screen.selected.section_ulid', $this->main2A->ulid)
            ->where('screen.selected.subject_name', 'Mathematics')
            ->where('screen.selected.can_edit_class', true));
});

it('gives a chosen subject teacher the automatic role and only that subject', function () {
    $identity = $this->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Kabir Anand');

    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1B, $this->maths, $staff->ulid);

    $assignment = RoleAssignment::query()->where('staff_id', DB::connection('tenant')->table('staff')->where('ulid', $staff->ulid)->value('id'))->firstOrFail();

    expect($assignment->source)->toBe('auto')
        ->and($assignment->toScope()->sectionUlids())->toBe([$this->main1B->ulid])
        ->and(Role::query()->find($assignment->role_id)->source_template_key)->toBe('subject_teacher')
        ->and(syllabusAudit('roles.subject_teacher_synced')->exists())->toBeTrue();

    $this->actingAs($identity)
        ->get(route('school.syllabus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.choices', 1)
            ->where('screen.choices.0.section_name', 'Class 1-B')
            ->where('screen.choices.0.subject_name', 'Mathematics')
            ->where('screen.choices.0.via_class_teacher', false));

    $this->put(syllabusUrl('section', $this->main1B, $this->english), ['done' => [], 'current' => null, 'periods' => 5, 'note' => 'x'])
        ->assertNotFound();

    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1B, $this->maths, null);

    expect(RoleAssignment::query()->where('source', 'auto')->whereHas('staff', fn ($query) => $query->where('ulid', $staff->ulid))->exists())->toBeFalse();
    $this->get(route('school.syllabus.index'))->assertForbidden();
});

it('saves the textbook, trimmed chapters and assessment for the whole class, and audits it', function () {
    saveSyllabus($this->classTeacher, $this->main1A, $this->maths, "  Patterns \n\nLines and angles\r\nNumber play\n");

    $syllabus = SubjectSyllabus::query()->firstOrFail();

    expect($syllabus->book)->toBe('Ganita Prakash')
        ->and($syllabus->chapters)->toBe(['Patterns', 'Lines and angles', 'Number play'])
        ->and($syllabus->assessment)->toBe('Periodic tests 20%')
        ->and(syllabusAudit('subjects.syllabus_saved')->count())->toBe(1);

    $this->actingAs($this->owner)->get(route('school.syllabus.index', ['section' => $this->north1A->ulid, 'subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.selected.class_syllabus.chapters', ['Patterns', 'Lines and angles', 'Number play'])
            ->where('screen.selected.class_syllabus.is_saved', true));
});

it('rejects an empty or oversized chapter list', function () {
    $this->actingAs($this->classTeacher);

    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => "  \n "])->assertSessionHasErrors('chapters');
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => implode("\n", range(1, 61))])->assertSessionHasErrors('syllabus');
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => str_repeat('a', 121)])->assertSessionHasErrors('syllabus');
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['book' => str_repeat('b', 81), 'chapters' => 'One'])->assertSessionHasErrors('book');

    expect(SubjectSyllabus::query()->count())->toBe(0);
});

it('keeps the ticks of chapters that did not move and clears the rest when the list changes', function () {
    saveSyllabus($this->classTeacher, $this->main1A, $this->maths);

    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [0, 1, 3], 'current' => 2, 'periods' => 6, 'note' => 'Test on 28 October.'])
        ->assertSessionHasNoErrors();

    saveSyllabus($this->classTeacher, $this->main1A, $this->maths, "Patterns\nLines\nNumber play\nFractions\nSymmetry");

    $progress = SectionSubjectProgress::query()->firstOrFail();

    expect($progress->done_chapters)->toBe([0, 3])
        ->and($progress->current_chapter)->toBe(2)
        ->and($progress->note)->toBe('Test on 28 October.');

    saveSyllabus($this->classTeacher, $this->main1A, $this->maths, "Number play\nPatterns");

    $progress->refresh();

    expect($progress->done_chapters)->toBe([])
        ->and($progress->current_chapter)->toBeNull();
});

it('saves a section\'s progress, periods and note and audits it', function () {
    saveSyllabus($this->classTeacher, $this->main1A, $this->maths);

    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [2, 0, 0], 'current' => 1, 'periods' => 7, 'note' => ' Practise Exercise 7.3 '])
        ->assertSessionHasNoErrors();

    $progress = SectionSubjectProgress::query()->firstOrFail();

    expect($progress->done_chapters)->toBe([0, 2])
        ->and($progress->current_chapter)->toBe(1)
        ->and($progress->periods_per_week)->toBe(7)
        ->and($progress->note)->toBe('Practise Exercise 7.3')
        ->and($progress->section_id)->toBe($this->main1A->id)
        ->and(syllabusAudit('subjects.progress_saved')->where('subject_ulid', $this->main1A->ulid)->exists())->toBeTrue();

    $this->get(route('school.syllabus.index', ['section' => $this->main1A->ulid, 'subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.selected.progress.done_chapters', [0, 2])
            ->where('screen.selected.progress.current_chapter', 1)
            ->where('screen.selected.progress.note', 'Practise Exercise 7.3')
            ->where('screen.selected.progress.updated_by', 'Neha Verma'));
});

it('refuses ticks for chapters that do not exist or before a chapter list is saved', function () {
    $this->actingAs($this->classTeacher)
        ->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [0], 'current' => null, 'periods' => 5, 'note' => ''])
        ->assertSessionHasErrors('syllabus');

    saveSyllabus($this->classTeacher, $this->main1A, $this->maths);

    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [4], 'current' => null])->assertSessionHasErrors('syllabus');
    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [], 'current' => 9])->assertSessionHasErrors('syllabus');
    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [], 'periods' => 13])->assertSessionHasErrors('periods');

    $this->put(syllabusUrl('section', $this->main1A, $this->english), ['done' => [], 'current' => null, 'periods' => 4, 'note' => 'Reading aloud.'])
        ->assertSessionHasNoErrors();

    expect(SectionSubjectProgress::query()->count())->toBe(1);
});

it('saves and clears notes for individual students, only for the section\'s roster', function () {
    $riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma');
    $arjun = $this->makeStudent($this->main1A, 'Arjun', 'Sharma');
    $other = $this->makeStudent($this->main1B, 'Neel', 'Gill');

    $this->actingAs($this->classTeacher)
        ->put(syllabusUrl('students', $this->main1A, $this->maths), ['notes' => [$riya->ulid => ' Strong in mental maths. ', $arjun->ulid => '']])
        ->assertSessionHasNoErrors();

    expect(StudentSubjectNote::query()->count())->toBe(1)
        ->and(StudentSubjectNote::query()->first()->note)->toBe('Strong in mental maths.')
        ->and(StudentSubjectNote::query()->first()->student_id)->toBe($riya->id);

    $this->get(route('school.syllabus.index'))
        ->assertInertia(fn (Assert $page) => $page->has('screen.selected.students', 2));

    $this->get(route('school.syllabus.index', ['section' => $this->main1A->ulid, 'subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.selected.students.0.name', 'Riya Sharma')
            ->where('screen.selected.students.0.note', 'Strong in mental maths.')
            ->where('screen.selected.students.1.note', ''));

    $this->put(syllabusUrl('students', $this->main1A, $this->maths), ['notes' => [$other->ulid => 'Not my student']])->assertNotFound();
    $this->put(syllabusUrl('students', $this->main1A, $this->maths), ['notes' => [$riya->ulid => '']])->assertSessionHasNoErrors();

    expect(StudentSubjectNote::query()->count())->toBe(0)
        ->and(syllabusAudit('subjects.student_notes_saved')->count())->toBe(2);
});

it('starts a new year from last year\'s book and chapters without copying progress', function () {
    $last = AcademicYear::query()->create(['ulid' => strtolower((string) Str::ulid()), 'name' => '2025-26', 'starts_on' => '2025-04-01', 'ends_on' => '2026-03-31', 'is_current' => false]);

    SubjectSyllabus::query()->create([
        'academic_year_id' => $last->id,
        'school_class_id' => SchoolClass::query()->where('level', 1)->value('id'),
        'subject_id' => $this->maths->id,
        'book' => 'Old book',
        'chapters' => ['Counting', 'Shapes'],
        'assessment' => 'Annual 100%',
    ]);

    $this->actingAs($this->classTeacher)
        ->get(route('school.syllabus.index', ['section' => $this->main1A->ulid, 'subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.selected.class_syllabus.is_saved', false)
            ->where('screen.selected.class_syllabus.copied_from', '2025-26')
            ->where('screen.selected.class_syllabus.book', 'Old book')
            ->where('screen.selected.class_syllabus.chapters', ['Counting', 'Shapes'])
            ->where('screen.selected.progress.done_chapters', []));

    expect(SubjectSyllabus::query()->where('academic_year_id', '!=', $last->id)->count())->toBe(0);
});

it('refuses someone who teaches nothing here, a subject teacher changing another class, and guests', function () {
    $this->get(route('school.syllabus.index'))->assertRedirect(route('login'));

    $noPermission = $this->staffWith(['students.view']);
    $this->actingAs($noPermission)->get(route('school.syllabus.index'))->assertForbidden();
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => 'One'])->assertForbidden();

    $holderOnly = $this->staffWith(['subjects.update']);
    $this->actingAs($holderOnly)->get(route('school.syllabus.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('screen.choices', 0)->where('screen.selected', null));
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => 'One'])->assertNotFound();

    $teacherOf1B = $this->staff();
    $staff = app(CreateStaffRecord::class)->handle($teacherOf1B->ulid, 'Gurleen Kaur');
    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1B, $this->maths, $staff->ulid);

    $this->actingAs($teacherOf1B)->put(syllabusUrl('class', $this->main2A, $this->maths), ['chapters' => 'One'])->assertNotFound();
    $this->put(syllabusUrl('class', $this->main1A, $this->maths), ['chapters' => 'One'])->assertNotFound();
    $this->put(syllabusUrl('class', $this->main1B, $this->maths), ['chapters' => 'One'])->assertRedirect();

    expect(SubjectSyllabus::query()->count())->toBe(1);
});

it('limits a wing coordinator to the sections in their scope', function () {
    $coordinator = $this->staffWith(['subjects.update', 'subjects.configure'], Scope::sections([$this->main2A->ulid]));

    $this->actingAs($coordinator)->get(route('school.syllabus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('screen.choices', 2)
            ->where('screen.choices.0.section_name', 'Class 2-A'));

    $this->put(syllabusUrl('section', $this->main1A, $this->maths), ['done' => [], 'current' => null, 'periods' => 3])->assertNotFound();
    $this->put(syllabusUrl('section', $this->main2A, $this->maths), ['done' => [], 'current' => null, 'periods' => 3])->assertSessionHasNoErrors();

    // Class 2 has one section, so the coordinator covers all of it; Class 1 has three, so a class-wide change is not theirs.
    $this->put(syllabusUrl('class', $this->main2A, $this->maths), ['chapters' => 'One'])->assertRedirect();
    expect(SubjectSyllabus::query()->count())->toBe(1);
});

it('blocks a suspended school and keeps one school\'s syllabus out of another', function () {
    saveSyllabus($this->classTeacher, $this->main1A, $this->maths);

    expect($this->otherSchool->execute(fn () => SubjectSyllabus::query()->count()))->toBe(0)
        ->and(SubjectSyllabus::query()->count())->toBe(1);

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($this->classTeacher)->get(route('school.syllabus.index'))
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('gives the teacher and coordinator templates the permission from their next version', function () {
    $landlord = DB::connection('landlord');
    $holds = fn (string $template, int $version) => $landlord->table('role_template_version_permissions')
        ->where('role_template_version_id', $landlord->table('role_template_versions')
            ->join('role_templates', 'role_templates.id', '=', 'role_template_versions.role_template_id')
            ->where('role_templates.key', $template)->where('version', $version)->value('role_template_versions.id'))
        ->where('permission_key', 'subjects.update')->exists();

    expect($holds('principal', 9))->toBeTrue()
        ->and($holds('vice_principal', 9))->toBeTrue()
        ->and($holds('coordinator', 9))->toBeTrue()
        ->and($holds('class_teacher', 3))->toBeTrue()
        ->and($holds('subject_teacher', 3))->toBeTrue()
        ->and($holds('class_teacher', 2))->toBeFalse()
        ->and($holds('accountant', 2))->toBeFalse();
});
