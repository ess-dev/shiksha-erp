<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\School\Actions\UpdateSchoolSettings;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\ChangeSection;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Actions\IssueTransferCertificate;
use App\Domain\Students\Actions\SetRollNumber;
use App\Domain\Students\Actions\UpdateStudentDetails;
use App\Domain\Students\Data\GuardianUpdate;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Data\StudentDetailsUpdate;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\EnrollmentResult;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Domain\Students\SiblingFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

it('enrols a student with a generated admission number, a roll number and an audit event', function () {
    $first = $this->makeStudent($this->main1A, 'Aditya', 'Gill');
    $second = $this->makeStudent($this->main1A, 'Gurnoor', 'Singh');

    expect($first->admission_number)->toBe('ADM/2026/0001')
        ->and($second->admission_number)->toBe('ADM/2026/0002')
        ->and($first->status)->toBe(StudentStatus::Enrolled)
        ->and($first->born_on)->toBe('2019-06-07')
        ->and(Enrollment::query()->where('student_id', $first->id)->sole()->roll_number)->toBe(1)
        ->and(Enrollment::query()->where('student_id', $second->id)->sole()->roll_number)->toBe(2);

    $event = AuditEvent::query()->where('action', 'students.student_created')->where('subject_ulid', $first->ulid)->sole();

    expect($event->after['section'])->toBe($this->main1A->ulid)
        ->and(json_encode($event->after))->not->toContain('+91');
});

it('uses the school\'s admission-number prefix', function () {
    app(UpdateSchoolSettings::class)->handle($this->owner, 'gps', 'tc');

    expect($this->makeStudent($this->main1A)->admission_number)->toBe('GPS/2026/0001');
});

it('accepts an admission number given by the school and refuses a repeat', function () {
    $input = fn (string $number) => new NewStudent(
        'Tara', 'Menon', Gender::Female, '2018-11-30', BloodGroup::Unknown, null, 'Phase 9, Mohali', $this->main1A->ulid,
        [new NewGuardian('Lakshmi Menon', 'Mother', $this->nextPhone())], $number,
    );

    app(CreateStudent::class)->handle($this->owner, $input('GPS/2026/1201'));

    expect(fn () => app(CreateStudent::class)->handle($this->owner, $input('gps/2026/1201')))
        ->toThrow(StudentException::class, 'already used');
});

it('links a second child to the same guardian when the phone and name match', function () {
    $phone = $this->nextPhone();
    $parent = fn () => [new NewGuardian('Pooja Gill', 'Mother', $phone, null, null, true)];

    $one = $this->makeStudent($this->main1A, 'Aditya', 'Gill', $parent());
    $two = $this->makeStudent($this->north1A, 'Meera', 'Gill', $parent());

    expect(Guardian::query()->count())->toBe(1)
        ->and(app(SiblingFinder::class)->idsFor($one->id))->toBe([$two->id])
        ->and(app(SiblingFinder::class)->idsFor($two->id))->toBe([$one->id]);

    $event = AuditEvent::query()->where('action', 'students.student_created')->where('subject_ulid', $two->ulid)->sole();

    expect($event->after['linked_guardians'])->toHaveCount(1)->and($event->after['new_guardians'])->toBe([]);
});

it('treats the same phone written differently as one guardian', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '98765 43210')]);
    $this->makeStudent($this->main1A, 'Meera', 'Gill', [new NewGuardian('  pooja   GILL ', 'Mother', '+919876543210')]);

    expect(Guardian::query()->count())->toBe(1)->and(Guardian::query()->sole()->phone)->toBe('+919876543210');
});

it('refuses a phone that already belongs to a differently named guardian', function () {
    $phone = $this->nextPhone();
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', $phone)]);

    expect(fn () => $this->makeStudent($this->main1A, 'Riya', 'Nair', [new NewGuardian('Sandeep Nair', 'Father', $phone)]))
        ->toThrow(StudentException::class, 'already belongs to the guardian Pooja Gill');

    expect(Student::query()->count())->toBe(1)->and(Guardian::query()->count())->toBe(1);
});

it('refuses two guardians of one child sharing a phone, and unusable phones', function () {
    $phone = $this->nextPhone();

    expect(fn () => $this->makeStudent($this->main1A, 'Aditya', 'Gill', [
        new NewGuardian('Pooja Gill', 'Mother', $phone), new NewGuardian('Sandeep Gill', 'Father', $phone),
    ]))->toThrow(StudentException::class, 'own phone number')
        ->and(fn () => $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '12345')]))
        ->toThrow(StudentException::class, 'not a valid Indian mobile')
        ->and(fn () => $this->makeStudent($this->main1A, 'Aditya', 'Gill', []))
        ->toThrow(StudentException::class, 'at least one guardian');

    expect(Student::query()->count())->toBe(0);
});

