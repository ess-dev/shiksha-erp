<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateHouse;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\House;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Actions\IssueTransferCertificate;
use App\Domain\Students\Actions\LinkSiblings;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\Models\Student;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional students for the two demo schools, created through the real
 * Actions by each school's demo principal: houses, a few students per
 * section, sibling families (one across campuses where there are two), one
 * staff-linked pair and one transferred student. Deterministic and safe to
 * re-run (a school that already has students is left alone). Local only.
 * illustrative — verify: names, ages and counts are placeholders.
 */
class DemoStudentsSeeder extends Seeder
{
    private const SCHOOLS = [
        'greenfield' => ['principal' => 'principal@greenfield.test', 'prefix' => 'GPS', 'per_section' => 6, 'phone_base' => 9876510000],
        'riverside' => ['principal' => 'principal@riverside.test', 'prefix' => 'RVS', 'per_section' => 4, 'phone_base' => 9876520000],
    ];

    private const HOUSES = ['Raman', 'Tagore', 'Vivekananda', 'Gandhi'];

    private const FIRST_M = ['Aditya', 'Gurnoor', 'Jasleen', 'Rohan', 'Vihaan', 'Krish', 'Siddharth', 'Pranav', 'Advik', 'Reyansh'];

    private const FIRST_F = ['Mahira', 'Nikhil', 'Prisha', 'Aadhya', 'Anika', 'Diya', 'Saanvi', 'Riya', 'Nandini', 'Ira'];

    private const LAST = ['Gill', 'Singh', 'Arora', 'Malhotra', 'Kaur', 'Rao', 'Sandhu', 'Khanna', 'Joshi', 'Saini', 'Bansal', 'Kapoor', 'Iyer', 'Nair', 'Tiwari', 'Bhatia'];

    private const MOTHERS = ['Priya', 'Neetu', 'Rupinder', 'Pooja', 'Shalini', 'Navneet', 'Anjali', 'Komal'];

    private const BLOOD = [BloodGroup::OPositive, BloodGroup::APositive, BloodGroup::BPositive, BloodGroup::ABPositive, BloodGroup::Unknown];

    private int $phoneCounter = 0;

    public function run(CreateHouse $createHouse, CreateStudent $createStudent, LinkSiblings $linkSiblings, IssueTransferCertificate $issueTc): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo students must never be seeded in production.');
        }

        foreach (self::SCHOOLS as $subdomain => $config) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();
            $principal = Identity::query()->where('email', $config['principal'])->first();

            if ($tenant === null || $principal === null) {
                continue;
            }

            $tenant->execute(function () use ($config, $principal, $createHouse, $createStudent, $linkSiblings, $issueTc) {
                $year = AcademicYear::query()->where('is_current', true)->first();

                if ($year === null || Student::query()->exists()) {
                    return;
                }

                $this->phoneCounter = 0;

                if (! House::query()->exists()) {
                    foreach (self::HOUSES as $house) {
                        $createHouse->handle($principal, $house);
                    }
                }

                $houses = array_values(array_map(intval(...), House::query()->where('status', 'active')->orderBy('sort_order')->pluck('id')->all()));
                $sections = Section::query()->with(['schoolClass', 'campus'])->where('academic_year_id', $year->id)
                    ->get()->sortBy(fn (Section $section) => $section->campus->code.sprintf('%03d', $section->schoolClass->level + 10).$section->name)->values();

                $count = 0;
                $made = [];

                foreach ($sections as $section) {
                    for ($n = 0; $n < $config['per_section']; $n++) {
                        $gender = $count % 2 === 0 ? Gender::Male : Gender::Female;
                        $first = ($gender === Gender::Male ? self::FIRST_M : self::FIRST_F)[($count + $n) % 10];
                        $last = self::LAST[($count * 3 + $n) % count(self::LAST)];
                        $made[] = $createStudent->handle($principal, $this->student($section, $first, $last, $gender, $houses, $count, [$this->family($last, $config['phone_base'])]));
                        $count++;
                    }
                }

                $this->siblingFamilies($createStudent, $principal, array_values($sections->all()), $houses, $config['phone_base']);

                // two children with different guardians, linked by staff (both families have portal access)
                if (count($made) > 3) {
                    $linkSiblings->handle($principal, $made[1], $made[2]);
                }

                // a student who has left, so the "Left the school" filter has someone
                if (count($made) > 5) {
                    $issueTc->handle($principal, $made[5], CalendarDate::today(), TransferReason::FamilyRelocating);
                }
            });
        }
    }

    /**
     * One family with two children in different classes (and campuses when the school has two).
     *
     * @param  list<Section>  $sections
     * @param  list<int>  $houses
     */
    private function siblingFamilies(CreateStudent $createStudent, Identity $principal, array $sections, array $houses, int $phoneBase): void
    {
        $byLevel = fn (int $level, ?string $campus = null) => collect($sections)->first(fn (Section $section) => $section->schoolClass->level === $level
            && ($campus === null || $section->campus->code === $campus));

        $campuses = collect($sections)->map(fn (Section $section) => $section->campus->code)->unique()->values();
        $older = $byLevel(5, $campuses->first());
        $younger = $byLevel(2, $campuses->last());

        if ($older === null || $younger === null) {
            return;
        }

        $guardians = [
            new NewGuardian('Priya Sharma', 'Mother', (string) ($phoneBase + 9001), 'priya.sharma@mail.example', 'Chartered accountant', true),
            new NewGuardian('Rohit Sharma', 'Father', (string) ($phoneBase + 9002), 'rohit.sharma@mail.example', 'Business'),
        ];

        $createStudent->handle($principal, $this->student($older, 'Riya', 'Sharma', Gender::Female, $houses, 1, $guardians));
        $createStudent->handle($principal, $this->student($younger, 'Arjun', 'Sharma', Gender::Male, $houses, 2, $guardians));
    }

    /**
     * @param  list<int>  $houses  Active house keys; every fifth student is "Not assigned"
     * @param  list<NewGuardian>  $guardians
     */
    private function student(Section $section, string $first, string $last, Gender $gender, array $houses, int $seed, array $guardians): NewStudent
    {
        $level = $section->schoolClass->level;

        return new NewStudent(
            firstName: $first,
            lastName: $last,
            gender: $gender,
            bornOn: sprintf('%04d-%02d-%02d', 2026 - ($level + 5) - ($seed % 2), 1 + $seed % 12, 1 + $seed % 27),
            bloodGroup: self::BLOOD[$seed % count(self::BLOOD)],
            houseKey: $houses === [] || $seed % 5 === 4 ? null : $houses[$seed % count($houses)],
            address: 'Sector '.(60 + $seed % 20).', Mohali',
            sectionUlid: $section->ulid,
            guardians: $guardians,
            admittedOn: '2026-04-03',
        );
    }

    private function family(string $last, int $phoneBase): NewGuardian
    {
        $n = ++$this->phoneCounter;

        return new NewGuardian(
            self::MOTHERS[$n % count(self::MOTHERS)].' '.$last.($n > 90 ? ' '.$n : ''),
            'Mother',
            (string) ($phoneBase + $n),
            null,
            null,
            true,
        );
    }
}
