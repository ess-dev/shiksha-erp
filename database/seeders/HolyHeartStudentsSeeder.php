<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Section;
use App\Domain\Students\Actions\CreateStudent;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Models\Student;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional students for the Holy Heart school (`holy-heart`), created through
 * CreateStudent by its principal: two students in each section of Classes 1
 * and 2 of the current year, one guardian per family. Deterministic and safe
 * to re-run (a school that already has students is left alone).
 * illustrative — verify: names, ages and counts are placeholders.
 */
class HolyHeartStudentsSeeder extends Seeder
{
    private const SUBDOMAIN = 'holy-heart';

    private const PRINCIPAL = 'mary-holy-heart@yopmail.com';

    private const PHONE_BASE = 9876530000;

    private const LEVELS = [1, 2];

    private const PER_SECTION = 2;

    private const FIRST_M = ['Aarav', 'Vivaan', 'Kabir', 'Ayaan', 'Dhruv', 'Shaurya'];

    private const FIRST_F = ['Myra', 'Ananya', 'Kiara', 'Sara', 'Tara', 'Navya'];

    private const LAST = ['Verma', 'Chopra', 'Dhillon', 'Mehta', 'Grewal', 'Pillai'];

    public function run(CreateStudent $createStudent): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Holy Heart demo students must never be seeded in production.');
        }

        $tenant = Tenant::query()->where('subdomain', self::SUBDOMAIN)->first();
        $principal = Identity::query()->where('email', self::PRINCIPAL)->first();

        if ($tenant === null || $principal === null) {
            return;
        }

        $tenant->execute(function () use ($createStudent, $principal) {
            $year = AcademicYear::query()->where('is_current', true)->first();

            if ($year === null || Student::query()->exists()) {
                return;
            }

            $sections = Section::query()->with(['schoolClass', 'campus'])
                ->where('academic_year_id', $year->id)
                ->where('status', 'active')
                ->get()
                ->filter(fn (Section $section) => in_array($section->schoolClass->level, self::LEVELS, true))
                ->sortBy(fn (Section $section) => sprintf('%03d', $section->schoolClass->level).$section->name)
                ->values();

            $count = 0;

            foreach ($sections as $section) {
                for ($n = 0; $n < self::PER_SECTION; $n++) {
                    $gender = $count % 2 === 0 ? Gender::Male : Gender::Female;
                    $first = ($gender === Gender::Male ? self::FIRST_M : self::FIRST_F)[$count % 6];
                    $last = self::LAST[($count * 5) % count(self::LAST)];

                    $createStudent->handle($principal, $this->student($section, $first, $last, $gender, $count), 'students.manage');
                    $count++;
                }
            }
        });
    }

    private function student(Section $section, string $first, string $last, Gender $gender, int $seed): NewStudent
    {
        $level = $section->schoolClass->level;

        return new NewStudent(
            firstName: $first,
            lastName: $last,
            gender: $gender,
            bornOn: sprintf('%04d-%02d-%02d', 2026 - ($level + 5), 1 + $seed % 12, 1 + $seed % 27),
            bloodGroup: BloodGroup::OPositive,
            houseKey: null,
            address: 'Sector '.(20 + $seed % 10).', Mumbai',
            sectionUlid: $section->ulid,
            guardians: [
                new NewGuardian(
                    $last.' Parent '.($seed + 1),
                    'Mother',
                    (string) (self::PHONE_BASE + $seed + 1),
                    null,
                    null,
                    true,
                ),
            ],
            admittedOn: '2026-04-03',
        );
    }
}
