<?php

use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Admissions\Actions\AddEnquiry;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetCurrentAcademicYear;
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

it('keeps one school\'s admission applications out of another school', function () {
    $this->seed(AccessCatalogSeeder::class);

    $a = app(ProvisionTenant::class)->handle('School A', 'school-a');
    $b = app(ProvisionTenant::class)->handle('School B', 'school-b');

    DB::connection('landlord')->table('tenant_modules')->insert([
        'tenant_id' => $a->id, 'module' => 'admissions', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = app(CreateIdentity::class)->handle('owner@example.com', null, 'password');
    app(SetUpSchoolOwner::class)->handle($a, $owner, 'Owner A');

    $a->execute(function () use ($owner) {
        $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
        app(SetCurrentAcademicYear::class)->handle($owner, $year);
        $campus = app(CreateCampus::class)->handle($owner, 'Main', 'main');
        $class = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);
        app(CreateSection::class)->handle($owner, $year, $campus, $class, 'A', 40);

        app(AddEnquiry::class)->handle($owner, new NewEnquiry(
            firstName: 'Advika', lastName: 'Bansal', gender: 'female', bornOn: null,
            classUlid: $class->ulid, campusUlid: $campus->ulid, parentName: 'Komal Bansal', parentRelation: 'Mother',
            phone: '9876543210', source: AdmissionSource::Website,
        ));

        expect(AdmissionApplication::query()->count())->toBe(1);
    });

    $b->execute(function () {
        expect(AdmissionApplication::query()->count())->toBe(0)
            ->and(DB::connection(config('multitenancy.tenant_database_connection_name'))->table('admission_documents')->count())->toBe(0);
    });
});