it('marks exactly one guardian as primary', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [
        new NewGuardian('Sandeep Gill', 'Father', $this->nextPhone()),
        new NewGuardian('Pooja Gill', 'Mother', $this->nextPhone(), isPrimary: true),
    ]);

    $primary = $student->guardians()->get()->filter(fn ($guardian) => $guardian->pivot->is_primary);

    expect($primary)->toHaveCount(1)->and($primary->first()->name)->toBe('Pooja Gill');
});

it('moves a student to another section of the same class and keeps the history', function () {
    $student = $this->makeStudent($this->main1A);
    $this->makeStudent($this->main1B, 'Other', 'Child');

    app(ChangeSection::class)->handle($this->owner, $student, $this->main1B->ulid, 'Parent request');

    $enrollments = Enrollment::query()->where('student_id', $student->id)->orderBy('id')->get();

    expect($enrollments)->toHaveCount(2)
        ->and($enrollments[0]->is_current)->toBeFalse()
        ->and($enrollments[0]->result)->toBe(EnrollmentResult::Moved)
        ->and($enrollments[0]->section_id)->toBe($this->main1A->id)
        ->and($enrollments[1]->is_current)->toBeTrue()
        ->and($enrollments[1]->section_id)->toBe($this->main1B->id)
        ->and($enrollments[1]->roll_number)->toBe(2)
        ->and($enrollments[1]->note)->toBe('Parent request');

    expect(AuditEvent::query()->where('action', 'students.section_changed')->sole()->after['section'])->toBe($this->main1B->ulid);
});

it('refuses a section of another class, the same section, or a full one', function () {
    $student = $this->makeStudent($this->main1A);
    $this->makeStudent($this->main1B, 'One', 'Child');
    $this->makeStudent($this->main1B, 'Two', 'Child');

    $change = fn (string $ulid) => fn () => app(ChangeSection::class)->handle($this->owner, $student, $ulid);

    expect($change($this->main2A->ulid))->toThrow(StudentException::class, 'same class')
        ->and($change($this->main1A->ulid))->toThrow(StudentException::class, 'already in that section')
        ->and($change($this->main1B->ulid))->toThrow(StudentException::class, 'is full');
});

it('lets a coordinator move students only inside their own reach', function () {
    $coordinator = $this->staffWith(['students.view', 'students.manage'], Scope::wing([$this->main->ulid], 1, 1));
    $student = $this->makeStudent($this->main1A);
    $outside = $this->makeStudent($this->north1A, 'Far', 'Away');

    app(ChangeSection::class)->handle($coordinator, $student, $this->main1B->ulid);

    expect(Enrollment::query()->where('student_id', $student->id)->where('is_current', true)->sole()->section_id)->toBe($this->main1B->id);

    // the target section lies outside the coordinator's campus
    expect(fn () => app(ChangeSection::class)->handle($coordinator, $student, $this->north1A->ulid))
        ->toThrow(StudentException::class, 'outside your access');

    // the student is outside the coordinator's reach: they cannot even see that they exist
    expect(fn () => app(ChangeSection::class)->handle($coordinator, $outside, $this->main1B->ulid))
        ->toThrow(StudentException::class, 'not found');
});

it('refuses to change a section without the permission', function () {
    $student = $this->makeStudent($this->main1A);
    $teacher = $this->staffWith(['students.view']);

    expect(fn () => app(ChangeSection::class)->handle($teacher, $student, $this->main1B->ulid))
        ->toThrow(StudentException::class, 'not allowed');
});

it('sets a custom roll number that is unique within the section', function () {
    $one = $this->makeStudent($this->main1A, 'One', 'Child');
    $two = $this->makeStudent($this->main1A, 'Two', 'Child');

    app(SetRollNumber::class)->handle($this->owner, $two, 9);

    expect(Enrollment::query()->where('student_id', $two->id)->sole()->roll_number)->toBe(9)
        ->and(fn () => app(SetRollNumber::class)->handle($this->owner, $one, 9))
        ->toThrow(StudentException::class, 'Roll number 9 is already used')
        ->and(fn () => app(SetRollNumber::class)->handle($this->owner, $one, 0))
        ->toThrow(StudentException::class, 'whole number');
});

