<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\IssueTransferCertificate;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

it('lists students with ulids only, in class and roll order, with masked phones for a plain viewer', function () {
    $this->makeStudent($this->main2A, 'Zoya', 'Khan');
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $this->makeStudent($this->main1A, 'Gurnoor', 'Singh');

    $this->actingAs($this->staffWith(['students.view']))
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('students/index')
            ->has('students.rows', 3)
            ->where('students.rows.0.name', 'Aditya Gill')
            ->where('students.rows.0.class_label', 'Class 1-A')
            ->where('students.rows.0.roll_number', 1)
            ->where('students.rows.1.name', 'Gurnoor Singh')
            ->where('students.rows.2.class_label', 'Class 2-A')
            ->where('students.current_students_total', 3)
            ->where('students.rows.0.guardian_phone', fn (string $phone) => str_contains($phone, '*') && ! str_contains($phone, '9876510'))
            ->missing('students.rows.0.id')
            ->has('classes', 4));
});

it('shows full guardian phones only to someone who may see sensitive details', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', null, null, true)]);

    $this->actingAs($this->staffWith(['students.view', 'students.view_sensitive']))
        ->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page->where('students.rows.0.guardian_phone', '+919876543210'));
});

it('paginates 25 to a page', function () {
    foreach (range(1, 27) as $n) {
        $this->makeStudent($this->main1A, 'Child'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'Test');
    }

    $this->actingAs($this->owner)->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.rows', 25)
            ->where('students.total', 27)
            ->where('students.last_page', 2)
            ->where('students.to', 25));

    $this->actingAs($this->owner)->get(route('students.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('students.rows', 2)->where('students.from', 26));
});

it('searches by name and admission number, and by phone only with sensitive access', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', null, null, true)]);
    $this->makeStudent($this->main1A, 'Riya', 'Nair');

    $search = fn ($who, string $term) => $this->actingAs($who)->get(route('students.index', ['q' => $term]));

    $search($this->owner, 'adit')->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Aditya Gill'));
    $search($this->owner, 'ADM/2026/0002')->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Riya Nair'));
    $search($this->owner, '98765432')->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Aditya Gill'));
    $search($this->staffWith(['students.view']), '98765432')->assertInertia(fn (Assert $page) => $page->has('students.rows', 0));
});

it('filters by class and by status, with "left the school" covering transferred students', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $left = $this->makeStudent($this->main1B, 'Leaver', 'Singh');
    app(IssueTransferCertificate::class)->handle($this->owner, $left, CalendarDate::today(), TransferReason::ChangingSchool);

    $get = fn (array $query) => $this->actingAs($this->owner)->get(route('students.index', $query));

    $get([])->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Aditya Gill'));
    $get(['status' => 'left'])->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Leaver Singh')->where('students.rows.0.status', 'transferred'));
    $get(['status' => 'all'])->assertInertia(fn (Assert $page) => $page->has('students.rows', 2));
    $get(['class' => $this->main1A->ulid])->assertInertia(fn (Assert $page) => $page->has('students.rows', 1));
    $get(['class' => $this->main1B->ulid])->assertInertia(fn (Assert $page) => $page->has('students.rows', 0));
    $get(['status' => 'bogus'])->assertSessionHasErrors('status');
});

it('limits the list to the viewer\'s scope', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $this->makeStudent($this->north1A, 'Far', 'Away');
    $this->makeStudent($this->main2A, 'Older', 'Child');

    $mainWing = $this->staffWith(['students.view'], Scope::wing([$this->main->ulid], 1, 1));

    $this->actingAs($mainWing)->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.rows', 1)
            ->where('students.rows.0.name', 'Aditya Gill')
            ->where('students.current_students_total', 1)
            ->has('classes', 2));

    $sections = $this->staffWith(['students.view'], Scope::sections([$this->north1A->ulid]));

    $this->actingAs($sections)->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page->has('students.rows', 1)->where('students.rows.0.name', 'Far Away'));
});

it('refuses the list to guests and to people without students.view', function () {
    $this->get(route('students.index'))->assertRedirect(route('login'));

    $this->actingAs($this->staffWith(['staff.view']))->get(route('students.index'))->assertForbidden();
});

