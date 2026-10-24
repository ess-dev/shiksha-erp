<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Exams\Actions\SaveMarks;
use App\Domain\Exams\Actions\UpdateReportCardApproval;
use App\Domain\Exams\Contracts\ReportCardNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\Queries\ReportCardsScreenQuery;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\School\Actions\AddSubject;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\NewGuardian;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    $this->enableModules(['exams']);
    $this->setUpStudentSchool();

    $one = SchoolClass::query()->where('level', 1)->firstOrFail();
    $add = app(AddSubject::class);
    $this->english = $add->handle($this->owner, 'English', 'eng', SubjectType::Core, [$one]);
    $this->maths = $add->handle($this->owner, 'Mathematics', 'mat', SubjectType::Core, [$one]);

    $this->teacher = $this->staff();
    app(SetClassTeacher::class)->handle($this->owner, $this->main1A, app(CreateStaffRecord::class)->handle($this->teacher->ulid, 'Neha Verma')->ulid);

    $this->phone = $this->nextPhone();
    $this->riya = $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $this->phone, null, null, true)]);
    $this->ira = $this->makeStudent($this->main1A, 'Ira', 'Nair');

    $this->exam = Exam::query()->create([
        'ulid' => strtolower((string) Str::ulid()),
        'academic_year_id' => AcademicYear::query()->where('is_current', true)->value('id'),
        'name' => 'Half-Yearly Examination',
        'type' => 'half_yearly',
        'max_marks' => 80,
        'starts_on' => CalendarDate::today(),
        'ends_on' => CalendarDate::today(),
        'status' => ExamStatus::Marking,
        'applies_to_all_classes' => true,
        'applies_to_all_campuses' => true,
    ]);

    $this->notifier = new class implements ReportCardNotifier
    {
        /** @var list<string> */
        public array $events = [];

        public function notify(string $event, string $examUlid, string $sectionUlid, array $studentUlids): void
        {
            $this->events[] = $event;
        }
    };
    $this->app->instance(ReportCardNotifier::class, $this->notifier);

    $this->app->bind(OtpSender::class, fn () => new class implements OtpSender
    {
        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            test()->lastCode = $code;
        }
    });
});

function rcMarks($subject, string $riya, string $ira, bool $publish = true): void
{
    $test = test();
    app(SaveMarks::class)->handle($test->owner, $test->exam, $test->main1A->ulid, $subject->ulid, [
        $test->riya->ulid => $riya,
        $test->ira->ulid => $ira,
    ], $publish);
}

function rcGenerate($actor, array $remarks)
{
    $test = test();

    return $test->actingAs($actor)->post(route('report-cards.generate', $test->exam), ['section' => $test->main1A->ulid, 'remarks' => $remarks]);
}

function rcAudit(string $action)
{
    return AuditEvent::query()->where('action', $action);
}

it('lists the class with marks readiness, counts and who approves, ulids only', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '', publish: false);
    $this->staffWith(['reportcards.approve'], Scope::wing([$this->main->ulid], 1, 1));

    $this->actingAs($this->teacher)
        ->get(route('report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/report-cards')
            ->where('screen.sections.0.label', 'Class 1-A')
            ->where('screen.exam_name', 'Half-Yearly Examination')
            ->where('screen.approval_required', true)
            ->where('screen.approvers', fn ($names) => is_string($names) && str_starts_with($names, 'Staff '))
            ->where('screen.not_started_count', 2)
            ->where('screen.rows.0.name', 'Riya Sharma')
            ->where('screen.rows.0.marks', 'unpublished')
            ->where('screen.rows.1.marks', 'missing')
            ->where('screen.rows.0.missing_subjects', [])
            ->where('screen.rows.1.missing_subjects', ['Mathematics'])
            ->where('screen.rows.0.card', null)
            ->missing('screen.rows.0.id'));
});