it('issues a transfer certificate: status, TC number, closed enrolment and audit', function () {
    app(UpdateSchoolSettings::class)->handle($this->owner, 'ADM', 'gps-tc');
    $student = $this->makeStudent($this->main1A);
    $other = $this->makeStudent($this->main1A, 'Other', 'Child');

    $today = CalendarDate::today();

    app(IssueTransferCertificate::class)->handle($this->owner, $student, $today, TransferReason::FamilyRelocating);
    app(IssueTransferCertificate::class)->handle($this->owner, $other, $today, TransferReason::Other);

    $student->refresh();
    $enrollment = Enrollment::query()->where('student_id', $student->id)->sole();

    expect($student->status)->toBe(StudentStatus::Transferred)
        ->and($student->tc_number)->toBe('GPS-TC/2026/0001')
        ->and($other->refresh()->tc_number)->toBe('GPS-TC/2026/0002')
        ->and($student->left_on)->toBe($today)
        ->and($student->leave_reason)->toBe('Family relocating')
        ->and($enrollment->is_current)->toBeFalse()
        ->and($enrollment->result)->toBe(EnrollmentResult::Left)
        ->and($enrollment->ended_on)->toBe($today);

    expect(AuditEvent::query()->where('action', 'students.transfer_certificate_issued')->count())->toBe(2);
});

it('refuses a TC twice, for a bad date, or without the critical permission', function () {
    $student = $this->makeStudent($this->main1A);
    $issue = fn ($who, ?string $day = null) => fn () => app(IssueTransferCertificate::class)->handle($who, $student, $day ?? CalendarDate::today(), TransferReason::Other);

    expect($issue($this->owner, '2026-02-30'))->toThrow(StudentException::class, 'not a valid date')
        ->and($issue($this->owner, '2020-01-01'))->toThrow(StudentException::class, 'before the admission date')
        ->and($issue($this->staffWith(['students.view', 'students.manage'])))->toThrow(StudentException::class, 'not allowed');

    $issue($this->owner)();

    expect($issue($this->owner))->toThrow(StudentException::class, 'Only a current student');
});

it('edits a student and the guardians, auditing only what changed', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', 'old@mail.example', 'Doctor', true)]);
    $guardian = Guardian::query()->sole();

    app(UpdateStudentDetails::class)->handle($this->owner, $student, new StudentDetailsUpdate(
        'Aditya', 'Gill', Gender::Other, '2019-06-08', BloodGroup::ABNegative, $this->tagore->id, 'Phase 9, Mohali',
        [new GuardianUpdate($guardian->ulid, 'Pooja Gill', 'Mother', '9876543210', 'pooja@mail.example', 'Doctor', true, false)],
    ));

    $student->refresh();

    expect($student->gender)->toBe(Gender::Other)
        ->and($student->born_on)->toBe('2019-06-08')
        ->and($student->blood_group)->toBe(BloodGroup::ABNegative)
        ->and($student->house_id)->toBe($this->tagore->id)
        ->and($guardian->refresh()->email)->toBe('pooja@mail.example')
        ->and($student->guardians()->first()->pivot->has_portal_access)->toBeFalse();

    $event = AuditEvent::query()->where('action', 'students.student_updated')->sole();

    expect(array_keys($event->before))->toEqualCanonicalizing(['gender', 'born_on', 'blood_group', 'house_id', 'address'])
        ->and($event->after['guardians'][0]['changed'])->toContain('email', 'link');
});

it('does not let a guardian take another guardian\'s phone, or be changed by someone who cannot reach all their children', function () {
    $student = $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', null, null, true)]);
    $this->makeStudent($this->main1A, 'Riya', 'Nair', [new NewGuardian('Sandeep Nair', 'Father', '9876543211', null, null, true)]);
    $guardian = Guardian::query()->where('name', 'Pooja Gill')->sole();

    $update = fn (string $phone, string $name = 'Pooja Gill') => new StudentDetailsUpdate(
        'Aditya', 'Gill', Gender::Male, '2019-06-07', BloodGroup::OPositive, $this->raman->id, 'Sector 70, Mohali',
        [new GuardianUpdate($guardian->ulid, $name, 'Mother', $phone, null, null, true, true)],
    );

    expect(fn () => app(UpdateStudentDetails::class)->handle($this->owner, $student, $update('9876543211')))
        ->toThrow(StudentException::class, 'already belongs to the guardian Sandeep Nair');

    // Pooja is also the mother of a child at the North campus
    $this->makeStudent($this->north1A, 'Meera', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210')]);
    $mainOnly = $this->staffWith(['students.view', 'students.manage'], Scope::campuses([$this->main->ulid]));

    expect(fn () => app(UpdateStudentDetails::class)->handle($mainOnly, $student, $update('9876543210', 'Pooja Kaur Gill')))
        ->toThrow(StudentException::class, 'outside your access');

    app(UpdateStudentDetails::class)->handle($this->owner, $student, $update('9876543210', 'Pooja Kaur Gill'));

    expect($guardian->refresh()->name)->toBe('Pooja Kaur Gill');
});
