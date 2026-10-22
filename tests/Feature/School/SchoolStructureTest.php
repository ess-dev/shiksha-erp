<?php

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\CreateSubject;
use App\Domain\School\Actions\SetClassSubjects;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Actions\UpdateCampus;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Queries\CampusesQuery;
use App\Domain\School\Queries\SchoolDefinitionsQuery;
use App\Domain\School\Queries\SectionsQuery;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class);

beforeEach(function () {
    $this->seed(AccessCatalogSeeder::class);

    $this->owner = test()->staff();
    app(SetUpSchoolOwner::class)->handle($this->school, $this->owner, 'Meera Nair');
});

/**
 * Two campuses with a Class 1 and a Class 6 section at each, in a current year.
 *
 * @return array{year: AcademicYear, main: Campus, north: Campus, one: SchoolClass, six: SchoolClass}
 */
function structure(): array
{
    $owner = test()->owner;

    $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
    app(SetCurrentAcademicYear::class)->handle($owner, $year);
    $main = app(CreateCampus::class)->handle($owner, 'Main Campus', 'main');
    $north = app(CreateCampus::class)->handle($owner, 'North Campus', 'north');
    $one = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);
    $six = app(CreateSchoolClass::class)->handle($owner, 'Class 6', 6);

    foreach ([$main, $north] as $campus) {
        foreach ([$one, $six] as $class) {
            app(CreateSection::class)->handle($owner, $year, $campus, $class, 'a', 40);
        }
    }

    return compact('year', 'main', 'north', 'one', 'six');
}

/** A staff identity holding a role with structure permissions over a scope. */
function structureViewer(Scope $scope, array $permissions = ['structure.view']): Identity
{
    $identity = test()->staff();
    $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Viewer');
    $role = app(CreateRole::class)->handle(test()->owner, 'Role '.uniqid(), $permissions);
    app(AssignRole::class)->handle(test()->owner, $staff, $role, $scope);

    return $identity;
}

it('sets up a school: campuses, year, classes, sections, subjects', function () {
    $s = structure();

    $maths = app(CreateSubject::class)->handle($this->owner, 'Mathematics', 'mat');
    $english = app(CreateSubject::class)->handle($this->owner, 'English', 'eng');
    app(SetClassSubjects::class)->handle($this->owner, $s['one'], [$maths, $english]);

    expect(Campus::query()->pluck('code')->all())->toEqualCanonicalizing(['MAIN', 'NORTH'])
        ->and(Section::query()->count())->toBe(4)
        ->and(Section::query()->first()->name)->toBe('A')
        ->and($s['one']->subjects()->pluck('code')->all())->toEqualCanonicalizing(['ENG', 'MAT'])
        ->and(AcademicYear::query()->where('is_current', true)->sole()->name)->toBe('2026-27');
});

it('audits every structure change', function () {
    structure();

    $actions = AuditEvent::query()->orderBy('id')->pluck('action')->unique()->values()->all();

    expect($actions)->toContain(
        'structure.academic_year_created',
        'structure.academic_year_made_current',
        'structure.campus_created',
        'structure.class_created',
        'structure.section_created',
    );
});

it('keeps academic years from overlapping, even by a day', function (string $starts, string $ends, bool $allowed) {
    app(CreateAcademicYear::class)->handle($this->owner, '2026-27', '2026-04-01', '2027-03-31');

    $create = fn () => app(CreateAcademicYear::class)->handle($this->owner, 'Another', $starts, $ends);

    if ($allowed) {
        expect($create()->name)->toBe('Another');
    } else {
        expect($create)->toThrow(StructureException::class, 'overlaps');
    }
})->with([
    'starts the day after' => ['2027-04-01', '2028-03-31', true],
    'ends the day before' => ['2025-04-01', '2026-03-31', true],
    'starts on the last day' => ['2027-03-31', '2028-03-30', false],
    'ends on the first day' => ['2025-04-02', '2026-04-01', false],
    'inside' => ['2026-06-01', '2026-09-30', false],
    'around' => ['2025-01-01', '2028-01-01', false],
]);

it('rejects impossible or inverted dates', function () {
    $create = fn (string $a, string $b) => fn () => app(CreateAcademicYear::class)->handle($this->owner, 'Y', $a, $b);

    expect($create('2026-02-30', '2027-03-31'))->toThrow(InvalidArgumentException::class)
        ->and($create('2026-4-1', '2027-03-31'))->toThrow(InvalidArgumentException::class)
        ->and($create('2027-03-31', '2026-04-01'))->toThrow(StructureException::class, 'must end after')
        ->and($create('2026-04-01', '2026-04-01'))->toThrow(StructureException::class, 'must end after');
});

