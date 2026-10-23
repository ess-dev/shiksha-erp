<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Admissions\Actions\AddEnquiry;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(function () {
    DB::connection('landlord')->table('tenant_modules')->insert([
        'tenant_id' => $this->school->id, 'module' => 'admissions', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->setUpStudentSchool();
    Storage::fake('tenant');
});

/** Adds an enquiry as the owner (or the given actor) for Class 1 at the Main campus by default. */
function enquiry(array $overrides = [], $actor = null): AdmissionApplication
{
    $test = test();
    $input = array_merge([
        'first' => 'Advika', 'last' => 'Bansal', 'gender' => 'female', 'born' => null,
        'class' => $test->main1A->school_class_id, 'campus' => $test->main->ulid,
        'parent' => 'Komal Bansal', 'relation' => 'Mother', 'phone' => $test->nextPhone(),
    ], $overrides);

    $classUlid = DB::connection('tenant')->table('school_classes')->where('id', $input['class'])->value('ulid');

    return app(AddEnquiry::class)->handle($actor ?? $test->owner, new NewEnquiry(
        firstName: $input['first'], lastName: $input['last'], gender: $input['gender'], bornOn: $input['born'],
        classUlid: $classUlid, campusUlid: $input['campus'], parentName: $input['parent'], parentRelation: $input['relation'],
        phone: $input['phone'], source: AdmissionSource::Website,
    ));
}

/** Fills the details the Application stage collects. */
function completeDetails(AdmissionApplication $application): void
{
    test()->actingAs(test()->owner)
        ->put(route('admissions.details', $application), ['born_on' => '2019-06-07', 'blood_group' => 'O+', 'address' => 'Sector 70, Mohali'])
        ->assertSessionHasNoErrors();
}

function moveTo(AdmissionApplication $application, string $stage, bool $force = false)
{
    return test()->actingAs(test()->owner)->post(route('admissions.move', $application), ['stage' => $stage, 'accept_missing_documents' => $force]);
}

it('adds an enquiry at the Enquiry stage with history and an audit row', function () {
    $application = enquiry();

    expect($application->stage->value)->toBe('enquiry')
        ->and($application->phone)->toStartWith('+91')
        ->and($application->events()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'admissions.enquiry_added')->count())->toBe(1);

    $this->actingAs($this->owner)->get(route('admissions.index'))->assertInertia(fn (Assert $page) => $page
        ->has('board.cards', 1)
        ->where('board.cards.0.name', 'Advika Bansal')
        ->where('board.cards.0.class_name', 'Class 1')
        ->where('board.cards.0.missing_documents', 3)
        ->where('board.summary.open_applications', 1)
        ->where('board.summary.conversion_percent', 0)
        ->missing('board.cards.0.id'));
});

it('refuses an enquiry for a class the campus does not run, or with a bad phone', function () {
    $this->actingAs($this->owner)
        ->post(route('admissions.store'), [
            'first_name' => 'Zoya', 'last_name' => 'Mirza', 'gender' => 'female', 'class' => DB::connection('tenant')->table('school_classes')->where('level', 2)->value('ulid'),
            'campus' => $this->north->ulid, 'parent_name' => 'Imran Mirza', 'relation' => 'Father', 'phone' => '9876543210', 'source' => 'website',
        ])
        ->assertSessionHasErrors('application');

    $this->actingAs($this->owner)
        ->post(route('admissions.store'), [
            'first_name' => 'Zoya', 'last_name' => 'Mirza', 'gender' => 'female',
            'class' => DB::connection('tenant')->table('school_classes')->where('level', 1)->value('ulid'),
            'campus' => $this->main->ulid, 'parent_name' => 'Imran Mirza', 'relation' => 'Father', 'phone' => '12345', 'source' => 'website',
        ])
        ->assertSessionHasErrors('application');

    expect(AdmissionApplication::query()->count())->toBe(0);
});

it('moves through the stages and holds back details and documents', function () {
    $application = enquiry();

    moveTo($application, 'application')->assertSessionHasNoErrors();
    moveTo($application, 'verified')->assertSessionHasErrors('application'); // details missing

    completeDetails($application);

    moveTo($application, 'verified')->assertSessionHasErrors('application'); // 3 documents missing
    expect($application->fresh()->stage->value)->toBe('application');

    moveTo($application, 'verified', force: true)->assertSessionHasNoErrors();
    expect($application->fresh()->stage->value)->toBe('verified')
        ->and(str_contains((string) $application->events()->reorder('id', 'desc')->first()->note, '3 document(s) still missing'))->toBeTrue();

    moveTo($application, 'enrolled')->assertSessionHasErrors('application');
});

it('clears the decision when an application moves back from Decision', function () {
    $application = enquiry();
    completeDetails($application);
    moveTo($application, 'decision', force: true);

    $this->actingAs($this->owner)->post(route('admissions.decision', $application), ['decision' => 'waitlisted'])->assertSessionHasNoErrors();
    expect($application->fresh()->decision->value)->toBe('waitlisted');

    moveTo($application, 'application');
    expect($application->fresh()->decision)->toBeNull();
});

it('records decisions only at the Decision stage and closes a refused application', function () {
    $application = enquiry();

    $this->actingAs($this->owner)->post(route('admissions.decision', $application), ['decision' => 'accepted'])->assertSessionHasErrors('application');

    completeDetails($application);
    moveTo($application, 'decision', force: true);

    $this->post(route('admissions.decision', $application), ['decision' => 'rejected'])->assertSessionHasNoErrors();

    expect($application->fresh()->is_closed)->toBeTrue();
    moveTo($application, 'application')->assertSessionHasErrors('application');

    $this->get(route('admissions.index'))->assertInertia(fn (Assert $page) => $page->has('board.cards', 0));
    $this->get(route('admissions.index', ['closed' => 1]))->assertInertia(fn (Assert $page) => $page->has('board.cards', 1)->where('board.cards.0.is_closed', true));
});

it('enrols an accepted applicant as a student with the guardian and the collected scans', function () {
    $application = enquiry(['phone' => '9812345678']);
    $this->actingAs($this->owner);
    completeDetails($application);

    $this->post(route('admissions.documents.store', [$application, 'birth_certificate']), ['file' => UploadedFile::fake()->create('birth.pdf', 80, 'application/pdf')])
        ->assertSessionHasNoErrors();

    moveTo($application, 'decision', force: true);
    $this->post(route('admissions.decision', $application), ['decision' => 'accepted'])->assertSessionHasNoErrors();

    $this->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admissions.index'));

    $student = Student::query()->sole();
    $application->refresh();

    expect($application->stage->value)->toBe('enrolled')
        ->and($application->student_id)->toBe($student->id)
        ->and($student->first_name)->toBe('Advika')
        ->and((string) $student->born_on)->toStartWith('2019-06-07')
        ->and(Guardian::query()->sole()->phone)->toBe('+919812345678')
        ->and(StudentDocument::query()->sole()->source)->toBe('admission')
        ->and(DB::connection('tenant')->table('enrollments')->where('student_id', $student->id)->value('roll_number'))->toBe(1)
        ->and(AuditEvent::query()->where('action', 'admissions.applicant_enrolled')->count())->toBe(1);

    Storage::disk('tenant')->assertExists(StudentDocument::query()->sole()->path);

    // A second click does not enrol the child twice, and the card is locked.
    $this->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])->assertSessionHasErrors('application');
    moveTo($application, 'application')->assertSessionHasErrors('application');
    expect(Student::query()->count())->toBe(1);
});

