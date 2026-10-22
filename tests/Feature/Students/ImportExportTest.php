<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Domain\Students\SiblingFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

const IMPORT_HEADER = 'First name,Last name,Gender,Date of birth,Blood group,House,Address,Campus,Class,Section,Admission no,Guardian name,Relation,Mobile,Email,Second guardian name,Second guardian relation,Second guardian mobile';

/** @param list<string> $rows */
function csvFile(array $rows, string $header = IMPORT_HEADER, string $name = 'students.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, implode("\n", [$header, ...$rows])."\n");
}

it('offers the template to someone who may import and to nobody else', function () {
    $response = $this->actingAs($this->owner)->get(route('students.import.template'))->assertOk();

    expect($response->streamedContent())->toContain('Gender')->toContain('Blood group')->toContain('Second guardian mobile');

    $this->actingAs($this->staffWith(['students.view']))->get(route('students.import.template'))->assertForbidden();
});

it('previews a file row by row without saving anything', function () {
    $file = csvFile([
        'Aarohi,Bedi,F,2019-02-11,O+,Raman,"Sector 70, Mohali",Main Campus,1,A,,Kunal Bedi,Father,9876501234,kunal@mail.example,Mansi Bedi,Mother,9876501235',
        'Vivaan,Bedi,Male,19/07/2018,A-,Tagore,"Sector 70, Mohali",Main,1,B,,Kunal Bedi,Father,9876501234,,,,',
        'Tara,Menon,F,2019-11-30,Unknown,Not assigned,Phase 9,,2,A,,Lakshmi Menon,Mother,98123,,,,',
        'Kabir,Anand,M,2019-05-05,B+,Nowhere,Phase 1,Main,7,C,,Raghav Anand,Father,9898989898,,,,',
        ',Gupta,X,not-a-date,Z+,,,,,,,N,,,,,,',
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(route('students.import.preview'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('file_name', 'students.csv')
        ->assertJsonPath('missing_columns', [])
        ->assertJsonPath('ready', 2)
        ->assertJsonPath('need_fixing', 3)
        ->assertJsonPath('rows.0.status', 'ready')
        ->assertJsonPath('rows.0.class_label', 'Class 1-A')
        ->assertJsonPath('rows.1.status', 'warning');

    $rows = collect($response->json('rows'));

    expect($rows[1]['warnings'])->toContain('Same family as row 2')
        ->and($rows[2]['status'])->toBe('fix')
        ->and($rows[2]['errors'])->toContain('Mobile must be a 10-digit Indian number')
        ->and($rows[3]['errors'])->toContain('House Nowhere does not exist', 'Class 7-C does not exist on Main')
        ->and($rows[4]['errors'])->toContain('First name is missing', 'Gender must be Male, Female or Other', 'Blood group must be like O+ or AB-, or Unknown', 'Address is missing')
        ->and(Student::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'students.imported')->count())->toBe(0);
});

it('imports the ready rows, links siblings by guardian, and skips the rest', function () {
    $file = csvFile([
        'Aarohi,Bedi,F,2019-02-11,O+,Raman,"Sector 70, Mohali",Main Campus,1,A,,Kunal Bedi,Father,9876501234,kunal@mail.example,Mansi Bedi,Mother,9876501235',
        'Vivaan,Bedi,Male,19/07/2018,A-,Tagore,"Sector 70, Mohali",Main,1,B,GPS/2026/1201,Kunal Bedi,Father,+91 98765 01234,,,,',
        'Tara,Menon,F,2019-11-30,Unknown,Not assigned,Phase 9,,2,A,,Lakshmi Menon,Mother,98123,,,,',
    ]);

    $this->actingAs($this->owner)
        ->post(route('students.import.store'), ['file' => $file])
        ->assertRedirect(route('students.index'))
        ->assertSessionHasNoErrors();

    $aarohi = Student::query()->where('first_name', 'Aarohi')->sole();
    $vivaan = Student::query()->where('first_name', 'Vivaan')->sole();

    expect(Student::query()->count())->toBe(2)
        ->and($aarohi->admission_number)->toBe('ADM/2026/0001')
        ->and($vivaan->admission_number)->toBe('GPS/2026/1201')
        ->and($aarohi->house_id)->toBe($this->raman->id)
        ->and($aarohi->status)->toBe(StudentStatus::Enrolled)
        ->and(Guardian::query()->count())->toBe(2)
        ->and(app(SiblingFinder::class)->idsFor($aarohi->id))->toBe([$vivaan->id])
        ->and(Enrollment::query()->where('student_id', $vivaan->id)->sole()->roll_number)->toBe(1);

    $event = AuditEvent::query()->where('action', 'students.imported')->sole();

    expect($event->after)->toMatchArray(['file' => 'students.csv', 'created' => 2, 'skipped' => 1, 'families_linked' => 1])
        ->and(AuditEvent::query()->where('action', 'students.student_created')->count())->toBe(2);

    $this->get(route('students.index'))->assertInertia(fn (Assert $page) => $page->has('students.rows', 2));
});

it('flashes the result so the page can show what was imported', function () {
    $this->actingAs($this->owner)
        ->post(route('students.import.store'), ['file' => csvFile(['Aarohi,Bedi,F,2019-02-11,O+,Raman,Mohali,Main,1,A,,Kunal Bedi,Father,9876501234,,,,'])])
        ->assertRedirect(route('students.index'));

    $this->get(route('students.index'))->assertOk();
});

it('refuses a mobile that belongs to a differently named guardian, in the file or already on file', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210')]);

    $response = $this->actingAs($this->owner)->postJson(route('students.import.preview'), ['file' => csvFile([
        'Riya,Nair,F,2019-03-03,O+,Raman,Mohali,Main,1,A,,Sandeep Nair,Father,9876543210,,,,',
        'Kabir,Anand,M,2019-05-05,O+,Raman,Mohali,Main,1,A,,Raghav Anand,Father,9898989898,,,,',
        'Zoya,Anand,F,2019-06-06,O+,Raman,Mohali,Main,1,A,,Renu Anand,Mother,9898989898,,,,',
        'Meera,Gill,F,2019-07-07,O+,Raman,Mohali,Main,1,A,,Pooja Gill,Mother,9876543210,,,,',
    ])])->assertOk();

    $rows = collect($response->json('rows'));

    expect($rows[0]['errors'][0])->toContain('already belongs to Pooja Gill')
        ->and($rows[1]['status'])->toBe('ready')
        ->and($rows[2]['errors'][0])->toContain('different guardian in row 3')
        ->and($rows[3]['status'])->toBe('warning')
        ->and($rows[3]['warnings'][0])->toContain('Joins the existing family of Pooja Gill');
});

it('catches repeats: admission numbers, the same child twice, a child already enrolled, and two guardians on one mobile', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill');

    $response = $this->actingAs($this->owner)->postJson(route('students.import.preview'), ['file' => csvFile([
        'Aditya,Gill,M,2019-06-07,O+,Raman,Mohali,Main,1,A,,Pooja Gill,Mother,9876500001,,,,',
        'Riya,Nair,F,2019-03-03,O+,Raman,Mohali,Main,1,A,GPS/1,Sandeep Nair,Father,9876500002,,,,',
        'Riya,Nair,F,2019-03-03,O+,Raman,Mohali,Main,1,A,gps/1,Sandeep Nair,Father,9876500002,,,,',
        'Asha,Rao,F,2019-04-04,O+,Raman,Mohali,Main,1,A,,Anil Rao,Father,9876500003,,Sita Rao,Mother,9876500003',
    ])])->assertOk();

    $rows = collect($response->json('rows'));

    expect($rows[0]['errors'])->toContain('Already a student here (same name and date of birth)')
        ->and($rows[1]['status'])->toBe('ready')
        ->and($rows[2]['errors'])->toContain('Admission no repeats row 3', 'Same student as row 3')
        ->and($rows[3]['errors'])->toContain('The two guardians need different mobile numbers');
});

it('flags missing columns and unreadable files', function () {
    $this->actingAs($this->owner)
        ->postJson(route('students.import.preview'), ['file' => csvFile(['A,B'], 'First name,Last name')])
        ->assertOk()
        ->assertJsonPath('missing_columns.0', 'Gender');

    $this->postJson(route('students.import.preview'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', IMPORT_HEADER."\n")])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->postJson(route('students.import.preview'), ['file' => UploadedFile::fake()->create('students.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->post(route('students.import.store'), ['file' => csvFile(['A,B'], 'First name,Last name')])->assertSessionHasErrors('student');
});

it('limits an import to 1,000 rows and 2 MB', function () {
    $row = 'Aarohi,Bedi,F,2019-02-11,O+,Raman,Mohali,Main,1,A,,Kunal Bedi,Father,9876501234,,,,';

    $this->actingAs($this->owner)
        ->postJson(route('students.import.preview'), ['file' => csvFile(array_fill(0, 1001, $row))])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->postJson(route('students.import.preview'), ['file' => UploadedFile::fake()->createWithContent('big.csv', str_repeat('x', 2 * 1024 * 1024 + 10))])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('keeps an import to the sections the person may import into', function () {
    $coordinator = $this->staffWith(['students.view', 'students.import'], Scope::wing([$this->main->ulid], 1, 1));

    $rows = collect($this->actingAs($coordinator)->postJson(route('students.import.preview'), ['file' => csvFile([
        'Aarohi,Bedi,F,2019-02-11,O+,Raman,Mohali,Main,1,A,,Kunal Bedi,Father,9876501234,,,,',
        'Vivaan,Bedi,M,2018-07-19,O+,Raman,Mohali,North,1,A,,Kunal Bedi,Father,9876501234,,,,',
        'Tara,Menon,F,2018-11-30,O+,Raman,Mohali,Main,2,A,,Lakshmi Menon,Mother,9812345670,,,,',
    ])])->assertOk()->json('rows'));

    expect($rows[0]['status'])->toBe('ready')
        ->and($rows[1]['errors'])->toContain('Class 1-A is outside your access')
        ->and($rows[2]['errors'])->toContain('Class 2-A is outside your access');

    $this->post(route('students.import.store'), ['file' => csvFile([
        'Aarohi,Bedi,F,2019-02-11,O+,Raman,Mohali,Main,1,A,,Kunal Bedi,Father,9876501234,,,,',
        'Vivaan,Bedi,M,2018-07-19,O+,Raman,Mohali,North,1,A,,Kunal Bedi,Father,9876501234,,,,',
    ])])->assertSessionHasNoErrors();

    expect(Student::query()->pluck('first_name')->all())->toBe(['Aarohi']);
});

it('refuses import to people without students.import', function () {
    $viewer = $this->staffWith(['students.view', 'students.manage']);

    $this->actingAs($viewer)->postJson(route('students.import.preview'), ['file' => csvFile([])])->assertForbidden();
    $this->post(route('students.import.store'), ['file' => csvFile([])])->assertForbidden();
});

it('warns about a section going over its capacity, without blocking', function () {
    $rows = collect($this->actingAs($this->owner)->postJson(route('students.import.preview'), ['file' => csvFile([
        'One,Child,M,2019-02-11,O+,Raman,Mohali,Main,1,B,,Parent One,Father,9876501001,,,,',
        'Two,Child,M,2019-02-12,O+,Raman,Mohali,Main,1,B,,Parent Two,Father,9876501002,,,,',
        'Three,Child,M,2019-02-13,O+,Raman,Mohali,Main,1,B,,Parent Three,Father,9876501003,,,,',
    ])])->json('rows'));

    expect($rows[1]['status'])->toBe('ready')
        ->and($rows[2]['status'])->toBe('warning')
        ->and($rows[2]['warnings'])->toContain('Class 1-B goes over its capacity of 2');
});

it('exports students with full phone numbers, audited, for people with students.export only', function () {
    $this->makeStudent($this->main1A, 'Aditya', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', '9876543210', 'pooja@mail.example', null, true)]);
    $this->makeStudent($this->north1A, 'Far', 'Away');

    $response = $this->actingAs($this->owner)->get(route('students.export'))->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Name,Class,Roll,Campus')
        ->and($csv)->toContain('Aditya Gill')->toContain('Class 1-A')
        ->and($csv)->toContain('919876543210')
        ->and($csv)->not->toContain('+919876543210')
        ->and($csv)->toContain('Far Away');

    $event = AuditEvent::query()->where('action', 'students.exported')->sole();

    expect($event->after['students'])->toBe(2)->and(json_encode($event->after))->not->toContain('9876543210');

    $this->get(route('students.export', ['class' => $this->main1A->ulid]))->assertOk();
    expect(AuditEvent::query()->where('action', 'students.exported')->latest('id')->first()->after['students'])->toBe(1);

    $this->actingAs($this->staffWith(['students.view', 'students.view_sensitive']))->get(route('students.export'))->assertForbidden();
});

it('keeps spreadsheet formulas out of an export and limits it to the exporter\'s scope', function () {
    $this->makeStudent($this->main1A, '=CMD()', 'Gill');
    $this->makeStudent($this->north1A, 'Far', 'Away');

    $mainOnly = $this->staffWith(['students.view', 'students.export'], Scope::campuses([$this->main->ulid]));
    $csv = $this->actingAs($mainOnly)->get(route('students.export'))->assertOk()->streamedContent();

    expect($csv)->toContain("'=CMD() Gill")->and($csv)->not->toContain('Far Away');
});