it('previews a report card from published marks with the suggested remark', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');

    $this->actingAs($this->teacher)
        ->get(route('report-cards.index', ['preview' => $this->riya->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('preview')
            ->reloadOnly('preview', fn (Assert $reload) => $reload
                ->where('preview.student_name', 'Riya Sharma')
                ->where('preview.class_teacher_name', 'Neha Verma')
                ->where('preview.result.total_marks', '156')
                ->where('preview.result.percent_tenths', 975)
                ->where('preview.remark', 'Outstanding work across subjects. Keep it up.')));
});

it('generates report cards for approval with a snapshot and the teacher\'s remark', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '', publish: false);

    rcGenerate($this->teacher, [$this->riya->ulid => null])
        ->assertSessionHasErrors(['report_cards' => 'Riya Sharma’s marks are not all entered and published yet.']);

    rcMarks($this->maths, '80', '51');

    rcGenerate($this->teacher, [$this->riya->ulid => '  Excellent term. ', $this->ira->ulid => null])->assertSessionHasNoErrors();

    $riya = ReportCard::query()->where('student_id', $this->riya->id)->firstOrFail();
    $ira = ReportCard::query()->where('student_id', $this->ira->id)->firstOrFail();

    expect($riya->status->value)->toBe('submitted')
        ->and($riya->kind->value)->toBe('generated')
        ->and($riya->remark)->toBe('Excellent term.')
        ->and($riya->snapshot['remark'])->toBe('Excellent term.')
        ->and($riya->snapshot['result']['total_marks'])->toBe('156')
        ->and($ira->remark)->toBe('Good effort. Regular revision will help further.')
        ->and(rcAudit('report_cards.generated')->count())->toBe(1)
        ->and($this->notifier->events)->toBe(['submitted']);

    rcGenerate($this->teacher, [$this->riya->ulid => null])
        ->assertSessionHasErrors(['report_cards' => 'Riya Sharma already has a report card for this exam.']);
});

it('publishes straight to parents when the school switched approval off', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    app(UpdateReportCardApproval::class)->handle($this->owner, false);

    rcGenerate($this->teacher, [$this->riya->ulid => null])->assertSessionHasNoErrors();

    expect(ReportCard::query()->firstOrFail()->status->value)->toBe('published')
        ->and($this->notifier->events)->toBe(['published']);
});

it('lets the class teacher withdraw a waiting report card and prepare it again', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => null]);
    $card = ReportCard::query()->firstOrFail();

    $this->actingAs($this->teacher)->post(route('report-cards.withdraw', $card))->assertRedirect()->assertSessionHasNoErrors();

    expect($card->fresh()->status->value)->toBe('returned')
        ->and($card->fresh()->return_note)->toBe('Withdrawn by the class teacher');

    rcGenerate($this->teacher, [$this->riya->ulid => 'Second try'])->assertSessionHasNoErrors();

    expect(ReportCard::query()->firstOrFail()->remark)->toBe('Second try');
});