it('refuses to enrol an applicant who is not accepted, or into a wrong or full section', function () {
    $application = enquiry();
    completeDetails($application);
    moveTo($application, 'decision', force: true);

    $this->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])->assertSessionHasErrors('application');

    $this->post(route('admissions.decision', $application), ['decision' => 'accepted']);

    // Class 2 section: wrong class.
    $this->post(route('admissions.enrol', $application), ['section' => $this->main2A->ulid])->assertSessionHasErrors('application');

    // 1-B holds two students: fill it.
    $this->makeStudent($this->main1B, 'Aarav', 'Sethi');
    $this->makeStudent($this->main1B, 'Bhavya', 'Sethi');
    $this->post(route('admissions.enrol', $application), ['section' => $this->main1B->ulid])->assertSessionHasErrors('application');

    expect($application->fresh()->stage->value)->toBe('decision');
});

it('applies the one-phone-one-guardian rule when enrolling', function () {
    $existing = $this->makeStudent($this->main1A);
    $phone = Guardian::query()->firstOrFail()->phone;

    $application = enquiry(['phone' => $phone, 'parent' => 'Someone Else']);
    completeDetails($application);
    moveTo($application, 'decision', force: true);
    $this->post(route('admissions.decision', $application), ['decision' => 'accepted']);

    $this->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])->assertSessionHasErrors('student');

    expect(Student::query()->count())->toBe(1)
        ->and($application->fresh()->stage->value)->toBe('decision')
        ->and($existing)->not->toBeNull();
});

