<?php

use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateHouse;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Actions\UpdateSchoolSettings;
use App\Domain\School\Models\House;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

afterEach(function () {
    Tenant::forgetCurrent();

    foreach (Tenant::query()->pluck('database_name') as $database) {
        DB::connection('landlord')->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$database]);
        DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }
});

it('keeps one school\'s students, guardians, houses and settings out of another school', function () {
    $this->seed(AccessCatalogSeeder::class);

    $a = app(ProvisionTenant::class)->handle('School A', 'school-a');
    $b = app(ProvisionTenant::class)->handle('School B', 'school-b');

    $owner = app(CreateIdentity::class)->handle('owner@example.com', null, 'password');
    app(SetUpSchoolOwner::class)->handle($a, $owner, 'Owner A');

    $a->execute(function () use ($owner) {
        $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
        app(SetCurrentAcademicYear::class)->handle($owner, $year);
        $campus = app(CreateCampus::class)->handle($owner, 'Main', 'main');
        $class = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);
        $section = app(CreateSection::class)->handle($owner, $year, $campus, $class, 'A', 40);
        app(CreateHouse::class)->handle($owner, 'Raman');
        app(UpdateSchoolSettings::class)->handle($owner, 'ALPHA', 'ATC');

        app(CreateStudent::class)->handle($owner, new NewStudent(
            'Aditya', 'Gill', Gender::Male, '2019-06-07', BloodGroup::Unknown, null, 'Mohali', $section->ulid,
            [new NewGuardian('Pooja Gill', 'Mother', '9876543210')],
        ));

        expect(Student::query()->count())->toBe(1)
            ->and(Guardian::query()->count())->toBe(1)
            ->and(House::query()->count())->toBe(1)
            ->and(app(SchoolSettingsQuery::class)->admissionPrefix())->toBe('ALPHA');
    });

    $b->execute(function () {
        expect(Student::query()->count())->toBe(0)
            ->and(Guardian::query()->count())->toBe(0)
            ->and(House::query()->count())->toBe(0)
            ->and(DB::connection(config('multitenancy.tenant_database_connection_name'))->table('number_sequences')->count())->toBe(0)
            ->and(app(SchoolSettingsQuery::class)->admissionPrefix())->toBe('ADM');
    });
});

it('lets the same guardian phone exist in two schools, since one phone is one guardian per school', function () {
    $this->seed(AccessCatalogSeeder::class);

    $schools = [app(ProvisionTenant::class)->handle('School A', 'school-a'), app(ProvisionTenant::class)->handle('School B', 'school-b')];
    $names = [];

    foreach ($schools as $index => $school) {
        $owner = app(CreateIdentity::class)->handle("owner{$index}@example.com", null, 'password');
        app(SetUpSchoolOwner::class)->handle($school, $owner, 'Owner');

        $school->execute(function () use ($owner, &$names, $index) {
            $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
            app(SetCurrentAcademicYear::class)->handle($owner, $year);
            $campus = app(CreateCampus::class)->handle($owner, 'Main', 'main');
            $class = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);
            $section = app(CreateSection::class)->handle($owner, $year, $campus, $class, 'A', 40);

            $student = app(CreateStudent::class)->handle($owner, new NewStudent(
                'Child', 'Number'.$index, Gender::Male, '2019-06-07', BloodGroup::Unknown, null, 'Mohali', $section->ulid,
                [new NewGuardian('Same Phone '.$index, 'Mother', '9876543210')],
            ));

            $names[] = $student->guardians()->first()->name;
        });
    }

    expect($names)->toBe(['Same Phone 0', 'Same Phone 1']);
});