it('uploads a scanned report card and checks its type', function () {
    $url = route('report-cards.upload', $this->exam);

    $this->actingAs($this->teacher)
        ->post($url, ['section' => $this->main1A->ulid, 'student' => $this->ira->ulid, 'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
        ->assertSessionHasErrors(['file' => 'Upload a PDF, JPEG or PNG file of at most 5 MB.']);

    $this->actingAs($this->teacher)
        ->post($url, ['section' => $this->main1A->ulid, 'student' => $this->ira->ulid, 'file' => UploadedFile::fake()->create('ira-hy.pdf', 80, 'application/pdf')])
        ->assertSessionHasNoErrors();

    $card = ReportCard::query()->firstOrFail();

    expect($card->kind->value)->toBe('file')
        ->and($card->file_name)->toBe('ira-hy.pdf')
        ->and($card->file_path)->toStartWith("tenants/{$this->school->ulid}/report-cards/");
    Storage::disk('tenant')->assertExists($card->file_path);

    $this->actingAs($this->teacher)
        ->get(URL::temporarySignedRoute('report-cards.file', now()->addMinutes(5), ['reportCard' => $card->ulid]))
        ->assertOk();

    expect(rcAudit('report_cards.file_opened')->count())->toBe(1);
});

it('lets approvers in scope publish or send back with a note', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => null, $this->ira->ulid => null]);

    $approver = $this->staffWith(['reportcards.approve'], Scope::wing([$this->main->ulid], 1, 1));
    $outside = $this->staffWith(['reportcards.approve'], Scope::campuses([$this->north->ulid]));
    $url = route('report-cards.decide', $this->exam);

    $this->actingAs($this->teacher)->post($url, ['section' => $this->main1A->ulid, 'approve' => true])->assertForbidden();
    $this->actingAs($outside)->post($url, ['section' => $this->main1A->ulid, 'approve' => true])->assertNotFound();
    $this->actingAs($approver)->post($url, ['section' => $this->main1A->ulid, 'approve' => false, 'note' => ''])
        ->assertSessionHasErrors(['report_cards' => 'Say what needs changing (3 to 200 characters).']);

    $this->actingAs($approver)->post($url, ['section' => $this->main1A->ulid, 'approve' => false, 'note' => 'Fix the remark', 'students' => [$this->ira->ulid]])
        ->assertSessionHasNoErrors();
    $this->actingAs($approver)->post($url, ['section' => $this->main1A->ulid, 'approve' => true])->assertSessionHasNoErrors();

    expect(ReportCard::query()->where('student_id', $this->riya->id)->firstOrFail()->status->value)->toBe('published')
        ->and(ReportCard::query()->where('student_id', $this->ira->id)->value('return_note'))->toBe('Fix the remark')
        ->and(rcAudit('report_cards.approved')->count())->toBe(1)
        ->and(rcAudit('report_cards.returned')->count())->toBe(1);

    $this->actingAs($this->teacher)
        ->get(route('report-cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.published_count', 1)
            ->where('screen.sent_back_count', 1)
            ->where('screen.rows.0.card.status', 'published')
            ->where('screen.rows.1.card.return_note', 'Fix the remark'));
});

it('shows parents only published report cards from school', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => 'Well done']);

    test()->post(route('parent.send-code'), ['phone' => $this->phone]);
    test()->post(route('parent.verify.store'), ['code' => $this->lastCode]);

    $this->get(route('parent.results'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('school_report_cards', [])
            ->where('report_card_waiting', true));

    $card = ReportCard::query()->firstOrFail();
    $this->get(route('parent.report-cards.show', [$card->ulid]))->assertNotFound();

    app(UpdateReportCardApproval::class)->handle($this->owner, true);
    $card->update(['status' => 'published']);

    $this->get(route('parent.results'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('school_report_cards.0.exam_name', 'Half-Yearly Examination')
            ->where('school_report_cards.0.prepared_by', 'Neha Verma')
            ->where('report_card_waiting', false));

    $this->get(route('parent.report-cards.show', [$card->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/report-card')
            ->where('card.remark', 'Well done')
            ->where('card.student_name', 'Riya Sharma'));

    $other = ReportCard::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'exam_id' => $this->exam->id, 'student_id' => $this->ira->id,
        'section_id' => $this->main1A->id, 'kind' => 'generated', 'status' => 'published', 'snapshot' => $card->snapshot, 'prepared_at' => now(),
    ]);
    $this->get(route('parent.report-cards.show', [$other->ulid]))->assertNotFound();
});

it('keeps report cards to the class teacher, the plan and an active school', function () {
    $subjectTeacher = $this->staffWith(['marks.enter']);

    $this->actingAs($subjectTeacher)->get(route('report-cards.index'))->assertForbidden();

    $notClassTeacher = $this->staffWith(['reportcards.prepare']);
    $this->actingAs($notClassTeacher)->get(route('report-cards.index'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.sections', []));
    rcGenerate($notClassTeacher, [$this->riya->ulid => null])->assertNotFound();

    $this->disableModule('exams');
    $this->actingAs($this->teacher)->get(route('report-cards.index'))->assertNotFound();
    $this->enableModules(['exams']);

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($this->teacher)->get(route('report-cards.index'))->assertForbidden();
});

it('keeps one school\'s report cards out of another school', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => null]);

    expect($this->otherSchool->execute(fn () => ReportCard::query()->count()))->toBe(0)
        ->and(ReportCard::query()->count())->toBe(1);
});