it('limits people to the campuses in their scope and returns 404 outside it', function () {
    $mine = enquiry(['campus' => $this->main->ulid]);
    $other = enquiry(['first' => 'Mihir', 'campus' => $this->north->ulid, 'class' => $this->north1A->school_class_id]);

    $mainOnly = $this->staffWith(['admissions.view', 'admissions.manage'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('admissions.index'))->assertInertia(fn (Assert $page) => $page
        ->has('board.cards', 1)->where('board.cards.0.name', 'Advika Bansal'));

    $this->get(route('admissions.index', ['open' => $other->ulid]))->assertNotFound();
    $this->post(route('admissions.move', $other), ['stage' => 'application'])->assertNotFound();
    $this->put(route('admissions.notes', $other), ['notes' => 'x'])->assertNotFound();

    expect($other->fresh()->stage->value)->toBe('enquiry')
        ->and($mine->fresh()->stage->value)->toBe('enquiry');

    // Adding an enquiry at a campus outside the scope is refused.
    $this->post(route('admissions.store'), [
        'first_name' => 'Zoya', 'last_name' => 'Mirza', 'gender' => 'female',
        'class' => DB::connection('tenant')->table('school_classes')->where('level', 1)->value('ulid'),
        'campus' => $this->north->ulid, 'parent_name' => 'Imran Mirza', 'relation' => 'Father', 'phone' => '9876500001', 'source' => 'website',
    ])->assertForbidden();
});

it('denies without the permission, and enrolling without admissions.enroll', function () {
    $application = enquiry();
    completeDetails($application);
    moveTo($application, 'decision', force: true);
    $this->post(route('admissions.decision', $application), ['decision' => 'accepted']);

    $teacher = $this->staffWith(['students.view']);
    $this->actingAs($teacher)->get(route('admissions.index'))->assertForbidden();

    $viewer = $this->staffWith(['admissions.view']);
    $this->actingAs($viewer)->post(route('admissions.move', $application), ['stage' => 'application'])->assertForbidden();

    $manager = $this->staffWith(['admissions.view', 'admissions.manage']);
    $this->actingAs($manager)->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])->assertForbidden();

    $enroller = $this->staffWith(['admissions.view', 'admissions.manage', 'admissions.enroll']);
    $this->actingAs($enroller)->post(route('admissions.enrol', $application), ['section' => $this->main1A->ulid])->assertSessionHasNoErrors();

    expect(Student::query()->count())->toBe(1);
});

it('hides the module when it is not in the plan', function () {
    DB::connection('landlord')->table('tenant_modules')->where('module', 'admissions')->delete();

    $this->actingAs($this->owner)->get(route('admissions.index'))->assertNotFound();
});

