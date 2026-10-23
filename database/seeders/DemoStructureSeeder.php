<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateAcademicYear;
use App\Domain\School\Actions\CreateCampus;
use App\Domain\School\Actions\CreateSchoolClass;
use App\Domain\School\Actions\CreateSection;
use App\Domain\School\Actions\CreateSubject;
use App\Domain\School\Actions\SetClassSubjects;
use App\Domain\School\Actions\SetCurrentAcademicYear;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\Subject;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;

/**
 * Fictional school structure for the demo schools, created through the real
 * Actions by each school's demo principal. Safe to re-run.
 * illustrative — verify: names, years and subjects are placeholders.
 */
class DemoStructureSeeder extends Seeder
{
    /** @var array<string, array{principal: string, campuses: array<string, string>}> */
    private const SCHOOLS = [
        'greenfield' => ['principal' => 'principal@greenfield.test', 'campuses' => ['MAIN' => 'Main Campus']],
        'riverside' => ['principal' => 'principal@riverside.test', 'campuses' => ['MAIN' => 'Main Campus', 'NORTH' => 'North Campus']],
    ];

    private const SUBJECTS = ['ENG' => 'English', 'HIN' => 'Hindi', 'MAT' => 'Mathematics', 'SCI' => 'Science', 'SST' => 'Social Studies'];

    public function run(
        CreateCampus $createCampus,
        CreateAcademicYear $createYear,
        SetCurrentAcademicYear $setCurrent,
        CreateSchoolClass $createClass,
        CreateSubject $createSubject,
        SetClassSubjects $setSubjects,
        CreateSection $createSection,
    ): void {
        foreach (self::SCHOOLS as $subdomain => $config) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();
            $principal = Identity::query()->where('email', $config['principal'])->first();

            if ($tenant === null || $principal === null) {
                continue;
            }

            $tenant->execute(function () use ($principal, $config, $createCampus, $createYear, $setCurrent, $createClass, $createSubject, $setSubjects, $createSection) {
                $year = AcademicYear::query()->where('name', '2026-27')->first()
                    ?? $createYear->handle($principal, '2026-27', '2026-04-01', '2027-03-31');
                $setCurrent->handle($principal, $year);

                $subjects = [];

                foreach (self::SUBJECTS as $code => $name) {
                    $subjects[] = Subject::query()->where('code', $code)->first() ?? $createSubject->handle($principal, $name, $code);
                }

                $classes = [];

                foreach (range(1, 5) as $level) {
                    $class = SchoolClass::query()->where('level', $level)->first() ?? $createClass->handle($principal, "Class {$level}", $level);
                    $setSubjects->handle($principal, $class, $subjects);
                    $classes[] = $class;
                }

                foreach ($config['campuses'] as $code => $name) {
                    $campus = Campus::query()->where('code', $code)->first() ?? $createCampus->handle($principal, $name, $code);

                    foreach ($classes as $class) {
                        foreach (['A', 'B'] as $sectionName) {
                            $exists = Section::query()
                                ->where('academic_year_id', $year->id)
                                ->where('campus_id', $campus->id)
                                ->where('school_class_id', $class->id)
                                ->where('name', $sectionName)
                                ->exists();

                            if (! $exists) {
                                $createSection->handle($principal, $year, $campus, $class, $sectionName, 40);
                            }
                        }
                    }
                }
            });
        }
    }
}
