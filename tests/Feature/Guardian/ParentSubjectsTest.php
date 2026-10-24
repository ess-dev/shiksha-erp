<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\SetSectionSubjectTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\SectionSubjectProgress;
use App\Domain\School\Models\StudentSubjectNote;
use App\Domain\School\Models\SubjectSyllabus;
use App\Domain\Students\Data\NewGuardian;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, BuildsStudents::class, ManagesSchoolPlan::class);

beforeEach(function () {
    $this->setUpStudentSchool();

    $this->phone = $this->nextPhone();
    $priya = fn () => [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)];
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', $priya());
    $this->arjun = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', $priya());
    $this->other = $this->makeStudent($this->main1A, 'Kabir', 'Mehta');

    $this->classTeacher = app(CreateStaffRecord::class)->handle($this->staff()->ulid, 'Neha Verma');
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, $this->classTeacher->ulid);

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $two = SchoolClass::query()->where('level', 2)->firstOrFail();
    $add = app(AddSubject::class);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one, $two]);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one]);

    $this->year = AcademicYear::query()->where('is_current', true)->firstOrFail();

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function parentSignIn(): void
{
    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

function saveMathsSyllabus(AcademicYear $year, int $level = 1, array $chapters = ['Patterns', 'Lines', 'Number play', 'Fractions']): void
{
    SubjectSyllabus::query()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => SchoolClass::query()->where('level', $level)->value('id'),
        'subject_id' => test()->maths->id,
        'book' => 'Ganita Prakash',
        'chapters' => $chapters,
        'assessment' => 'Periodic tests 20%',
    ]);
}

it('shows a child the subjects of their class with book, teacher, progress and both notes', function () {
    saveMathsSyllabus($this->year);
    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1A, $this->maths, app(CreateStaffRecord::class)->handle($this->staff()->ulid, 'Kabir Anand')->ulid);

    SectionSubjectProgress::query()->create([
        'section_id' => $this->main1A->id, 'subject_id' => $this->maths->id,
        'done_chapters' => [0, 1], 'current_chapter' => 2, 'periods_per_week' => 7, 'note' => 'Test on 28 October.',
    ]);
    StudentSubjectNote::query()->create(['academic_year_id' => $this->year->id, 'student_id' => $this->riya->id, 'subject_id' => $this->maths->id, 'note' => 'Strong in mental maths.']);
    StudentSubjectNote::query()->create(['academic_year_id' => $this->year->id, 'student_id' => $this->other->id, 'subject_id' => $this->maths->id, 'note' => 'Kabir only.']);

    parentSignIn();

    $this->get(route('parent.subjects', ['child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/subjects')
            ->where('child_first_name', 'Riya')
            ->where('section_label', 'Class 1-A')
            ->where('is_current_session', true)
            ->has('subjects', 2)
            ->where('subjects.0.name', 'English')
            ->where('subjects.0.teacher_name', 'Neha Verma')
            ->where('subjects.0.percent_done', null)
            ->where('subjects.0.chapters', [])
            ->where('subjects.1.name', 'Mathematics')
            ->where('subjects.1.book', 'Ganita Prakash')
            ->where('subjects.1.teacher_name', 'Kabir Anand')
            ->where('subjects.1.periods_per_week', 7)
            ->where('subjects.1.percent_done', 50)
            ->where('subjects.1.current_chapter', 'Number play')
            ->where('subjects.1.teacher_note', 'Test on 28 October.')
            ->where('subjects.1.student_note', 'Strong in mental maths.')
            ->where('subjects.1.assessment', 'Periodic tests 20%')
            ->where('subjects.1.chapters.0', ['number' => 1, 'title' => 'Patterns', 'state' => 'done'])
            ->where('subjects.1.chapters.2.state', 'current')
            ->where('subjects.1.chapters.3.state', 'upcoming'));
});

it('keeps another section\'s progress and another child\'s notes out of the page', function () {
    saveMathsSyllabus($this->year, 1);
    saveMathsSyllabus($this->year, 2, ['Counting', 'Shapes']);

    SectionSubjectProgress::query()->create(['section_id' => $this->main1A->id, 'subject_id' => $this->maths->id, 'done_chapters' => [0], 'current_chapter' => null, 'periods_per_week' => 6, 'note' => 'Class 1 only.']);
    StudentSubjectNote::query()->create(['academic_year_id' => $this->year->id, 'student_id' => $this->riya->id, 'subject_id' => $this->maths->id, 'note' => 'For Riya.']);

    parentSignIn();

    $this->get(route('parent.subjects', ['child' => $this->arjun->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('section_label', 'Class 2-A')
            ->has('subjects', 1)
            ->where('subjects.0.percent_done', 0)
            ->where('subjects.0.teacher_note', null)
            ->where('subjects.0.student_note', null)
            ->where('subjects.0.chapters', fn ($chapters) => count($chapters) === 2));
});

it('treats another family\'s child as not found and lets a guest nowhere', function () {
    $this->get(route('parent.subjects'))->assertRedirect();

    parentSignIn();

    $this->get(route('parent.subjects', ['child' => $this->other->ulid]))->assertNotFound();
});

it('shows a past session only for a child who was enrolled then, using that year\'s data', function () {
    $past = app(CreateAcademicYear::class)->handle($this->owner, '2025-26', '2025-04-01', '2026-03-31');

    parentSignIn();
    $this->put(route('parent.session.select'), ['session' => $past->ulid]);

    $this->get(route('parent.subjects'))->assertInertia(fn (Assert $page) => $page
        ->where('is_current_session', false)
        ->where('child_first_name', null)
        ->where('subjects', []));

    // Riya was in Class 1-A of 2025-26 as well; that year's syllabus and progress are what show.
    $oldSection = app(CreateSection::class)->handle($this->owner, $past, $this->main, SchoolClass::query()->where('level', 1)->firstOrFail(), 'A', 40);
    DB::connection('tenant')->table('enrollments')->insert([
        'student_id' => $this->riya->id, 'academic_year_id' => $past->id, 'section_id' => $oldSection->id,
        'roll_number' => 3, 'started_on' => '2025-04-01', 'ended_on' => '2026-03-31', 'result' => 'promoted', 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    saveMathsSyllabus($past, 1, ['Counting', 'Shapes']);
    saveMathsSyllabus($this->year, 1, ['This year']);
    SectionSubjectProgress::query()->create(['section_id' => $oldSection->id, 'subject_id' => $this->maths->id, 'done_chapters' => [0, 1], 'current_chapter' => null, 'periods_per_week' => 5, 'note' => null]);

    $this->get(route('parent.subjects', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('session_name', '2025-26')
        ->where('is_current_session', false)
        ->has('subjects', 1)
        ->where('subjects.0.name', 'Mathematics')
        ->where('subjects.0.percent_done', 100)
        ->where('subjects.0.chapters.0.title', 'Counting'));

    // The child of Class 2 was not enrolled in 2025-26.
    $this->get(route('parent.subjects', ['child' => $this->arjun->ulid]))->assertNotFound();
});

it('shows nothing from another school and is blocked while the school is suspended', function () {
    saveMathsSyllabus($this->year);

    expect($this->otherSchool->execute(fn () => SubjectSyllabus::query()->count()))->toBe(0);

    parentSignIn();
    $this->setSchoolStatus(TenantStatus::Suspended);

    $this->get(route('parent.subjects'))->assertForbidden();
});