it('masks the phone unless the viewer may see sensitive details', function () {
    $application = enquiry(['phone' => '9812345678']);

    $front = $this->staffWith(['admissions.view', 'admissions.manage']);
    $this->actingAs($front)->get(route('admissions.index', ['open' => $application->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.phone', fn (string $phone) => ! str_contains($phone, '9812345678')));

    $this->actingAs($this->owner)->get(route('admissions.index', ['open' => $application->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.phone', '+919812345678'));
});

it('ticks documents, uploads scans, and serves them from a signed link after a permission check', function () {
    $application = enquiry();
    $this->actingAs($this->owner);

    $this->put(route('admissions.documents.received', [$application, 'aadhaar']), ['received' => true])->assertSessionHasNoErrors();
    $this->post(route('admissions.documents.store', [$application, 'photo']), ['file' => UploadedFile::fake()->create('photo.png', 20, 'image/png')])->assertSessionHasNoErrors();
    $this->post(route('admissions.documents.store', [$application, 'photo']), ['file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])->assertSessionHasErrors('file');

    $this->get(route('admissions.index', ['open' => $application->ulid]))->assertInertia(fn (Assert $page) => $page
        ->where('selected.missing_documents', 1)
        ->where('selected.documents.1.is_received', true)
        ->where('selected.documents.2.file_name', 'photo.png')
        ->where('selected.documents.2.view_url', fn (string $url) => str_contains($url, 'signature=')));

    $url = $this->get(route('admissions.index', ['open' => $application->ulid]))->inertiaProps('selected.documents.2.view_url');
    $this->get($url)->assertOk();
    expect(AuditEvent::query()->where('action', 'admissions.document_opened')->count())->toBe(1);

    // Without the signature, or without view_sensitive, the scan is not served.
    $this->get(route('admissions.documents.show', [$application, 'photo']))->assertForbidden();
    $viewer = $this->staffWith(['admissions.view']);
    $this->actingAs($viewer)->get($url)->assertForbidden();
});

it('requires different documents by class, using the defaults until the school saves its own', function () {
    $lowest = enquiry();
    $higher = enquiry(['first' => 'Devansh', 'class' => $this->main2A->school_class_id]);

    $this->actingAs($this->owner);
    $kinds = fn ($application) => $this->get(route('admissions.index', ['open' => $application->ulid]))->inertiaProps('selected.documents');

    expect(collect($kinds($lowest))->pluck('kind')->all())->toBe(['birth_certificate', 'aadhaar', 'photo'])
        ->and(collect($kinds($higher))->pluck('kind')->all())->toBe(['birth_certificate', 'aadhaar', 'transfer_certificate', 'photo']);

    $classUlids = DB::connection('tenant')->table('school_classes')->pluck('ulid', 'level');

    $this->put(route('admissions.settings.documents'), ['rules' => [
        'birth_certificate' => ['required' => true, 'all_classes' => true],
        'aadhaar' => ['required' => false, 'all_classes' => false],
        'transfer_certificate' => ['required' => true, 'all_classes' => false, 'classes' => [$classUlids[1]]],
        'photo' => ['required' => false, 'all_classes' => false],
    ]])->assertSessionHasNoErrors();

    expect(collect($kinds($lowest))->pluck('kind')->all())->toBe(['birth_certificate', 'transfer_certificate'])
        ->and(collect($kinds($higher))->pluck('kind')->all())->toBe(['birth_certificate']);

    // An empty class list for a required kind is refused, and so is a non-manager.
    $this->put(route('admissions.settings.documents'), ['rules' => [
        'birth_certificate' => ['required' => true, 'all_classes' => false, 'classes' => []],
    ]])->assertSessionHasErrors('house');

    $this->actingAs($this->staffWith(['admissions.view', 'admissions.manage']))
        ->put(route('admissions.settings.documents'), ['rules' => ['birth_certificate' => ['required' => true, 'all_classes' => true]]])
        ->assertForbidden();
});

it('gives the new permission to the right role templates', function () {
    $has = fn (string $template, string $key) => in_array($key, DB::connection('landlord')->table('role_templates')
        ->join('role_template_versions', fn ($join) => $join->on('role_template_versions.role_template_id', '=', 'role_templates.id')->on('role_template_versions.version', '=', 'role_templates.current_version'))
        ->join('role_template_version_permissions', 'role_template_version_permissions.role_template_version_id', '=', 'role_template_versions.id')
        ->where('role_templates.key', $template)->pluck('permission_key')->all(), true);

    expect($has('principal', 'admissions.enroll'))->toBeTrue()
        ->and($has('vice_principal', 'admissions.enroll'))->toBeTrue()
        ->and($has('coordinator', 'admissions.enroll'))->toBeTrue()
        ->and($has('admission_officer', 'admissions.enroll'))->toBeTrue()
        ->and($has('front_office', 'admissions.manage'))->toBeTrue()
        ->and($has('front_office', 'admissions.enroll'))->toBeFalse()
        ->and($has('class_teacher', 'admissions.view'))->toBeFalse();
});
