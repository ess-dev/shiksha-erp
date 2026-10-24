<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Exams\Contracts\MarksNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamMark;
use App\Domain\Exams\Models\ExamMarkSheet;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\SetSectionSubjectTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsAuthorizationMatrix;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class, AssertsAuthorizationMatrix::class);

beforeEach(function () {
    $this->enableModules(['exams']);
    $this->setUpStudentSchool();

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $two = SchoolClass::query()->where('level', 2)->firstOrFail();
    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one, $two]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one, $two]);
    $add->handle($this->owner, 'Music', 'mus', SubjectType::CoScholastic, [$one, $two]);

    $this->teacher = $this->staff();
    $this->teacherStaff = app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma');
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, $this->teacherStaff->ulid);

    $this->ananya = $this->makeStudent($this->main1A, 'Ananya', 'Reddy');
    $this->ira = $this->makeStudent($this->main1A, 'Ira', 'Nair');
    $this->kabir = $this->makeStudent($this->main1A, 'Kabir', 'Tiwari');

    $this->exam = marksExam();

    $this->notifier = new class implements MarksNotifier
    {
        /** @var list<list<string>> */
        public array $sent = [];

        public function marksPublished(string $examUlid, string $sectionUlid, string $subjectUlid): void
        {
            $this->sent[] = [$examUlid, $sectionUlid, $subjectUlid];
        }
    };
    $this->app->instance(MarksNotifier::class, $this->notifier);
});

function marksExam(ExamStatus $status = ExamStatus::Marking, int $maxMarks = 25, string $name = 'October Unit Test'): Exam
{
    return Exam::query()->create([
        'ulid' => strtolower((string) Str::ulid()),
        'academic_year_id' => AcademicYear::query()->where('is_current', true)->value('id'),
        'name' => $name,
        'type' => 'unit_test',
        'max_marks' => $maxMarks,
        'starts_on' => CalendarDate::today(),
        'ends_on' => CalendarDate::today(),
        'status' => $status,
        'applies_to_all_classes' => true,
        'applies_to_all_campuses' => true,
    ]);
}

/**
 * @param  array<string, string|null>  $marks
 */
function putMarks(Identity $actor, array $marks, bool $publish = false, ?Section $section = null, $subject = null, ?Exam $exam = null)
{
    $test = test();

    return $test->actingAs($actor)->put(route('marks.update', [
        $exam ?? $test->exam,
        ($section ?? $test->main1A)->ulid,
        ($subject ?? $test->english)->ulid,
    ]), ['marks' => $marks, 'publish' => $publish]);
}

function allMarks(string $ananya = '20', string $ira = '12.5', string $kabir = 'AB'): array
{
    $test = test();

    return [$test->ananya->ulid => $ananya, $test->ira->ulid => $ira, $test->kabir->ulid => $kabir];
}

function marksAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('opens the class teacher\'s sheet with both subjects as theirs, ulids only', function () {
    $this->actingAs($this->teacher)
        ->get(route('marks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/marks')
            ->has('screen.exams', 1)
            ->where('screen.exams.0.name', 'October Unit Test')
            ->where('screen.exams.0.max_marks', 25)
            ->has('screen.sections', 1)
            ->where('screen.sections.0.label', 'Class 1-A')
            ->has('screen.subjects', 2)
            ->where('screen.subjects.0.name', 'English')
            ->where('screen.subjects.0.is_mine', true)
            ->where('screen.sheet.state', 'editable')
            ->has('screen.sheet.rows', 3)
            ->where('screen.sheet.rows.0.value', '')
            ->where('screen.pass_percent', 33)
            ->has('screen.scale', 8)
            ->where('screen.scale.0.grade', 'A1')
            ->where('screen.can_change_scale', false)
            ->missing('screen.sheet.rows.0.id')
            ->missing('screen.exams.0.id'));
});

it('lists no exam before marking opens', function () {
    $this->exam->update(['status' => ExamStatus::Scheduled]);

    $this->actingAs($this->teacher)
        ->get(route('marks.index'))
        ->assertInertia(fn (Assert $page) => $page->has('screen.exams', 0)->where('screen.sheet', null));

    putMarks($this->teacher, allMarks())->assertSessionHasErrors(['marks' => 'Marking for this exam is closed.']);
});