it('switches report card approval for settings managers only', function () {
    $this->actingAs($this->teacher)->put(route('exams.grading.approval'), ['required' => false])->assertForbidden();
    $this->actingAs($this->owner)->put(route('exams.grading.approval'), ['required' => false])->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->get(route('exams.grading'))
        ->assertInertia(fn (Assert $page) => $page->where('screen.report_card_approval', false));

    expect(rcAudit('settings.report_card_approval_updated')->count())->toBe(1);
});

it('lists waiting report cards for approvers in scope on Approvals and Report cards', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => null, $this->ira->ulid => null]);

    $approver = $this->staffWith(['reportcards.approve'], Scope::wing([$this->main->ulid], 1, 1));
    $outside = $this->staffWith(['reportcards.approve'], Scope::campuses([$this->north->ulid]));
    $card = ReportCard::query()->where('student_id', $this->riya->id)->firstOrFail();

    $this->actingAs($approver)->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/approvals')
            ->where('report_cards.awaiting_count', 2)
            ->where('report_cards.groups.0.section_label', 'Class 1-A')
            ->where('report_cards.groups.0.exam_name', 'Half-Yearly Examination')
            ->where('report_cards.groups.0.student_count', 2)
            ->where('report_cards.groups.0.cards.0.student_name', 'Riya Sharma')
            ->where('report_cards.groups.0.cards.0.status', 'submitted')
            ->missing('report_cards.groups.0.cards.0.id')
            ->missing('view'));

    $this->actingAs($approver)->get(route('approvals.index', ['view' => $card->ulid]))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('view', fn (Assert $reload) => $reload
            ->where('view.status', 'submitted')
            ->where('view.exam_name', 'Half-Yearly Examination')));

    $this->actingAs($approver)->get(route('report-cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('screen.sections', [])
            ->where('approvals.awaiting_count', 2)
            ->where('approvals.groups.0.cards.1.student_name', 'Ira Nair'));

    $this->actingAs($outside)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('report_cards.groups', [])->where('report_cards.awaiting_count', 0));

    expect(fn () => app(ReportCardsScreenQuery::class)->view($outside, $card->ulid))->toThrow(ReportCardException::class);
});

it('shows approvers published and sent back cards on the status filter', function () {
    rcMarks($this->english, '76', '57');
    rcMarks($this->maths, '80', '51');
    rcGenerate($this->teacher, [$this->riya->ulid => null, $this->ira->ulid => null]);
    $approver = $this->staffWith(['reportcards.approve']);
    $url = route('report-cards.decide', $this->exam);

    $this->actingAs($approver)->post($url, ['section' => $this->main1A->ulid, 'approve' => true, 'students' => [$this->riya->ulid]]);
    $this->actingAs($approver)->post($url, ['section' => $this->main1A->ulid, 'approve' => false, 'note' => 'Fix the remark']);

    $this->actingAs($approver)->get(route('report-cards.index', ['status' => 'published']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('approvals.status', 'published')
            ->where('approvals.awaiting_count', 0)
            ->where('approvals.published_count', 1)
            ->where('approvals.sent_back_count', 1)
            ->where('approvals.groups.0.cards', fn ($cards) => count($cards) === 1 && $cards[0]['student_name'] === 'Riya Sharma'));

    $this->actingAs($approver)->get(route('report-cards.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('approvals.status', null)
            ->where('approvals.groups.0.cards.1.return_note', 'Fix the remark'));
});

it('keeps Approvals to approvers, the plan and an active school', function () {
    $this->actingAs($this->teacher)->get(route('approvals.index'))->assertForbidden();
    $this->actingAs($this->staffWith(['marks.enter']))->get(route('approvals.index'))->assertForbidden();

    $approver = $this->staffWith(['reportcards.approve']);
    $unplanned = $this->staffWith(['reportcards.approve']);
    $suspended = $this->staffWith(['reportcards.approve']);
    $this->actingAs($approver)->get(route('approvals.index'))->assertOk();

    $this->disableModule('exams');
    $this->actingAs($unplanned)->get(route('approvals.index'))->assertForbidden();
    $this->enableModules(['exams']);

    $this->setSchoolStatus(TenantStatus::Suspended);
    $this->actingAs($suspended)->get(route('approvals.index'))->assertForbidden();
});
