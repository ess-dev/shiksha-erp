<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateHoliday;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Models\Holiday;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

afterEach(function () {
    Tenant::forgetCurrent();

    foreach (Tenant::query()->pluck('database_name') as $database) {
        DB::connection('landlord')->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$database]);
        DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }
});

it('keeps one school\'s registers, marks, holidays and class teachers out of another school', function () {
    $this->seed(AccessCatalogSeeder::class);

    $a = app(ProvisionTenant::class)->handle('School A', 'school-a');
    $b = app(ProvisionTenant::class)->handle('School B', 'school-b');

    DB::connection('landlord')->table('tenant_modules')->insert([
        'tenant_id' => $a->id, 'module' => 'attendance', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = app(CreateIdentity::class)->handle('owner@example.com', null, 'password');
    app(SetUpSchoolOwner::class)->handle($a, $owner, 'Owner A');

    $a->execute(function () use ($owner) {
        $year = app(CreateAcademicYear::class)->handle($owner, '2026-27', '2026-04-01', '2027-03-31');
        app(SetCurrentAcademicYear::class)->handle($owner, $year);
        $campus = app(CreateCampus::class)->handle($owner, 'Main', 'main');
        $class = app(CreateSchoolClass::class)->handle($owner, 'Class 1', 1);
        $section = app(CreateSection::class)->handle($owner, $year, $campus, $class, 'A', 40);
        app(UpdateAttendanceCalendar::class)->handle($owner, [1, 2, 3, 4, 5, 6, 7], 7);
        app(CreateHoliday::class)->handle($owner, '2026-11-08', 'Diwali');

        $teacherIdentity = app(CreateIdentity::class)->handle('teacher@example.com', null, 'password');
        $teacher = app(CreateStaffRecord::class)->handle($teacherIdentity->ulid, 'Neha Verma');
        app(SetClassTeacher::class)->handle($owner, $section, $teacher->ulid);

        $student = app(CreateStudent::class)->handle($owner, new NewStudent(
            firstName: 'Aditya', lastName: 'Gill', gender: Gender::Male, bornOn: '2019-06-07', bloodGroup: BloodGroup::OPositive,
            houseKey: null, address: 'Sector 70, Mohali', sectionUlid: $section->ulid,
            guardians: [new NewGuardian('Komal Gill', 'Mother', '9876543210', null, null, true)],
            admittedOn: '2026-04-01',
        ));

        $register = AttendanceRegister::query()->create([
            'ulid' => strtolower((string) Str::ulid()), 'section_id' => Section::query()->firstOrFail()->id,
            'attendance_on' => CalendarDate::today(), 'first_saved_at' => now(), 'last_saved_at' => now(),
        ]);
        AttendanceMark::query()->create(['attendance_register_id' => $register->id, 'student_id' => $student->id, 'status' => 'present']);

        expect(AttendanceRegister::query()->count())->toBe(1)
            ->and(Holiday::query()->count())->toBe(1);
    });

    $b->execute(function () {
        $tenant = DB::connection(config('multitenancy.tenant_database_connection_name'));

        expect(AttendanceRegister::query()->count())->toBe(0)
            ->and(AttendanceMark::query()->count())->toBe(0)
            ->and(Holiday::query()->count())->toBe(0)
            ->and($tenant->table('sections')->whereNotNull('class_teacher_staff_id')->count())->toBe(0)
            ->and($tenant->table('role_assignments')->where('source', 'auto')->count())->toBe(0);
    });
});
