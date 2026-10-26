<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Homework\Actions\CreateHomework;
use App\Domain\Homework\Actions\DeleteHomework;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\Homework\Models\HomeworkSubmissionFile;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetClassTeacher;
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
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, ManagesSchoolPlan::class, BuildsStudents::class);

beforeEach(function () {
    Storage::fake('tenant');
    $this->enableModules(['homework']);
    $this->setUpStudentSchool();

    $this->phone = $this->nextPhone();
    $priya = fn () => [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)];
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', $priya());
    $this->arjun = $this->makeStudent($this->main2A, 'Arjun', 'Sharma', $priya());
    $this->kabir = $this->makeStudent($this->main1A, 'Kabir', 'Mehta');

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $two = SchoolClass::query()->where('level', 2)->firstOrFail();
    $add = app(AddSubject::class);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one, $two]);
    $this->science = $add->handle($this->owner, 'Science', 'sci', SubjectType::Core, [$one, $two]);

    $this->teacher = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma')->ulid);
    $this->teacher2 = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main2A, app(CreateStaffRecord::class)->handle($this->teacher2->ulid, 'Pooja Bhatia')->ulid);

    $this->notifier = new class implements HomeworkNotifier
    {
        /** @var list<string> */
        public array $events = [];

        public function notify(string $event, string $assignmentUlid, string $sectionUlid, array $studentUlids): void
        {
            $this->events[] = $event;
        }
    };
    $this->app->instance(HomeworkNotifier::class, $this->notifier);

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function phSignIn(): void
{
    test()->post(route('parent.send-code'), ['phone' => test()->phone]);
    test()->post(route('parent.verify.store'), ['code' => test()->lastCode])->assertRedirect(route('parent.home'));
}

function phDue(int $days = 2): string
{
    $date = (new DateTimeImmutable(CalendarDate::today()))->modify("+{$days} days");

    while ((int) $date->format('N') >= 6) {
        $date = $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

function phMake(string $title = 'Fractions — Exercise 7.3', string $type = 'notebook', bool $classTwo = false, array $files = []): HomeworkAssignment
{
    $test = test();

    return app(CreateHomework::class)->handle(
        $classTwo ? $test->teacher2 : $test->teacher,
        $classTwo ? $test->main2A->ulid : $test->main1A->ulid,
        $test->maths->ulid,
        $title,
        'Solve in the notebook.',
        phDue(),
        SubmissionType::from($type),
        $files,
    );
}

it('shows a child the homework of their class with its state, attachments and feedback', function () {
    $notebook = phMake('Fractions', 'notebook', files: [UploadedFile::fake()->create('sheet.pdf', 30, 'application/pdf')]);
    $upload = phMake('Plant cell', 'upload');
    $other = phMake('Class 2 only', classTwo: true);
    $checked = phMake('Spelling', 'notebook');
    HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $checked->id, 'student_id' => $this->riya->id, 'checked_on' => CalendarDate::today(), 'feedback' => 'Neat work.']);
    HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $checked->id, 'student_id' => $this->kabir->id, 'checked_on' => CalendarDate::today(), 'feedback' => 'Only for Kabir.']);

    phSignIn();

    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/homework')
            ->where('child_first_name', 'Riya')
            ->where('section_label', 'Class 1-A')
            ->where('is_current_session', true)
            ->has('items', 3)
            ->where('items', function ($items) use ($checked) {
                $byTitle = collect($items)->keyBy('title');

                return ! $byTitle->has('Class 2 only')
                    && $byTitle['Spelling']['state'] === 'done'
                    && $byTitle['Spelling']['feedback'] === 'Neat work.'
                    && $byTitle['Spelling']['can_upload'] === false
                    && $byTitle['Spelling']['ulid'] === $checked->ulid
                    && $byTitle['Fractions']['state'] === 'due'
                    && $byTitle['Fractions']['can_upload'] === false
                    && $byTitle['Fractions']['teacher_name'] === 'Neha Verma'
                    && $byTitle['Fractions']['subject'] === 'Mathematics'
                    && $byTitle['Fractions']['attachments'][0]['name'] === 'sheet.pdf'
                    && $byTitle['Plant cell']['can_upload'] === true
                    && ! array_key_exists('id', $byTitle['Plant cell']);
            }));
});