it('saves a draft in half-marks with AB and blanks, and audits each change', function () {
    putMarks($this->teacher, allMarks('20', '12.5', 'AB'))->assertSessionHasNoErrors();

    $rows = ExamMark::query()->orderBy('student_id')->get();

    expect($rows->pluck('half_marks')->all())->toBe([40, 25, null])
        ->and($rows->pluck('is_absent')->all())->toBe([false, false, true])
        ->and($rows->pluck('section_id')->unique()->all())->toBe([$this->main1A->id])
        ->and(ExamMarkSheet::query()->firstOrFail()->published_at)->toBeNull();

    putMarks($this->teacher, allMarks('21', '', 'AB'))->assertSessionHasNoErrors();

    $audit = marksAudit('marks.saved')->latest('id')->firstOrFail();

    expect(ExamMark::query()->count())->toBe(2)
        ->and($audit->before['marks'])->toBe([$this->ananya->ulid => '20', $this->ira->ulid => '12.5'])
        ->and($audit->after['marks'])->toBe([$this->ananya->ulid => '21', $this->ira->ulid => ''])
        ->and($this->exam->hasMarks())->toBeTrue();

    $this->actingAs($this->teacher)
        ->get(route('marks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.sheet.rows.0.value', '21')
            ->where('screen.sheet.rows.1.value', '')
            ->where('screen.sheet.rows.2.value', 'AB'));
});

it('refuses marks that are not 0 to the maximum in halves or AB', function () {
    putMarks($this->teacher, allMarks('26', '12.3', 'AB'))
        ->assertSessionHasErrors(['marks' => 'Fix 2 marks first: use 0–25 (halves allowed) or AB.']);

    expect(ExamMark::query()->count())->toBe(0);
});

it('refuses a student who is not on the section\'s roster', function () {
    $other = $this->makeStudent($this->main1B, 'Pari', 'Gill');

    putMarks($this->teacher, [$other->ulid => '10'])->assertNotFound();
});

