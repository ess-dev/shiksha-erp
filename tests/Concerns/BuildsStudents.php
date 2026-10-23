<?php

namespace Tests\Concerns;

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Data\Scope;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateHouse;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\House;
use App\Domain\School\Models\Section;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Models\Student;
use Database\Seeders\AccessCatalogSeeder;

/**
 * A small fictional school for student tests: an owner, two campuses, Classes
 * 1 and 2, four sections and two houses.
 */
trait BuildsStudents
{
    public Identity $owner;

    public Campus $main;

    public Campus $north;

    public Section $main1A;

    public Section $main1B;

    public Section $north1A;

    public Section $main2A;

    public House $raman;

    public House $tagore;

    private int $phoneCounter = 0;

    protected function setUpStudentSchool(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $this->owner = $this->staff(twoFactor: true);
        app(SetUpSchoolOwner::class)->handle($this->school, $this->owner, 'Meera Nair');

        $year = app(CreateAcademicYear::class)->handle($this->owner, '2026-27', '2026-04-01', '2027-03-31');
        app(SetCurrentAcademicYear::class)->handle($this->owner, $year);
        $this->main = app(CreateCampus::class)->handle($this->owner, 'Main Campus', 'main');
        $this->north = app(CreateCampus::class)->handle($this->owner, 'North Campus', 'north');
        $one = app(CreateSchoolClass::class)->handle($this->owner, 'Class 1', 1);
        $two = app(CreateSchoolClass::class)->handle($this->owner, 'Class 2', 2);

        $this->main1A = app(CreateSection::class)->handle($this->owner, $year, $this->main, $one, 'A', 40);
        $this->main1B = app(CreateSection::class)->handle($this->owner, $year, $this->main, $one, 'B', 2);
        $this->north1A = app(CreateSection::class)->handle($this->owner, $year, $this->north, $one, 'A', 40);
        $this->main2A = app(CreateSection::class)->handle($this->owner, $year, $this->main, $two, 'A', 40);

        $this->raman = app(CreateHouse::class)->handle($this->owner, 'Raman');
        $this->tagore = app(CreateHouse::class)->handle($this->owner, 'Tagore');
    }

    /**
     * A new fictional phone number, different every call.
     */
    protected function nextPhone(): string
    {
        return '98765'.str_pad((string) (10000 + ++$this->phoneCounter), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Enrols a student (as the owner) with one guardian of their own unless guardians are given.
     *
     * @param  list<NewGuardian>|null  $guardians
     */
    protected function makeStudent(Section $section, string $first = 'Aditya', string $last = 'Gill', ?array $guardians = null, ?Identity $actor = null): Student
    {
        return app(CreateStudent::class)->handle($actor ?? $this->owner, new NewStudent(
            firstName: $first,
            lastName: $last,
            gender: Gender::Male,
            bornOn: '2019-06-07',
            bloodGroup: BloodGroup::OPositive,
            houseKey: $this->raman->id,
            address: 'Sector 70, Mohali',
            sectionUlid: $section->ulid,
            guardians: $guardians ?? [new NewGuardian("{$first} Parent {$last}", 'Mother', $this->nextPhone(), "{$first}@mail.example", 'Doctor', true)],
        ));
    }

    /**
     * A staff identity with one role holding the permissions over a scope.
     *
     * @param  list<string>  $permissions
     */
    protected function staffWith(array $permissions, ?Scope $scope = null): Identity
    {
        $identity = $this->staff();
        $staff = app(CreateStaffRecord::class)->handle($identity->ulid, 'Staff '.uniqid());
        $role = app(CreateRole::class)->handle($this->owner, 'Role '.uniqid(), $permissions, null, true);
        app(AssignRole::class)->handle($this->owner, $staff, $role, $scope ?? Scope::school());

        return $identity;
    }
}