it('blocks the list while the school is suspended', function () {
    $this->school->update(['status' => 'suspended']);

    $this->actingAs($this->owner)->get(route('students.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('errors/subscription'));
});

it('lets someone who reaches several campuses narrow the list with the top-bar switcher', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $this->makeStudent($this->north1A, 'Far', 'Away');

    $this->actingAs($this->owner)
        ->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('campusSwitcher.campuses', 2)
            ->where('campusSwitcher.selected', null)
            ->has('students.rows', 2));

    $this->put(route('school.campus.select'), ['campus' => $this->north->ulid])->assertSessionHasNoErrors();

    $this->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('campusSwitcher.selected', $this->north->ulid)
            ->has('students.rows', 1)
            ->where('students.rows.0.name', 'Far Away')
            ->where('students.current_students_total', 1)
            ->has('classes', 1));

    $this->put(route('school.campus.select'), ['campus' => ''])->assertSessionHasNoErrors();

    $this->get(route('students.index'))->assertInertia(fn (Assert $page) => $page->has('students.rows', 2));
});

it('shows no switcher to someone who reaches one campus and refuses a campus they cannot reach', function () {
    $mainOnly = $this->staffWith(['students.view'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('students.index'))
        ->assertInertia(fn (Assert $page) => $page->where('campusSwitcher', null));

    $this->put(route('school.campus.select'), ['campus' => $this->north->ulid])->assertSessionHasErrors('campus');
});

it('shows a student profile with ulids, history and abilities', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [
        new NewGuardian('Pooja Gill', 'Mother', '9876543210', 'pooja@mail.example', 'Doctor', true),
        new NewGuardian('Sandeep Gill', 'Father', '9876543211', null, 'Doctor'),
    ]);

    $this->actingAs($this->owner)
        ->get(route('students.show', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('students/show')
            ->where('student.ulid', $student->ulid)
            ->where('student.class_label', 'Class 1-A')
            ->where('student.house_name', 'Raman')
            ->where('student.blood_group', 'O+')
            ->where('student.guardians.0.name', 'Pooja Gill')
            ->where('student.guardians.0.phone', '+919876543210')
            ->where('student.guardians.0.parent_app', 'invited')
            ->has('student.enrollments', 1)
            ->where('student.enrollments.0.is_current', true)
            ->where('student.abilities.manage', true)
            ->where('student.abilities.issue_tc', true)
            ->has('student.section_options', 2)
            ->has('options.houses', 2)
            ->missing('student.id')
            ->missing('student.guardians.0.id')
            ->where('tab', 'overview'));
});

it('hides what a plain viewer may not see on the profile', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', null, null, true)]);

    $this->actingAs($this->staffWith(['students.view']))
        ->get(route('students.show', $student))
        ->assertInertia(fn (Assert $page) => $page
            ->where('student.guardians.0.phone', fn (string $phone) => str_contains($phone, '*'))
            ->where('student.documents', null)
            ->where('student.edit', null)
            ->where('student.abilities.manage', false)
            ->has('student.section_options', 0));
});

