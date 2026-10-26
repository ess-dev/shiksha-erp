<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkFile;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\SetSectionSubjectTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewGuardian;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsAuthorizationMatrix;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class, AssertsAuthorizationMatrix::class);

beforeEach(function () {
    Storage::fake('tenant');
    $this->enableModules(['homework']);
    $this->setUpStudentSchool();

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one]);

    // Neha Verma leads Class 1-A; Kabir Anand teaches its Mathematics.
    $this->teacher = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma')->ulid);
    $this->kabir = $this->staff();
    app(SetSectionSubjectTeacher::class)->handle($this->owner, $this->main1A, $this->maths, app(CreateStaffRecord::class)->handle($this->kabir->ulid, 'Kabir Anand')->ulid);

    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $this->nextPhone(), null, null, true)]);
    $this->ira = $this->makeStudent($this->main1A, 'Ira', 'Nair');

    $this->notifier = new class implements HomeworkNotifier
    {
        /** @var list<string> */
        public array $events = [];

        public function notify(string $event, string $assignmentUlid, string $sectionUlid, array $studentUlids): void
        {
            $this->events[] = $event.':'.count($studentUlids);
        }
    };
    $this->app->instance(HomeworkNotifier::class, $this->notifier);
});

/** A weekday at least `$days` days from today (the default working days are Monday to Friday). */
function hwDue(int $days = 2): string
{
    $date = (new DateTimeImmutable(CalendarDate::today()))->modify("+{$days} days");

    while ((int) $date->format('N') >= 6) {
        $date = $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

function hwCreate($actor, array $overrides = [])
{
    $test = test();

    return $test->actingAs($actor)->post(route('homework.store'), [
        'section' => $test->main1A->ulid,
        'subject' => $test->english->ulid,
        'title' => 'Draw and label a plant cell',
        'instructions' => 'A4 sheet, coloured pencils.',
        'due_on' => hwDue(),
        'submission_type' => 'notebook',
        ...$overrides,
    ]);
}

function hwMake($actor, array $overrides = []): HomeworkAssignment
{
    hwCreate($actor, $overrides)->assertSessionHasNoErrors();

    return HomeworkAssignment::query()->latest('id')->firstOrFail();
}

function hwAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('lets the class teacher set homework with an attachment and tells the class', function () {
    $response = hwCreate($this->teacher, ['files' => [UploadedFile::fake()->create('worksheet.pdf', 120, 'application/pdf')]]);

    $assignment = HomeworkAssignment::query()->firstOrFail();
    $response->assertRedirect(route('homework.index', ['assignment' => $assignment->ulid]))->assertSessionHasNoErrors();

    $file = HomeworkFile::query()->firstOrFail();

    expect($assignment->title)->toBe('Draw and label a plant cell')
        ->and($assignment->section_id)->toBe($this->main1A->id)
        ->and($assignment->subject_id)->toBe($this->english->id)
        ->and($assignment->assigned_on)->toBe(CalendarDate::today())
        ->and($assignment->submission_type->value)->toBe('notebook')
        ->and($file->file_name)->toBe('worksheet.pdf')
        ->and($file->file_path)->toStartWith("tenants/{$this->school->ulid}/homework/{$assignment->ulid}/")
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($this->notifier->events)->toBe(['assigned:2'])
        ->and(hwAudit('homework.created')->count())->toBe(1);

    Storage::disk('tenant')->assertExists($file->file_path);
});

it('shows the teacher the list and the selected assignment, ulids only', function () {
    $older = hwMake($this->teacher, ['title' => 'Older work']);
    $newer = hwMake($this->teacher, ['title' => 'Newer work', 'subject' => $this->maths->ulid]);

    $this->actingAs($this->teacher)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('homework/index')
            ->where('screen.can_assign', true)
            ->has('screen.assignments', 2)
            ->where('screen.assignments.0.title', 'Newer work')
            ->where('screen.assignments.0.subject', 'Mathematics')
            ->where('screen.assignments.0.section_label', 'Class 1-A')
            ->where('screen.assignments.0.submitted', 0)
            ->where('screen.assignments.0.total', 2)
            ->where('screen.assignments.0.ulid', $newer->ulid)
            ->where('screen.selected_ulid', $newer->ulid)
            ->where('screen.selected.assigned_by', 'Neha Verma')
            ->where('screen.selected.can_manage', true)
            ->has('screen.selected.rows', 2)
            ->where('screen.selected.rows.0.name', 'Riya Sharma')
            ->where('screen.selected.rows.0.status', 'not_yet')
            ->missing('screen.assignments.0.id')
            ->missing('screen.selected.rows.0.id'));

    $this->actingAs($this->teacher)->get(route('homework.index', ['assignment' => $older->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.selected.title', 'Older work'));
});

it('offers a teacher only the classes and subjects they teach', function () {
    $this->actingAs($this->kabir)->get(route('homework.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.form.sections.0.label', 'Class 1-A')
            ->has('screen.form.sections.0.subjects', 1)
            ->where('screen.form.sections.0.subjects.0.name', 'Mathematics'));

    hwCreate($this->kabir, ['subject' => $this->english->ulid])->assertNotFound();
    hwCreate($this->kabir, ['section' => $this->main2A->ulid, 'subject' => $this->maths->ulid])->assertNotFound();
    hwCreate($this->kabir, ['subject' => $this->maths->ulid])->assertSessionHasNoErrors();

    // The class teacher may set any subject of their class.
    hwCreate($this->teacher, ['subject' => $this->english->ulid])->assertSessionHasNoErrors();

    expect(HomeworkAssignment::query()->count())->toBe(2);
});

it('checks the title, the due date and the files', function () {
    hwCreate($this->teacher, ['title' => 'Hi'])->assertSessionHasErrors(['homework' => 'Give it a short title (3 to 80 characters).']);
    hwCreate($this->teacher, ['due_on' => CalendarDate::today()])->assertSessionHasErrors(['homework' => 'Pick a due date after today.']);
    hwCreate($this->teacher, ['due_on' => '2026-13-45'])->assertSessionHasErrors('due_on');

    $sunday = (new DateTimeImmutable(CalendarDate::today()))->modify('next sunday')->format('Y-m-d');
    hwCreate($this->teacher, ['due_on' => $sunday])->assertSessionHasErrors(['homework' => 'Sunday is a weekly day off. Pick a school day.']);

    DB::connection('tenant')->table('school_holidays')->insert(['ulid' => strtolower((string) Str::ulid()), 'holiday_on' => hwDue(3), 'name' => 'Diwali', 'created_at' => now(), 'updated_at' => now()]);
    hwCreate($this->teacher, ['due_on' => hwDue(3)])->assertSessionHasErrors(['homework' => 'That day is a holiday (Diwali). Pick a school day.']);

    hwCreate($this->teacher, ['files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
        ->assertSessionHasErrors(['homework' => 'virus.exe cannot be attached. Use a photo, PDF, Word, PowerPoint, Excel or text file of at most 10 MB.']);
    hwCreate($this->teacher, ['files' => array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6))])
        ->assertSessionHasErrors('files');
    hwCreate($this->teacher, ['files' => [UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf')]])
        ->assertSessionHasErrors('files.0');

    expect(HomeworkAssignment::query()->count())->toBe(0)
        ->and(Storage::disk('tenant')->allFiles())->toBe([]);
});

it('lets the teacher who set it edit it at any point and tells families', function () {
    $assignment = hwMake($this->teacher, ['files' => [UploadedFile::fake()->create('old.pdf', 20, 'application/pdf')]]);
    $old = HomeworkFile::query()->firstOrFail();
    $this->notifier->events = [];

    $this->actingAs($this->teacher)->put(route('homework.update', $assignment->ulid), [
        'title' => 'Plant cell, coloured',
        'instructions' => '',
        'due_on' => hwDue(4),
        'submission_type' => 'upload',
        'files' => [UploadedFile::fake()->create('new.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        'remove_files' => [$old->ulid],
    ])->assertSessionHasNoErrors();

    $assignment->refresh();

    expect($assignment->title)->toBe('Plant cell, coloured')
        ->and($assignment->instructions)->toBeNull()
        ->and($assignment->due_on)->toBe(hwDue(4))
        ->and($assignment->submission_type->value)->toBe('upload')
        ->and(HomeworkFile::query()->pluck('file_name')->all())->toBe(['new.docx'])
        ->and($this->notifier->events)->toBe(['changed:2'])
        ->and(hwAudit('homework.updated')->count())->toBe(1);

    Storage::disk('tenant')->assertMissing($old->file_path);
    Storage::disk('tenant')->assertExists(HomeworkFile::query()->firstOrFail()->file_path);

    // A closed assignment can still be corrected: the due date is only checked when it changes.
    $assignment->update(['assigned_on' => '2026-04-01', 'due_on' => '2026-04-10']);
    $this->actingAs($this->teacher)->put(route('homework.update', $assignment->ulid), [
        'title' => 'Plant cell, final', 'due_on' => '2026-04-10', 'submission_type' => 'upload',
    ])->assertSessionHasNoErrors();

    expect($assignment->fresh()->title)->toBe('Plant cell, final');

    // Nothing changed: nothing audited, nobody told.
    $this->notifier->events = [];
    $this->actingAs($this->teacher)->put(route('homework.update', $assignment->ulid), [
        'title' => 'Plant cell, final', 'due_on' => '2026-04-10', 'submission_type' => 'upload',
    ])->assertSessionHasNoErrors();

    expect(hwAudit('homework.updated')->count())->toBe(2)
        ->and($this->notifier->events)->toBe([]);
});

it('does not let the way of handing in change once work was handed in', function () {
    $assignment = hwMake($this->teacher);
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true, 'students' => [$this->riya->ulid]]);

    $this->actingAs($this->teacher)->put(route('homework.update', $assignment->ulid), [
        'title' => $assignment->title, 'due_on' => $assignment->due_on, 'submission_type' => 'upload',
    ])->assertSessionHasErrors(['homework' => 'The way of handing in cannot change once students have handed in or been checked.']);
});

it('lets only the teacher who set it change or delete it', function () {
    $assignment = hwMake($this->kabir, ['subject' => $this->maths->ulid]);
    $changes = ['title' => 'Hacked', 'due_on' => hwDue(), 'submission_type' => 'notebook'];

    // The class teacher can see it (read only) but not change it.
    $this->actingAs($this->teacher)->get(route('homework.index', ['assignment' => $assignment->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('screen.selected.can_manage', false));
    $this->actingAs($this->teacher)->put(route('homework.update', $assignment->ulid), $changes)->assertForbidden();
    $this->actingAs($this->teacher)->delete(route('homework.destroy', $assignment->ulid))->assertForbidden();
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true])->assertForbidden();
    $this->actingAs($this->teacher)->put(route('homework.feedback', $assignment->ulid), ['feedback' => [$this->riya->ulid => 'x']])->assertForbidden();

    // A teacher of another class cannot even see it.
    $outsider = $this->staffWith(['homework.view', 'homework.assign'], Scope::sections([$this->main2A->ulid]));
    $this->actingAs($outsider)->put(route('homework.update', $assignment->ulid), $changes)->assertNotFound();

    expect($assignment->fresh()->title)->not->toBe('Hacked');
});

it('deletes homework softly, even after work was handed in, and tells families', function () {
    $assignment = hwMake($this->teacher);
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true]);
    $this->notifier->events = [];

    $this->actingAs($this->teacher)->delete(route('homework.destroy', $assignment->ulid))->assertRedirect(route('homework.index'));

    expect(HomeworkAssignment::query()->count())->toBe(0)
        ->and(HomeworkAssignment::withTrashed()->count())->toBe(1)
        ->and(HomeworkSubmission::query()->count())->toBe(2)
        ->and($this->notifier->events)->toBe(['removed:2'])
        ->and(hwAudit('homework.deleted')->count())->toBe(1);

    $this->actingAs($this->teacher)->get(route('homework.index'))->assertInertia(fn (Assert $page) => $page->has('screen.assignments', 0)->where('screen.selected', null));
    $this->actingAs($this->teacher)->delete(route('homework.destroy', $assignment->ulid))->assertNotFound();
});

it('marks notebook work checked for chosen students or everyone, and takes it back', function () {
    $assignment = hwMake($this->teacher);
    $detail = fn () => test()->actingAs(test()->teacher)->get(route('homework.index'));

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true, 'students' => [$this->riya->ulid]])->assertSessionHasNoErrors();

    $detail()->assertInertia(fn (Assert $page) => $page
        ->where('screen.selected.submitted', 1)
        ->where('screen.selected.rows.0.status', 'submitted')
        ->where('screen.selected.rows.0.is_checked', true)
        ->where('screen.selected.rows.1.status', 'not_yet'));

    // Everyone: only the one who is not checked yet changes.
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true]);
    expect(HomeworkSubmission::query()->count())->toBe(2);

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => false, 'students' => [$this->ira->ulid]]);
    expect(HomeworkSubmission::query()->count())->toBe(1)
        ->and(hwAudit('homework.checked')->count())->toBe(2)
        ->and(hwAudit('homework.unchecked')->count())->toBe(1);

    // A student of another class is not in the roster.
    $stranger = $this->makeStudent($this->main2A, 'Om', 'Joshi');
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true, 'students' => [$stranger->ulid]])->assertNotFound();
});

