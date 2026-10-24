<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Exams\Contracts\ExamNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamDateSheetEntry;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\CreateHoliday;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Shared\CalendarDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // Every weekday is a school day, so the tests do not depend on the day they run.
    app(UpdateAttendanceCalendar::class)->handle($this->owner, [1, 2, 3, 4, 5, 6, 7], 7);

    $this->class1 = SchoolClass::query()->where('level', 1)->firstOrFail();
    $this->class2 = SchoolClass::query()->where('level', 2)->firstOrFail();

    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$this->class1, $this->class2]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$this->class1]);
    $this->hindi = $add->handle($this->owner, 'Hindi', 'hin', SubjectType::Elective, [$this->class2]);
    $this->music = $add->handle($this->owner, 'Music', 'mus', SubjectType::CoScholastic, [$this->class1, $this->class2]);
});

function examDay(int $offset = 14): string
{
    return (new DateTimeImmutable(CalendarDate::today()))->modify(sprintf('%+d days', $offset))->format('Y-m-d');
}

/**
 * A valid exam for every class and campus; override what a test changes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function examPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'December Unit Test',
        'type' => 'unit_test',
        'max_marks' => 25,
        'starts_on' => examDay(),
        'ends_on' => null,
        'all_classes' => true,
        'class_ulids' => [],
        'all_campuses' => true,
        'campus_ulids' => [],
        'date_sheet' => [],
        'instructions' => null,
        'show_on_calendar' => true,
        'notify_parents' => false,
    ], $overrides);
}

function makeExam(array $overrides = []): Exam
{
    test()->actingAs(test()->owner)->post(route('exams.store'), examPayload($overrides))->assertSessionHasNoErrors();

    return Exam::query()->where('name', $overrides['name'] ?? 'December Unit Test')->latest('id')->firstOrFail();
}

function examAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('creates an exam for all classes and campuses with a date sheet, an audit event and the parent notification', function () {
    $this->mock(ExamNotifier::class)->shouldReceive('notify')->once()->with(Mockery::type('string'), 'scheduled');

    $this->actingAs($this->owner)
        ->post(route('exams.store'), examPayload([
            'type' => 'periodic_test',
            'max_marks' => 40,
            'ends_on' => examDay(15),
            'instructions' => 'Chapters 5 to 7.',
            'notify_parents' => true,
            'date_sheet' => [
                ['subject_ulid' => $this->english->ulid, 'held_on' => examDay(14), 'begins_at' => '08:30'],
                ['subject_ulid' => $this->maths->ulid, 'held_on' => examDay(15), 'begins_at' => null],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $exam = Exam::query()->firstOrFail();

    expect($exam->status)->toBe(ExamStatus::Scheduled)
        ->and($exam->max_marks)->toBe(40)
        ->and($exam->applies_to_all_classes)->toBeTrue()
        ->and($exam->applies_to_all_campuses)->toBeTrue()
        ->and($exam->ends_on)->toBe(examDay(15))
        ->and($exam->dateSheet()->count())->toBe(2)
        ->and(DB::connection('tenant')->table('exam_school_class')->count())->toBe(0);

    $event = examAudit('exams.exam_created')->firstOrFail();
    expect($event->subject_ulid)->toBe($exam->ulid)
        ->and($event->after['date_sheet_subjects'])->toBe(2);
});

it('stores the chosen classes and campuses, and a one-day test ends the day it starts', function () {
    $exam = makeExam([
        'all_classes' => false,
        'class_ulids' => [$this->class2->ulid],
        'all_campuses' => false,
        'campus_ulids' => [$this->main->ulid],
    ]);

    expect($exam->ends_on)->toBe($exam->starts_on)
        ->and($exam->applies_to_all_classes)->toBeFalse()
        ->and(DB::connection('tenant')->table('exam_school_class')->where('exam_id', $exam->id)->pluck('school_class_id')->all())->toBe([$this->class2->id])
        ->and(DB::connection('tenant')->table('campus_exam')->where('exam_id', $exam->id)->pluck('campus_id')->all())->toBe([$this->main->id]);
});

it('shows the screen with ulids only, counts, labels and the form choices', function () {
    $open = makeExam(['name' => 'October Unit Test', 'all_classes' => false, 'class_ulids' => [$this->class1->ulid, $this->class2->ulid]]);
    $this->actingAs($this->owner)->put(route('exams.status', $open), ['status' => 'marking'])->assertSessionHasNoErrors();
    makeExam(['name' => 'Periodic Test 2', 'type' => 'periodic_test']);

    $this->actingAs($this->owner)
        ->get(route('exams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/index')
            ->where('overview.scheduled_count', 1)
            ->where('overview.marking_count', 1)
            ->where('overview.published_count', 0)
            ->has('overview.exams', 2)
            ->where('overview.exams.0.name', 'October Unit Test')
            ->where('overview.exams.0.classes_label', 'Class 1, 2')
            ->where('overview.exams.0.can_change', true)
            ->where('overview.exams.0.can_publish', true)
            ->where('overview.exams.1.classes_label', 'All classes')
            ->where('overview.exams.1.can_delete', true)
            ->where('overview.form.may_choose_all_classes', true)
            ->has('overview.form.classes', 2)
            ->has('overview.form.campuses', 2)
            ->where('overview.form.subjects_by_class.0.subjects.0.ulid', $this->english->ulid)
            ->missing('overview.exams.0.id'));

    // Co-scholastic subjects are never offered on a date sheet.
    $page = $this->actingAs($this->owner)->get(route('exams.index'))->viewData('page');
    expect(json_encode($page['props']['overview']['form']['subjects_by_class']))->not->toContain($this->music->ulid);
});

it('refuses duplicate names, ignoring case, but lets a cancelled exam\'s name be used again', function () {
    $first = makeExam(['name' => 'Term Test']);

    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['name' => 'term test']))->assertSessionHasErrors('exam');
    expect(Exam::query()->count())->toBe(1);

    $this->actingAs($this->owner)->put(route('exams.status', $first), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    makeExam(['name' => 'term test']);

    // Restoring the cancelled one now clashes with the live exam of the same name.
    $this->actingAs($this->owner)->put(route('exams.status', $first), ['status' => 'scheduled'])->assertSessionHasErrors('exam');
    expect($first->fresh()->status)->toBe(ExamStatus::Cancelled);
});

it('refuses bad dates, closed days and a selection that matches no section', function () {
    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['starts_on' => examDay(-1)]))->assertSessionHasErrors('exam');
    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['starts_on' => examDay(5), 'ends_on' => examDay(4)]))->assertSessionHasErrors('exam');

    app(CreateHoliday::class)->handle($this->owner, examDay(20), 'Diwali');
    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['starts_on' => examDay(20)]))->assertSessionHasErrors('exam');

    // Class 3 exists but has no section anywhere.
    $three = app(CreateSchoolClass::class)->handle($this->owner, 'Class 3', 3);
    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['all_classes' => false, 'class_ulids' => [$three->ulid]]))->assertSessionHasErrors('exam');

    $this->actingAs($this->owner)->post(route('exams.store'), examPayload(['name' => 'ab', 'max_marks' => 500]))->assertSessionHasErrors(['name', 'max_marks']);

    expect(Exam::query()->count())->toBe(0);
});

it('checks the date sheet: examined subjects of the chosen classes, once each, inside the dates, on school days', function () {
    $entry = fn ($subject, $day) => ['subject_ulid' => $subject->ulid, 'held_on' => examDay($day), 'begins_at' => null];
    $send = fn (array $sheet, array $more = []) => $this->actingAs($this->owner)
        ->post(route('exams.store'), examPayload(['ends_on' => examDay(16), 'date_sheet' => $sheet] + $more));

    $send([$entry($this->english, 14), $entry($this->english, 15)])->assertSessionHasErrors('exam');
    $send([$entry($this->english, 30)])->assertSessionHasErrors('exam');
    $send([$entry($this->music, 14)])->assertSessionHasErrors('exam');
    // Maths is taught in class 1 only.
    $send([$entry($this->maths, 14)], ['all_classes' => false, 'class_ulids' => [$this->class2->ulid]])->assertSessionHasErrors('exam');

    app(CreateHoliday::class)->handle($this->owner, examDay(15), 'Local holiday');
    $send([$entry($this->english, 15)])->assertSessionHasErrors('exam');

    expect(Exam::query()->count())->toBe(0);

    $send([$entry($this->maths, 14)], ['all_classes' => false, 'class_ulids' => [$this->class1->ulid]])->assertSessionHasNoErrors();
    expect(ExamDateSheetEntry::query()->count())->toBe(1);
});

it('changes an exam, replacing its classes and date sheet, and records before and after', function () {
    $exam = makeExam(['date_sheet' => [['subject_ulid' => $this->english->ulid, 'held_on' => examDay(14), 'begins_at' => null]]]);

    $this->actingAs($this->owner)
        ->put(route('exams.update', $exam), examPayload([
            'name' => 'December Test (revised)',
            'max_marks' => 30,
            'all_classes' => false,
            'class_ulids' => [$this->class1->ulid],
            'date_sheet' => [['subject_ulid' => $this->maths->ulid, 'held_on' => examDay(14), 'begins_at' => '09:00']],
        ]))
        ->assertSessionHasNoErrors();

    $exam->refresh();

    expect($exam->name)->toBe('December Test (revised)')
        ->and($exam->max_marks)->toBe(30)
        ->and($exam->applies_to_all_classes)->toBeFalse()
        ->and(ExamDateSheetEntry::query()->where('exam_id', $exam->id)->pluck('subject_id')->all())->toBe([$this->maths->id]);

    $event = examAudit('exams.exam_updated')->firstOrFail();
    expect($event->before['name'])->toBe('December Unit Test')
        ->and($event->after['name'])->toBe('December Test (revised)');

    // Saving without touching the name does not clash with itself.
    $this->actingAs($this->owner)->put(route('exams.update', $exam), examPayload(['name' => 'December Test (revised)']))->assertSessionHasNoErrors();
});

it('moves an exam through marking to published and tells parents, and published is final', function () {
    $exam = makeExam();
    $this->mock(ExamNotifier::class)->shouldReceive('notify')->once()->with($exam->ulid, 'results_published');

    // Scheduled cannot be published without marking.
    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'published'])->assertSessionHasErrors('exam');

    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'marking'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'scheduled'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'marking'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'published'])->assertSessionHasNoErrors();

    expect($exam->fresh()->status)->toBe(ExamStatus::Published)
        ->and($exam->fresh()->published_at)->not->toBeNull()
        ->and(examAudit('exams.status_changed')->count())->toBe(4);

    foreach (['scheduled', 'marking', 'cancelled'] as $status) {
        $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => $status])->assertSessionHasErrors('exam');
    }

    $this->actingAs($this->owner)->put(route('exams.update', $exam), examPayload())->assertSessionHasErrors('exam');
    $this->actingAs($this->owner)->delete(route('exams.destroy', $exam))->assertSessionHasErrors('exam');
});

it('cancels and restores an exam', function () {
    $exam = makeExam();

    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    expect($exam->fresh()->status)->toBe(ExamStatus::Cancelled);

    $this->actingAs($this->owner)->put(route('exams.update', $exam), examPayload())->assertSessionHasErrors('exam');

    $this->actingAs($this->owner)->put(route('exams.status', $exam), ['status' => 'scheduled'])->assertSessionHasNoErrors();
    expect($exam->fresh()->status)->toBe(ExamStatus::Scheduled);
});

it('deletes an exam that was never published, with its date sheet, and keeps the audit event', function () {
    $exam = makeExam(['date_sheet' => [['subject_ulid' => $this->english->ulid, 'held_on' => examDay(14), 'begins_at' => null]]]);

    $this->actingAs($this->owner)->delete(route('exams.destroy', $exam))->assertSessionHasNoErrors();

    expect(Exam::query()->count())->toBe(0)
        ->and(ExamDateSheetEntry::query()->count())->toBe(0)
        ->and(examAudit('exams.exam_deleted')->firstOrFail()->before['name'])->toBe('December Unit Test');
});

it('lets a wing coordinator work only inside their wing', function () {
    $wing = $this->staffWith(['exams.schedule', 'results.publish'], Scope::wing([$this->main->ulid], 1, 1));

    $inside = makeExam(['name' => 'Class 1 Main Test', 'all_classes' => false, 'class_ulids' => [$this->class1->ulid], 'all_campuses' => false, 'campus_ulids' => [$this->main->ulid]]);
    $wide = makeExam(['name' => 'School Wide Test']);
    $elsewhere = makeExam(['name' => 'Class 2 Test', 'all_classes' => false, 'class_ulids' => [$this->class2->ulid]]);

    $this->actingAs($wing)->get(route('exams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.exams', 2)
            ->where('overview.exams.0.name', 'Class 1 Main Test')
            ->where('overview.exams.0.can_change', true)
            ->where('overview.exams.1.name', 'School Wide Test')
            ->where('overview.exams.1.can_change', false)
            ->where('overview.form.may_choose_all_classes', false)
            ->has('overview.form.classes', 1)
            ->has('overview.form.campuses', 1));

    // Inside the wing: allowed, including publishing.
    $this->actingAs($wing)->put(route('exams.status', $inside), ['status' => 'marking'])->assertSessionHasNoErrors();
    $this->actingAs($wing)->put(route('exams.status', $inside), ['status' => 'published'])->assertSessionHasNoErrors();

    // Seen but wider than the wing: refused. Not touching the wing at all: not found.
    $this->actingAs($wing)->put(route('exams.status', $wide), ['status' => 'marking'])->assertForbidden();
    $this->actingAs($wing)->delete(route('exams.destroy', $wide))->assertForbidden();
    $this->actingAs($wing)->put(route('exams.status', $elsewhere), ['status' => 'marking'])->assertNotFound();
    $this->actingAs($wing)->put(route('exams.update', $elsewhere), examPayload(['name' => 'Class 2 Test']))->assertNotFound();

    // New exams must stay inside the wing.
    $this->actingAs($wing)->post(route('exams.store'), examPayload(['name' => 'Everything']))->assertSessionHasErrors('exam');
    $this->actingAs($wing)->post(route('exams.store'), examPayload([
        'name' => 'Wing Test', 'all_classes' => false, 'class_ulids' => [$this->class2->ulid], 'all_campuses' => false, 'campus_ulids' => [$this->main->ulid],
    ]))->assertSessionHasErrors('exam');
    $this->actingAs($wing)->post(route('exams.store'), examPayload([
        'name' => 'Wing Test', 'all_classes' => false, 'class_ulids' => [$this->class1->ulid], 'all_campuses' => false, 'campus_ulids' => [$this->main->ulid],
    ]))->assertSessionHasNoErrors();
});

it('needs results.publish as well as exams.schedule to publish', function () {
    $exam = makeExam();
    $scheduler = $this->staffWith(['exams.schedule']);

    $this->actingAs($scheduler)->put(route('exams.status', $exam), ['status' => 'marking'])->assertSessionHasNoErrors();
    $this->actingAs($scheduler)->put(route('exams.status', $exam), ['status' => 'published'])->assertForbidden();

    $this->actingAs($scheduler)->get(route('exams.index'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.exams.0.can_publish', false));

    expect($exam->fresh()->status)->toBe(ExamStatus::Marking);
});

it('applies the authorization matrix to the list and to changing an exam', function () {
    $exam = makeExam(['all_classes' => false, 'class_ulids' => [$this->class1->ulid], 'all_campuses' => false, 'campus_ulids' => [$this->main->ulid]]);
    $make = fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope);

    $this->assertAuthorizationMatrix(
        module: 'exams',
        permission: 'exams.schedule',
        inScope: Scope::wing([$this->main->ulid], 1, 1),
        outOfScope: null,
        makeActor: $make,
        send: fn ($actor) => $this->actingAs($actor)->get(route('exams.index')),
    );

    $this->assertAuthorizationMatrix(
        module: 'exams',
        permission: 'exams.schedule',
        inScope: Scope::wing([$this->main->ulid], 1, 1),
        outOfScope: Scope::wing([$this->north->ulid], 1, 1),
        makeActor: $make,
        send: fn ($actor) => $this->actingAs($actor)->put(route('exams.status', $exam), ['status' => $exam->fresh()->status === ExamStatus::Marking ? 'scheduled' : 'marking']),
    );
});

it('keeps one school\'s exams out of another school', function () {
    makeExam();

    expect($this->otherSchool->execute(fn () => Exam::query()->count()))->toBe(0)
        ->and(Exam::query()->count())->toBe(1);
});