it('counts a missed upload and shows late hand-ins as late', function () {
    $missed = phMake('Old work', 'upload');
    $late = phMake('Late work', 'upload');
    DB::connection('tenant')->table('homework_assignments')->whereIn('id', [$missed->id, $late->id])->update(['assigned_on' => '2026-04-01', 'due_on' => '2026-04-10']);
    HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $late->id, 'student_id' => $this->riya->id, 'submitted_on' => '2026-04-12', 'submitted_at' => now()]);

    phSignIn();

    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('items', function ($items) {
            $byTitle = collect($items)->keyBy('title');

            return $byTitle['Old work']['state'] === 'missed'
                && $byTitle['Old work']['can_upload'] === true
                && $byTitle['Late work']['state'] === 'done'
                && $byTitle['Late work']['is_late'] === true;
        }));
});

it('lets a parent upload their child\'s work, and late work too, and tells the teacher', function () {
    $assignment = phMake('Plant cell', 'upload');
    $late = phMake('Diary entry', 'upload');
    DB::connection('tenant')->table('homework_assignments')->where('id', $late->id)->update(['assigned_on' => '2026-04-01', 'due_on' => '2026-04-10']);
    $this->notifier->events = [];

    phSignIn();

    $this->post(route('parent.homework.submit', $assignment->ulid), [
        'child' => $this->riya->ulid,
        'files' => [UploadedFile::fake()->image('page1.jpg'), UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
    ])->assertSessionHasNoErrors();

    $submission = HomeworkSubmission::query()->where('homework_assignment_id', $assignment->id)->firstOrFail();

    expect($submission->student_id)->toBe($this->riya->id)
        ->and($submission->submitted_on)->toBe(CalendarDate::today())
        ->and($submission->checked_on)->toBeNull()
        ->and($submission->files()->pluck('file_name')->all())->toBe(['page1.jpg', 'notes.docx'])
        ->and($this->notifier->events)->toBe(['submitted'])
        ->and(AuditEvent::query()->where('action', 'homework.submitted')->count())->toBe(1);

    foreach ($submission->files as $file) {
        Storage::disk('tenant')->assertExists($file->file_path);
        expect($file->file_path)->toContain("/{$assignment->ulid}/submissions/{$this->riya->ulid}/");
    }

    $this->post(route('parent.homework.submit', $late->ulid), ['child' => $this->riya->ulid, 'files' => [UploadedFile::fake()->image('late.png')]])->assertSessionHasNoErrors();

    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('items', function ($items) {
            $byTitle = collect($items)->keyBy('title');

            return $byTitle['Plant cell']['state'] === 'done'
                && $byTitle['Plant cell']['is_late'] === false
                && count($byTitle['Plant cell']['my_files']) === 2
                && $byTitle['Diary entry']['is_late'] === true;
        }));
});

it('refuses a second hand-in, bad files, notebook homework, an empty upload and a class that is not the child\'s', function () {
    $upload = phMake('Plant cell', 'upload');
    $notebook = phMake('Fractions', 'notebook');
    $classTwo = phMake('Class 2 work', 'upload', classTwo: true);

    phSignIn();
    $send = fn (string $ulid, array $files, ?string $child = null) => test()->post(route('parent.homework.submit', $ulid), ['child' => $child ?? test()->riya->ulid, 'files' => $files]);

    $send($upload->ulid, [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])
        ->assertSessionHasErrors(['homework' => 'run.exe cannot be attached. Use a photo, PDF, Word, PowerPoint, Excel or text file of at most 10 MB.']);
    $send($upload->ulid, [])->assertSessionHasErrors('files');
    $send($upload->ulid, array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6)))->assertSessionHasErrors('files');
    $send($notebook->ulid, [UploadedFile::fake()->image('a.jpg')])->assertSessionHasErrors(['homework' => 'This homework is done in the notebook, so nothing is uploaded.']);
    $send($classTwo->ulid, [UploadedFile::fake()->image('a.jpg')])->assertNotFound();
    $send($upload->ulid, [UploadedFile::fake()->image('a.jpg')], 'not-a-child-of-priya')->assertNotFound();

    $send($upload->ulid, [UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
    $send($upload->ulid, [UploadedFile::fake()->image('b.jpg')])->assertSessionHasErrors(['homework' => 'This homework is already handed in.']);

    // Arjun is in Class 2, so he can hand in Class 2's work but not Riya's.
    $send($upload->ulid, [UploadedFile::fake()->image('c.jpg')], $this->arjun->ulid)->assertNotFound();
    $send($classTwo->ulid, [UploadedFile::fake()->image('c.jpg')], $this->arjun->ulid)->assertSessionHasNoErrors();

    expect(HomeworkSubmission::query()->count())->toBe(2);
});