it('lets checked-in-class work count as submitted and never as late', function () {
    $assignment = hwMake($this->teacher);
    $assignment->update(['assigned_on' => '2026-04-01', 'due_on' => '2026-04-10']);

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true, 'students' => [$this->riya->ulid]]);

    $this->actingAs($this->teacher)->get(route('homework.index'))->assertInertia(fn (Assert $page) => $page
        ->where('screen.selected.days_left', fn ($days) => $days < 0)
        ->where('screen.selected.rows.0.status', 'submitted')
        ->where('screen.selected.rows.1.status', 'missing'));
});

it('writes, changes and clears feedback only for students who handed in', function () {
    $assignment = hwMake($this->teacher);
    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true, 'students' => [$this->riya->ulid]]);
    $this->notifier->events = [];
    $save = fn (array $feedback) => test()->actingAs(test()->teacher)->put(route('homework.feedback', $assignment->ulid), ['feedback' => $feedback]);

    $save([$this->riya->ulid => '  Good vocabulary.  ', $this->ira->ulid => null])->assertSessionHasNoErrors();

    expect(HomeworkSubmission::query()->firstOrFail()->feedback)->toBe('Good vocabulary.')
        ->and($this->notifier->events)->toBe(['feedback:1'])
        ->and(hwAudit('homework.feedback_saved')->count())->toBe(1);

    // Unchanged: nothing audited or sent.
    $this->notifier->events = [];
    $save([$this->riya->ulid => 'Good vocabulary.'])->assertSessionHasNoErrors();
    expect(hwAudit('homework.feedback_saved')->count())->toBe(1)->and($this->notifier->events)->toBe([]);

    $save([$this->ira->ulid => 'Not handed in'])->assertNotFound();
    $save([$this->riya->ulid => str_repeat('x', 161)])->assertSessionHasErrors('feedback.'.$this->riya->ulid);

    $save([$this->riya->ulid => ''])->assertSessionHasNoErrors();
    expect(HomeworkSubmission::query()->firstOrFail()->feedback)->toBeNull();
});

