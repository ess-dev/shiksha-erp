<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\Campus;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * Platform step during onboarding: gives a new school its campuses, first
 * academic year (made current) and, optionally, its classes with their
 * sections at every campus. Runs the ordinary structure Actions as the
 * school's owner, so every rule and audit event of those Actions applies.
 */
class SetUpInitialStructure extends Action
{
    public function __construct(
        private readonly CreateCampus $createCampus,
        private readonly CreateAcademicYear $createAcademicYear,
        private readonly SetCurrentAcademicYear $setCurrentAcademicYear,
        private readonly CreateSchoolClass $createClass,
        private readonly CreateSection $createSection,
    ) {}

    /**
     * @param  list<array{name: string, address: ?string}>  $campuses
     * @param  array{name: string, starts_on: string, ends_on: string}  $academicYear
     * @param  list<array{level: int, name: string, sections: list<string>}>  $classes
     * @return array{campuses: int, sections: int}
     */
    public function handle(Tenant $tenant, Identity $owner, array $campuses, array $academicYear, array $classes): array
    {
        return $tenant->execute(fn () => $this->transaction(function () use ($owner, $campuses, $academicYear, $classes) {
            $year = $this->createAcademicYear->handle($owner, $academicYear['name'], $academicYear['starts_on'], $academicYear['ends_on']);
            $this->setCurrentAcademicYear->handle($owner, $year);

            $created = [];
            $usedCodes = [];

            foreach ($campuses as $campus) {
                $created[] = $this->createCampus->handle($owner, $campus['name'], $this->codeFor($campus['name'], $usedCodes), $campus['address']);
            }

            $sections = 0;

            foreach ($classes as $class) {
                $schoolClass = $this->createClass->handle($owner, $class['name'], $class['level']);

                /** @var Campus $campus */
                foreach ($created as $campus) {
                    foreach ($class['sections'] as $name) {
                        $this->createSection->handle($owner, $year, $campus, $schoolClass, $name);
                        $sections++;
                    }
                }
            }

            $this->audit(new AuditEntry('structure.initial_structure_set_up', 'academic_year', $year->ulid, null, [
                'campuses' => count($created),
                'classes' => count($classes),
                'sections' => $sections,
            ]));

            return ['campuses' => count($created), 'sections' => $sections];
        }));
    }

    /**
     * A short unique campus code from its name ("Main campus" -> "MAIN").
     *
     * @param  list<string>  $used
     */
    private function codeFor(string $name, array &$used): string
    {
        $base = strtoupper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '', Str::before(trim($name).' ', ' ')) ?? '', 0, 6));
        $base = $base === '' ? 'CAMPUS' : $base;
        $code = $base;

        for ($n = 2; in_array($code, $used, true); $n++) {
            $code = $base.$n;
        }

        $used[] = $code;

        return $code;
    }
}