it('shows a past session\'s homework from the session dropdown and refuses uploads there', function () {
    $past = app(CreateAcademicYear::class)->handle($this->owner, '2025-26', '2025-04-01', '2026-03-31');
    $oldSection = app(CreateSection::class)->handle($this->owner, $past, $this->main, SchoolClass::query()->where('level', 1)->firstOrFail(), 'A', 40);
    DB::connection('tenant')->table('enrollments')->insert([
        'student_id' => $this->riya->id, 'academic_year_id' => $past->id, 'section_id' => $oldSection->id,
        'roll_number' => 3, 'started_on' => '2025-04-01', 'ended_on' => '2026-03-31', 'result' => 'promoted', 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $old = HomeworkAssignment::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'academic_year_id' => $past->id, 'section_id' => $oldSection->id, 'subject_id' => $this->maths->id,
        'title' => 'Last year\'s sums', 'assigned_on' => '2025-09-01', 'due_on' => '2025-09-05', 'submission_type' => 'upload',
        'created_by_staff_id' => DB::connection('tenant')->table('staff')->value('id'),
    ]);
    phMake('This year', 'upload');

    phSignIn();
    $this->put(route('parent.session.select'), ['session' => $past->ulid]);

    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('session_name', '2025-26')
        ->where('is_current_session', false)
        ->has('items', 1)
        ->where('items.0.title', 'Last year\'s sums')
        ->where('items.0.state', 'missed')
        ->where('items.0.can_upload', false));

    $this->post(route('parent.homework.submit', $old->ulid), ['child' => $this->riya->ulid, 'files' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertSessionHasErrors(['homework' => 'Homework from an earlier session can only be viewed.']);

    // Arjun was not enrolled in 2025-26.
    $this->get(route('parent.homework', ['child' => $this->arjun->ulid]))->assertNotFound();
});

it('hides deleted homework from parents', function () {
    $assignment = phMake('Fractions');

    phSignIn();
    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page->has('items', 1));

    app(DeleteHomework::class)->handle($this->teacher, $assignment->ulid);

    $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->assertInertia(fn (Assert $page) => $page->has('items', 0));
});

it('opens a child\'s own files and attachments from signed links only', function () {
    $assignment = phMake('Plant cell', 'upload', files: [UploadedFile::fake()->create('sheet.pdf', 30, 'application/pdf')]);

    phSignIn();
    $this->post(route('parent.homework.submit', $assignment->ulid), ['child' => $this->riya->ulid, 'files' => [UploadedFile::fake()->image('page1.jpg')]]);

    $items = $this->get(route('parent.homework', ['child' => $this->riya->ulid]))->inertiaProps('items');
    $attachmentUrl = $items[0]['attachments'][0]['url'];
    $workUrl = $items[0]['my_files'][0]['url'];

    $this->get($attachmentUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get($workUrl)->assertOk();
    expect(AuditEvent::query()->where('action', 'homework.submission_file_opened')->count())->toBe(1);

    $this->get(route('parent.homework.files.show', ['file' => $items[0]['attachments'][0]['ulid'], 'child' => $this->riya->ulid]))->assertForbidden();

    // Another family's child's upload, even with a valid signature, is not found.
    $kabirWork = HomeworkSubmission::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_assignment_id' => $assignment->id, 'student_id' => $this->kabir->id, 'submitted_on' => CalendarDate::today(), 'submitted_at' => now()]);
    $kabirFile = HomeworkSubmissionFile::query()->create(['ulid' => strtolower((string) Str::ulid()), 'homework_submission_id' => $kabirWork->id, 'file_path' => 'tenants/x/homework/kabir.jpg', 'file_name' => 'kabir.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'position' => 0]);

    $this->get(URL::temporarySignedRoute('parent.homework.submission-files.show', now()->addMinutes(5), ['file' => $kabirFile->ulid, 'child' => $this->riya->ulid]))->assertNotFound();
});

it('is blocked when the module is not in the plan or the school is suspended, and keeps schools apart', function () {
    phSignIn();
    $this->get(route('parent.homework'))->assertOk();

    $this->disableModule('homework');
    $this->get(route('parent.homework'))->assertNotFound();
    $this->enableModules(['homework']);

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->get(route('parent.homework'))->assertForbidden();
    $this->setSchoolStatus(TenantStatus::Active);

    phMake('Fractions');
    expect($this->otherSchool->execute(fn () => HomeworkAssignment::withTrashed()->count()))->toBe(0);
});

it('sends guests to the parent sign-in page', function () {
    $this->get(route('parent.homework'))->assertRedirect(route('parent.sign-in'));
});

it('shows the Homework menu only when the plan has the module', function () {
    phSignIn();
    $this->get(route('parent.homework'))->assertInertia(fn (Assert $page) => $page->where('shell.homework_enabled', true));

    $this->disableModule('homework');
    $this->get(route('parent.home'))->assertInertia(fn (Assert $page) => $page->where('shell.homework_enabled', false));
});