it('lets uploaded work be checked only after the parent handed it in', function () {
    $assignment = hwMake($this->teacher, ['submission_type' => 'upload']);

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true])->assertSessionHasNoErrors();
    expect(HomeworkSubmission::query()->count())->toBe(0);

    HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $assignment->id, 'student_id' => $this->riya->id, 'submitted_on' => CalendarDate::today(), 'submitted_at' => now()]);

    $this->actingAs($this->teacher)->get(route('homework.index'))->assertInertia(fn (Assert $page) => $page
        ->where('screen.assignments.0.to_check', 1)
        ->where('screen.selected.to_check', 1));

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => true])->assertSessionHasNoErrors();
    expect(HomeworkSubmission::query()->firstOrFail()->checked_on)->toBe(CalendarDate::today());

    $this->actingAs($this->teacher)->post(route('homework.checked', $assignment->ulid), ['checked' => false]);
    expect(HomeworkSubmission::query()->firstOrFail()->checked_on)->toBeNull();
});

it('gives people who may only view a read-only screen', function () {
    hwMake($this->teacher);
    $coordinator = $this->staffWith(['homework.view'], Scope::wing([$this->main->ulid], 1, 1));

    $this->actingAs($coordinator)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.can_assign', false)
            ->where('screen.form', null)
            ->has('screen.assignments', 1)
            ->where('screen.selected.can_manage', false));

    hwCreate($coordinator)->assertForbidden();

    // A wing that does not include the class sees nothing.
    $other = $this->staffWith(['homework.view'], Scope::wing([$this->main->ulid], 2, 2));
    $this->actingAs($other)->get(route('homework.index'))->assertInertia(fn (Assert $page) => $page->has('screen.assignments', 0));
});

