<?php

use App\Domain\Exams\Actions\ChangeExamStatus;
use App\Domain\Exams\Actions\SaveMarks;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class, ManagesSchoolPlan::class);

beforeEach(function () {
    $this->enableModules(['exams']);
    $this->setUpStudentSchool();

    $this->phone = $this->nextPhone();
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)]);
    $this->kabir = $this->makeStudent($this->main1A, 'Kabir', 'Mehta');

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $add = app(AddSubject::class);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one]);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one]);
    $add->handle($this->owner, 'Music', 'mus', SubjectType::CoScholastic, [$one]);

    $this->exam = Exam::query()->create([
        'ulid' => strtolower((string) Str::ulid()),
        'academic_year_id' => AcademicYear::query()->where('is_current', true)->value('id'),
        'name' => 'Periodic Test 1',
        'type' => 'periodic_test',
        'max_marks' => 40,
        'starts_on' => CalendarDate::today(),
        'ends_on' => CalendarDate::today(),
        'status' => ExamStatus::Marking,
        'applies_to_all_classes' => true,
        'applies_to_all_campuses' => true,
    ]);

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function resultsParentSignIn(): void
{
    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

function enterResults($subject, string $riya, string $kabir, bool $publish): void
{
    $test = test();

    app(SaveMarks::class)->handle($test->owner, $test->exam, $test->main1A->ulid, $subject->ulid, [
        $test->riya->ulid => $riya,
        $test->kabir->ulid => $kabir,
    ], $publish);
}

it('shows a child only published marks, with awaiting subjects, totals and grades', function () {
    enterResults($this->english, '36', '20', publish: true);
    enterResults($this->maths, '38', '30', publish: false);

    resultsParentSignIn();

    $this->get(route('parent.results'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/results')
            ->where('shell.exams_enabled', true)
            ->has('results.exams', 1)
            ->where('results.exams.0.name', 'Periodic Test 1')
            ->where('results.exams.0.pending_count', 1)
            ->where('results.exams.0.subjects.0.subject_name', 'English')
            ->where('results.exams.0.subjects.0.state', 'published')
            ->where('results.exams.0.subjects.0.marks', '36')
            ->where('results.exams.0.subjects.0.percent', 90)
            ->where('results.exams.0.subjects.0.grade', 'A2')
            ->where('results.exams.0.subjects.0.grade_point', 9)
            ->where('results.exams.0.subjects.1.subject_name', 'Mathematics')
            ->where('results.exams.0.subjects.1.state', 'awaiting')
            ->where('results.exams.0.subjects.1.marks', null)
            ->where('results.exams.0.total_marks', '36')
            ->where('results.exams.0.total_max', 40)
            ->where('results.exams.0.percent_tenths', 900)
            ->where('results.exams.0.grade', 'A2')
            ->has('results.exams.0.subjects', 2)
            ->has('results.scale', 8)
            ->where('admission_number', fn ($number) => is_string($number) && $number !== '')
            ->missing('results.exams.0.id'));
});

it('shows every subject once the whole exam is published, including absences', function () {
    enterResults($this->english, '36', '20', publish: false);
    enterResults($this->maths, 'AB', '30', publish: false);
    app(ChangeExamStatus::class)->handle($this->owner, $this->exam, ExamStatus::Published);

    resultsParentSignIn();

    $this->get(route('parent.results'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.exams.0.pending_count', 0)
            ->where('results.exams.0.subjects.1.state', 'absent')
            ->where('results.exams.0.total_marks', '36')
            ->where('results.exams.0.total_max', 40));
});

it('opens the printable report card for an exam with published marks', function () {
    enterResults($this->english, '36', '20', publish: true);
    enterResults($this->maths, '37.5', '30', publish: true);

    resultsParentSignIn();

    $this->get(route('parent.results.report-card', $this->exam->ulid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/report-card')
            ->where('card.student_name', 'Riya Sharma')
            ->where('card.class_label', 'Class 1-A')
            ->where('card.session_name', '2026-27')
            ->where('card.result.subjects.1.marks', '37.5')
            ->where('card.result.subjects.1.percent', 94)
            ->where('card.result.subjects.1.grade', 'A1')
            ->where('card.result.total_marks', '73.5')
            ->where('card.result.total_max', 80)
            ->where('card.result.percent_tenths', 919)
            ->where('card.result.grade', 'A1')
            ->where('card.attendance', null)
            ->missing('card.result.id'));
});

it('has no report card while nothing is published', function () {
    enterResults($this->english, '36', '20', publish: false);

    resultsParentSignIn();

    $this->get(route('parent.results.report-card', $this->exam->ulid))->assertNotFound();
});

it('never shows another family\'s child', function () {
    enterResults($this->english, '36', '20', publish: true);

    resultsParentSignIn();

    $this->get(route('parent.results', ['child' => $this->kabir->ulid]))->assertNotFound();
    $this->get(route('parent.results.report-card', [$this->exam->ulid, 'child' => $this->kabir->ulid]))->assertNotFound();
});

it('is closed when exams are not in the school\'s plan', function () {
    resultsParentSignIn();
    $this->disableModule('exams');

    $this->get(route('parent.results'))->assertNotFound();
    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page->where('shell.exams_enabled', false));
});