it('accepts leap-day boundaries and stores dates unshifted', function () {
    $year = app(CreateAcademicYear::class)->handle($this->owner, 'Leap', '2027-03-01', '2028-02-29');

    expect($year->fresh()->starts_on)->toBe('2027-03-01')
        ->and($year->fresh()->ends_on)->toBe('2028-02-29');

    expect(fn () => app(CreateAcademicYear::class)->handle($this->owner, 'Bad leap', '2028-03-01', '2029-02-29'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps exactly one current academic year', function () {
    $a = app(CreateAcademicYear::class)->handle($this->owner, '2025-26', '2025-04-01', '2026-03-31');
    $b = app(CreateAcademicYear::class)->handle($this->owner, '2026-27', '2026-04-01', '2027-03-31');

    app(SetCurrentAcademicYear::class)->handle($this->owner, $a);
    app(SetCurrentAcademicYear::class)->handle($this->owner, $b);
    app(SetCurrentAcademicYear::class)->handle($this->owner, $b);

    expect(AcademicYear::query()->where('is_current', true)->pluck('name')->all())->toBe(['2026-27'])
        ->and(fn () => DB::connection('tenant')->table('academic_years')->update(['is_current' => true]))
        ->toThrow(Exception::class);
});

it('rejects duplicate campuses, classes, sections and subjects', function () {
    $s = structure();

    expect(fn () => app(CreateCampus::class)->handle($this->owner, 'Other', 'Main'))->toThrow(StructureException::class)
        ->and(fn () => app(CreateSchoolClass::class)->handle($this->owner, 'Class 1', 9))->toThrow(StructureException::class)
        ->and(fn () => app(CreateSchoolClass::class)->handle($this->owner, 'Class 9', 1))->toThrow(StructureException::class)
        ->and(fn () => app(CreateSection::class)->handle($this->owner, $s['year'], $s['main'], $s['one'], 'A'))->toThrow(StructureException::class)
        ->and(fn () => app(CreateSchoolClass::class)->handle($this->owner, 'Class 99', 99))->toThrow(InvalidArgumentException::class);

    app(CreateSubject::class)->handle($this->owner, 'Hindi', 'HIN');
    expect(fn () => app(CreateSubject::class)->handle($this->owner, 'Hindi 2', 'hin'))->toThrow(StructureException::class);
});

it('adds sections only to an active campus', function () {
    $s = structure();

    app(UpdateCampus::class)->handle($this->owner, $s['north'], 'North Campus', 'inactive');

    expect(fn () => app(CreateSection::class)->handle($this->owner, $s['year'], $s['north'], $s['one'], 'B'))
        ->toThrow(StructureException::class, 'active campus');
});

it('only lets people with structure.manage change the structure', function () {
    $s = structure();
    $viewer = structureViewer(Scope::school());

    expect(fn () => app(CreateCampus::class)->handle($viewer, 'X', 'X'))->toThrow(StructureException::class, 'not allowed')
        ->and(fn () => app(CreateSection::class)->handle($viewer, $s['year'], $s['main'], $s['one'], 'B'))->toThrow(StructureException::class)
        ->and(fn () => app(CreateAcademicYear::class)->handle($this->staff(), 'Y', '2030-04-01', '2031-03-31'))->toThrow(StructureException::class);
});

it('limits section management to the manager\'s own scope', function () {
    $s = structure();
    $campusManager = structureViewer(Scope::campuses([$s['main']->ulid]), ['structure.manage']);
    $wingManager = structureViewer(Scope::wing([$s['main']->ulid, $s['north']->ulid], 1, 5), ['structure.manage']);

    expect(app(CreateSection::class)->handle($campusManager, $s['year'], $s['main'], $s['one'], 'B')->name)->toBe('B')
        ->and(fn () => app(CreateSection::class)->handle($campusManager, $s['year'], $s['north'], $s['one'], 'B'))
        ->toThrow(StructureException::class, 'not allowed');

    expect(app(CreateSection::class)->handle($wingManager, $s['year'], $s['north'], $s['one'], 'C')->name)->toBe('C')
        ->and(fn () => app(CreateSection::class)->handle($wingManager, $s['year'], $s['north'], $s['six'], 'C'))
        ->toThrow(StructureException::class, 'not allowed')
        // Campus-wide or school-wide changes are not for a campus or wing manager.
        ->and(fn () => app(CreateCampus::class)->handle($campusManager, 'Z', 'Z'))->toThrow(StructureException::class);
});

it('lists only the sections inside the viewer\'s scope', function () {
    $s = structure();
    $names = fn (Identity $who, array $filters = []) => collect(app(SectionsQuery::class)->paginate($who, ...$filters)->items())
        ->map(fn ($section) => $section->jsonSerialize()['campus']['name'].' / '.$section->jsonSerialize()['class']['name'])
        ->sort()->values()->all();

    $everything = structureViewer(Scope::school());
    $main = structureViewer(Scope::campuses([$s['main']->ulid]));
    $wing = structureViewer(Scope::wing([$s['north']->ulid], 1, 5));
    $sectionOnly = structureViewer(Scope::sections([Section::query()->where('campus_id', $s['north']->id)->where('school_class_id', $s['six']->id)->value('ulid')]));
    $nothing = $this->staff();

    expect($names($everything))->toHaveCount(4)
        ->and($names($main))->toBe(['Main Campus / Class 1', 'Main Campus / Class 6'])
        ->and($names($wing))->toBe(['North Campus / Class 1'])
        ->and($names($sectionOnly))->toBe(['North Campus / Class 6'])
        ->and($names($nothing))->toBe([])
        ->and($names($everything, ['classUlid' => $s['six']->ulid]))->toHaveCount(2)
        ->and($names($everything, ['campusUlid' => $s['north']->ulid, 'classUlid' => $s['one']->ulid]))->toBe(['North Campus / Class 1']);
});

it('shows each viewer only the campuses their scope reaches', function () {
    $s = structure();
    $campuses = fn (Identity $who) => collect(app(CampusesQuery::class)->get($who))->map(fn ($c) => $c->jsonSerialize()['code'])->sort()->values()->all();

    $sectionUlid = Section::query()->where('campus_id', $s['north']->id)->value('ulid');

    expect($campuses(structureViewer(Scope::school())))->toBe(['MAIN', 'NORTH'])
        ->and($campuses(structureViewer(Scope::campuses([$s['main']->ulid]))))->toBe(['MAIN'])
        ->and($campuses(structureViewer(Scope::wing([$s['north']->ulid], 1, 5))))->toBe(['NORTH'])
        ->and($campuses(structureViewer(Scope::sections([$sectionUlid]))))->toBe(['NORTH'])
        ->and($campuses($this->staff()))->toBe([]);
});

it('shares school-wide definitions with anyone who can view the structure', function () {
    structure();
    $viewer = structureViewer(Scope::campuses(['anything']));
    $query = app(SchoolDefinitionsQuery::class);

    expect(collect($query->classes($viewer))->map(fn ($c) => $c->jsonSerialize()['level'])->all())->toBe([1, 6])
        ->and($query->currentAcademicYear($viewer)?->jsonSerialize()['name'])->toBe('2026-27')
        ->and($query->academicYears($viewer))->toHaveCount(1)
        ->and($query->classes($this->staff()))->toBe([])
        ->and($query->currentAcademicYear($this->staff()))->toBeNull();
});

it('never exposes internal ids in structure data', function () {
    structure();

    $section = app(SectionsQuery::class)->paginate($this->owner)->items()[0]->jsonSerialize();

    expect($section)->toHaveKey('ulid')->and($section)->not->toHaveKey('id')
        ->and(json_encode($section))->not->toContain('"id"');
});

it('feeds the section location to scope checks', function () {
    $s = structure();
    $section = Section::query()->where('campus_id', $s['north']->id)->where('school_class_id', $s['six']->id)->sole();
    $target = $section->scopeTarget();

    expect($target->campusUlid)->toBe($s['north']->ulid)
        ->and($target->classLevel)->toBe(6)
        ->and($target->sectionUlid)->toBe($section->ulid)
        ->and(Scope::wing([$s['north']->ulid], 1, 5)->covers($target))->toBeFalse()
        ->and(Scope::wing([$s['north']->ulid], 6, 8)->covers($target))->toBeTrue()
        ->and(Scope::sections([$section->ulid])->covers($target))->toBeTrue();
});