it('returns 404, not 403, for a student outside the viewer\'s reach', function () {
    $far = $this->makeStudent($this->north1A, 'Far', 'Away');
    $mainOnly = $this->staffWith(['students.view', 'students.manage'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('students.show', $far))->assertNotFound();
    $this->post(route('students.section', $far), ['section' => $this->north1A->ulid])->assertNotFound();
});

it('names siblings in reach and only counts the ones outside it', function () {
    $phone = '9876543210';
    $guardian = fn () => [new NewGuardian('Pooja Gill', 'Mother', $phone, null, null, true)];

    $aarav = $this->makeStudent($this->main1A, 'Aarav', 'Gill', $guardian());
    $meera = $this->makeStudent($this->main1A, 'Meera', 'Gill', $guardian());
    $far = $this->makeStudent($this->north1A, 'Far', 'Gill', $guardian());

    $this->actingAs($this->owner)->get(route('students.show', $aarav))
        ->assertInertia(fn (Assert $page) => $page
            ->has('student.siblings', 2)
            ->where('student.hidden_siblings', 0)
            ->where('student.siblings.0.via', 'guardian')
            ->where('student.guardians.0.other_children', fn ($names) => count($names) === 2));

    $mainOnly = $this->staffWith(['students.view'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->get(route('students.show', $aarav))
        ->assertInertia(fn (Assert $page) => $page
            ->has('student.siblings', 1)
            ->where('student.siblings.0.name', 'Meera Gill')
            ->where('student.hidden_siblings', 1)
            ->where('student.guardians.0.hidden_other_children', 1));

    $this->get(route('students.index'))->assertInertia(fn (Assert $page) => $page->where('students.rows.0.has_sibling', true));

    expect($far->id)->not->toBe($meera->id);
});

it('edits a student over HTTP, validating the form', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', null, null, true)]);
    $guardian = Guardian::query()->sole();

    $payload = fn (array $override = []) => array_merge([
        'first_name' => 'Aditya', 'last_name' => 'Gill', 'gender' => 'male', 'born_on' => '2019-06-07', 'blood_group' => 'B+',
        'house' => $this->tagore->ulid, 'address' => 'Phase 9, Mohali',
        'guardians' => [['ulid' => $guardian->ulid, 'name' => 'Pooja Gill', 'relation' => 'Mother', 'phone' => '9876543210', 'email' => '', 'occupation' => 'Doctor', 'is_primary' => true, 'has_portal_access' => true]],
    ], $override);

    $this->actingAs($this->owner);

    $this->patch(route('students.update', $student), $payload())->assertSessionHasNoErrors();

    expect($student->refresh()->blood_group->value)->toBe('B+')->and($student->house_id)->toBe($this->tagore->id);

    $this->patch(route('students.update', $student), $payload(['born_on' => '2099-01-01', 'address' => '']))->assertSessionHasErrors(['born_on', 'address']);
    $this->patch(route('students.update', $student), $payload(['gender' => 'robot']))->assertSessionHasErrors('gender');
    $this->patch(route('students.update', $student), $payload(['house' => 'nope']))->assertSessionHasErrors('house');
    $this->patch(route('students.update', $student), $payload(['house' => '']))->assertSessionHasNoErrors();

    expect($student->refresh()->house_id)->toBeNull();
});

it('refuses an inactive house for a student unless they already have it', function () {
    $student = $this->makeStudent($this->main1A);
    $this->raman->update(['status' => 'inactive']);
    $this->tagore->update(['status' => 'inactive']);
    $guardian = Guardian::query()->sole();

    $payload = fn (string $house) => [
        'first_name' => 'Aditya', 'last_name' => 'Gill', 'gender' => 'male', 'born_on' => '2019-06-07', 'blood_group' => 'O+',
        'house' => $house, 'address' => 'Sector 70, Mohali',
        'guardians' => [['ulid' => $guardian->ulid, 'name' => $guardian->name, 'relation' => 'Mother', 'phone' => $guardian->phone, 'email' => '', 'occupation' => '', 'is_primary' => true, 'has_portal_access' => true]],
    ];

    $this->actingAs($this->owner);

    // the student keeps the house they already have, even though it is inactive now
    $this->patch(route('students.update', $student), $payload($this->raman->ulid))->assertSessionHasNoErrors();
    $this->patch(route('students.update', $student), $payload($this->tagore->ulid))->assertSessionHasErrors('house');
});

it('changes a section and a roll number over HTTP', function () {
    $student = $this->makeStudent($this->main1A);

    $this->actingAs($this->owner);

    $this->post(route('students.section', $student), ['section' => $this->main1B->ulid, 'reason' => 'Parent request'])->assertSessionHasNoErrors();
    $this->post(route('students.section', $student), ['section' => $this->main2A->ulid])->assertSessionHasErrors('student');
    $this->patch(route('students.roll-number', $student), ['roll_number' => 12])->assertSessionHasNoErrors();
    $this->patch(route('students.roll-number', $student), ['roll_number' => 0])->assertSessionHasErrors('roll_number');

    $current = Enrollment::query()->where('student_id', $student->id)->where('is_current', true)->sole();

    expect($current->section_id)->toBe($this->main1B->id)->and($current->roll_number)->toBe(12);
});

it('issues a TC over HTTP and shows the printable certificate', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $today = CalendarDate::today();

    $this->actingAs($this->owner);

    $this->post(route('students.tc.store', $student), ['last_day' => $today, 'reason' => 'family_relocating'])
        ->assertRedirect(route('students.tc.show', $student));

    $this->get(route('students.tc.show', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('students/transfer-certificate')
            ->where('certificate.tc_number', 'TC/2026/0001')
            ->where('certificate.student_name', 'Aditya Gill')
            ->where('certificate.class_label', 'Class 1-A')
            ->where('certificate.reason', 'Family relocating')
            ->where('certificate.last_day', $today)
            ->has('certificate.guardian_names', 1));

    $this->post(route('students.tc.store', $student), ['last_day' => $today, 'reason' => 'other'])->assertSessionHasErrors('student');
});

it('keeps the TC to people holding students.issue_tc', function () {
    $student = $this->makeStudent($this->main1A);
    $coordinator = $this->staffWith(['students.view', 'students.manage']);

    $this->actingAs($coordinator)
        ->post(route('students.tc.store', $student), ['last_day' => CalendarDate::today(), 'reason' => 'other'])
        ->assertForbidden();

    expect($student->refresh()->tc_number)->toBeNull();
});

it('audits the changes made over HTTP', function () {
    $student = $this->makeStudent($this->main1A);

    $this->actingAs($this->owner)->post(route('students.section', $student), ['section' => $this->main1B->ulid]);

    expect(AuditEvent::query()->where('action', 'students.section_changed')->exists())->toBeTrue();
    expect(Student::query()->count())->toBe(1);
});