it('applies the authorization matrix to the screen and to setting homework', function () {
    $this->assertAuthorizationMatrix(
        module: 'homework',
        permission: 'homework.view',
        inScope: Scope::school(),
        outOfScope: null,
        makeActor: fn (array $permissions, Scope $scope) => $this->staffWith($permissions, $scope),
        send: fn ($actor) => $this->actingAs($actor)->get(route('homework.index')),
    );

    // Setting homework: teachers hold `homework.assign` through their automatic role, so the matrix
    // helper (which builds actors from a custom role) does not fit; the same cases are checked directly.
    hwCreate($this->staffWith(['students.view']))->assertForbidden();
    hwCreate($this->staffWith(['homework.assign']))->assertNotFound();
    hwCreate($this->teacher)->assertSessionHasNoErrors();

    $this->disableModule('homework');
    hwCreate($this->teacher)->assertNotFound();
    $this->enableModules(['homework']);

    $this->setSchoolStatus(TenantStatus::Suspended);
    hwCreate($this->teacher)->assertForbidden();
    $this->setSchoolStatus(TenantStatus::Active);

    expect(HomeworkAssignment::query()->count())->toBe(1);
});

it('opens attachments and uploaded work from signed links for people who may see them', function () {
    $assignment = hwMake($this->teacher, ['submission_type' => 'upload', 'files' => [UploadedFile::fake()->create('sheet.pdf', 20, 'application/pdf')]]);
    $submission = HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $assignment->id, 'student_id' => $this->riya->id, 'submitted_on' => CalendarDate::today(), 'submitted_at' => now()]);
    $upload = $submission->files()->create(['ulid' => strtolower((string) Str::ulid()), 'file_path' => "tenants/{$this->school->ulid}/homework/{$assignment->ulid}/submissions/{$this->riya->ulid}/work.jpg", 'file_name' => 'work.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'position' => 0]);
    Storage::disk('tenant')->put($upload->file_path, 'jpeg-bytes');

    $page = $this->actingAs($this->teacher)->get(route('homework.index'));
    $props = $page->inertiaProps('screen.selected');
    $attachment = $props['attachments'][0]['url'];
    $work = $props['rows'][0]['files'][0]['url'];

    $this->actingAs($this->teacher)->get($attachment)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(hwAudit('homework.submission_file_opened')->count())->toBe(0);
    $this->actingAs($this->teacher)->get($work)->assertOk();
    expect(hwAudit('homework.submission_file_opened')->count())->toBe(1);

    // Without the signature, or for a teacher of another class, it is refused or hidden.
    $this->actingAs($this->teacher)->get(route('homework.files.show', HomeworkFile::query()->firstOrFail()->ulid))->assertForbidden();
    $outsider = $this->staffWith(['homework.view'], Scope::sections([$this->main2A->ulid]));
    $this->actingAs($outsider)->get($attachment)->assertNotFound();
    $this->actingAs($outsider)->get(URL::temporarySignedRoute('homework.submission-files.show', now()->addMinutes(5), ['file' => $upload->ulid]))->assertNotFound();
});

it('keeps one school\'s homework out of another school', function () {
    hwMake($this->teacher);

    expect($this->otherSchool->execute(fn () => HomeworkAssignment::withTrashed()->count()))->toBe(0)
        ->and(HomeworkAssignment::query()->count())->toBe(1);
});