it('publishes only a complete sheet, freezes the grading scale and tells parents', function () {
    putMarks($this->teacher, allMarks('20', '', 'AB'), publish: true)
        ->assertSessionHasErrors(['marks' => 'Enter marks (or AB) for 1 more student before publishing.']);

    expect($this->exam->fresh()->grade_scale)->toBeNull();

    putMarks($this->teacher, allMarks(), publish: true)->assertSessionHasNoErrors();

    expect(ExamMarkSheet::query()->firstOrFail()->published_at)->not->toBeNull()
        ->and($this->exam->fresh()->grade_scale[0])->toEqual(['grade' => 'A1', 'min_percent' => 91, 'grade_point' => 10])
        ->and(marksAudit('marks.published')->count())->toBe(1)
        ->and($this->notifier->sent)->toBe([[$this->exam->ulid, $this->main1A->ulid, $this->english->ulid]]);

    putMarks($this->teacher, allMarks('21'))
        ->assertSessionHasErrors(['marks' => 'These marks are published. Ask the principal to reopen them for a correction.']);

    $this->actingAs($this->teacher)
        ->get(route('marks.index', ['subject' => $this->english->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.sheet.state', 'published')
            ->where('screen.sheet.published_by', 'Neha Verma')
            ->where('screen.sheet.can_reopen', false));
});

it('lets a chosen subject teacher enter their subject and leaves it view only for the class teacher', function () {
    $kabirAnand = $this->staff();
    $staff = app(CreateStaffRecord::class)->handle($kabirAnand->ulid, 'Kabir Anand');
    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1A, $this->maths, $staff->ulid);

    $this->actingAs($this->teacher)
        ->get(route('marks.index', ['subject' => $this->maths->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.subjects.1.name', 'Mathematics')
            ->where('screen.subjects.1.is_mine', false)
            ->where('screen.subjects.1.may_enter', false)
            ->where('screen.sheet.state', 'view_only')
            ->where('screen.sheet.teacher_name', 'Kabir Anand'));

    putMarks($this->teacher, allMarks(), subject: $this->maths)->assertForbidden();
    putMarks($kabirAnand, allMarks(), subject: $this->maths)->assertSessionHasNoErrors();
    putMarks($kabirAnand, allMarks(), subject: $this->english)->assertForbidden();
});

it('lets someone holding marks.reopen enter any subject in scope and reopen published marks with a reason', function () {
    $coordinator = $this->staffWith(['marks.reopen', 'exams.view'], Scope::wing([$this->main->ulid], 1, 1));

    putMarks($coordinator, allMarks(), publish: true)->assertSessionHasNoErrors();
    putMarks($coordinator, allMarks(), section: $this->main2A)->assertNotFound();

    $url = route('marks.reopen', [$this->exam, $this->main1A->ulid, $this->english->ulid]);

    $this->actingAs($this->teacher)->post($url, ['reason' => 'Wrong student'])->assertForbidden();
    $this->actingAs($coordinator)->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($coordinator)->post($url, ['reason' => 'Roll 2 marks were swapped'])->assertSessionHasNoErrors();

    $sheet = ExamMarkSheet::query()->firstOrFail();

    expect($sheet->published_at)->toBeNull()
        ->and($sheet->reopen_reason)->toBe('Roll 2 marks were swapped')
        ->and(marksAudit('marks.reopened')->firstOrFail()->after['reason'])->toBe('Roll 2 marks were swapped');

    putMarks($this->teacher, allMarks('19'))->assertSessionHasNoErrors();

    $this->actingAs($this->teacher)
        ->get(route('marks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.sheet.state', 'editable')
            ->where('screen.sheet.reopen_reason', 'Roll 2 marks were swapped'));
});

it('treats every subject as published and final once the whole exam is published', function () {
    putMarks($this->teacher, allMarks())->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->put(route('exams.status', $this->exam), ['status' => 'published'])
        ->assertSessionHasNoErrors();

    expect($this->exam->fresh()->grade_scale)->not->toBeNull();

    $this->actingAs($this->owner)
        ->get(route('marks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.sheet.state', 'published')
            ->where('screen.sheet.can_reopen', false));

    putMarks($this->owner, allMarks('1'))->assertSessionHasErrors('marks');
});

it('refuses an exam that is not for the section', function () {
    $this->exam->update(['applies_to_all_classes' => false]);
    DB::connection('tenant')->table('exam_school_class')->insert([
        'exam_id' => $this->exam->id,
        'school_class_id' => SchoolClass::query()->where('level', 2)->value('id'),
    ]);

    putMarks($this->teacher, allMarks())->assertNotFound();
});

it('locks maximum marks, blocks deleting the exam and shows progress on the Exams list', function () {
    putMarks($this->teacher, allMarks(), publish: true)->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->delete(route('exams.destroy', $this->exam))
        ->assertSessionHasErrors('exam');

    $this->actingAs($this->owner)
        ->get(route('exams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.exams.0.max_marks_locked', true)
            ->where('overview.exams.0.can_delete', false)
            // 4 sections x 2 examined subjects; one fully entered and published.
            ->where('overview.exams.0.marks_progress', ['sheets' => 8, 'entered' => 1, 'published' => 1]));
});

it('checks permission, scope, plan and school status', function () {
    $this->assertAuthorizationMatrix(
        module: 'exams',
        permission: 'marks.reopen',
        inScope: Scope::sections([$this->main1A->ulid]),
        outOfScope: Scope::sections([$this->north1A->ulid]),
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => putMarks($actor, allMarks()),
    );

    $this->actingAs($this->staffWith(['students.view']))->get(route('marks.index'))->assertForbidden();
});

it('keeps one school\'s marks out of another school', function () {
    putMarks($this->teacher, allMarks())->assertSessionHasNoErrors();

    expect($this->otherSchool->execute(fn () => ExamMark::query()->count()))->toBe(0)
        ->and(ExamMark::query()->count())->toBe(3);
});

it('saves the grading scale for settings managers and grades new exams with it', function () {
    putMarks($this->teacher, allMarks(), publish: true)->assertSessionHasNoErrors();

    $grades = [
        ['grade' => 'A', 'min_percent' => 85, 'grade_point' => 4],
        ['grade' => 'B', 'min_percent' => 40, 'grade_point' => 2],
        ['grade' => 'F', 'min_percent' => 0, 'grade_point' => 0],
    ];

    $this->actingAs($this->teacher)->put(route('exams.grading.update'), ['grades' => $grades])->assertForbidden();
    $this->actingAs($this->owner)
        ->put(route('exams.grading.update'), ['grades' => [$grades[1], $grades[0], $grades[2]]])
        ->assertSessionHasErrors(['grades' => 'Each grade must start below the one above it.']);
    $this->actingAs($this->owner)->put(route('exams.grading.update'), ['grades' => $grades])->assertSessionHasNoErrors();

    expect(marksAudit('settings.grade_scale_updated')->count())->toBe(1);

    $this->actingAs($this->owner)
        ->get(route('exams.grading'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/grading')
            ->where('screen.is_saved', true)
            ->where('screen.grades.0.grade', 'A')
            ->where('screen.frozen_exam_count', 1));

    // The published exam keeps the scale it was published with.
    $this->actingAs($this->owner)
        ->get(route('marks.index', ['exam' => $this->exam->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.scale.0.grade', 'A1'));

    $next = marksExam(name: 'November Unit Test');

    $this->actingAs($this->owner)
        ->get(route('marks.index', ['exam' => $next->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.scale.0.grade', 'A')
            ->where('screen.pass_percent', 40));
});
