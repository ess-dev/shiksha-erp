<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\Shared\Action;
use InvalidArgumentException;

/**
 * Adds every class of a level range (from the configured class ladder) and
 * their sections at one campus for one academic year, in one step. Classes and
 * sections that already exist are skipped, so the tool can be run again to top
 * up. It runs the ordinary class and section Actions, so their permission
 * checks and audit events apply.
 */
class CreateClassesInBulk extends Action
{
    public const int MAX_SECTIONS = 12;

    public function __construct(
        private readonly CreateSchoolClass $createClass,
        private readonly CreateSection $createSection,
    ) {}

    /**
     * @param  bool  $letters  Name sections A, B, C… (otherwise 1, 2, 3…)
     * @param  array<int, int>  $sectionsByLevel  Section count for a level that differs from $sectionCount
     * @return array{classes_created: int, sections_created: int, sections_skipped: int}
     */
    public function handle(
        Identity $actor,
        AcademicYear $year,
        Campus $campus,
        int $fromLevel,
        int $toLevel,
        int $sectionCount,
        bool $letters = true,
        ?int $capacity = null,
        array $sectionsByLevel = [],
    ): array {
        $ladder = collect(config()->array('shiksha.class_ladder'))
            ->filter(fn (array $step) => $step['level'] >= $fromLevel && $step['level'] <= $toLevel)
            ->sortBy('level')
            ->values();

        if ($ladder->isEmpty() || $fromLevel > $toLevel) {
            throw new InvalidArgumentException('Pick a range with at least one class.');
        }

        return $this->transaction(function () use ($actor, $year, $campus, $ladder, $sectionCount, $letters, $capacity, $sectionsByLevel) {
            $classesCreated = 0;
            $sectionsCreated = 0;
            $sectionsSkipped = 0;

            foreach ($ladder as $step) {
                $class = SchoolClass::query()->where('level', $step['level'])->first();

                if ($class === null) {
                    $class = $this->createClass->handle($actor, $step['name'], $step['level']);
                    $classesCreated++;
                }

                $count = max(1, min(self::MAX_SECTIONS, $sectionsByLevel[$step['level']] ?? $sectionCount));

                foreach (self::sectionNames($count, $letters) as $name) {
                    $exists = Section::query()
                        ->where('academic_year_id', $year->id)
                        ->where('campus_id', $campus->id)
                        ->where('school_class_id', $class->id)
                        ->where('name', $name)
                        ->exists();

                    if ($exists) {
                        $sectionsSkipped++;

                        continue;
                    }

                    $this->createSection->handle($actor, $year, $campus, $class, $name, $capacity);
                    $sectionsCreated++;
                }
            }

            $this->audit(new AuditEntry('structure.classes_created_in_bulk', 'academic_year', $year->ulid, null, [
                'campus' => $campus->ulid,
                'classes' => $classesCreated,
                'sections' => $sectionsCreated,
                'skipped' => $sectionsSkipped,
            ]));

            return [
                'classes_created' => $classesCreated,
                'sections_created' => $sectionsCreated,
                'sections_skipped' => $sectionsSkipped,
            ];
        });
    }

    /**
     * @return list<string>
     */
    public static function sectionNames(int $count, bool $letters): array
    {
        return array_map(
            fn (int $i) => $letters ? chr(65 + $i) : (string) ($i + 1),
            range(0, $count - 1),
        );
    }
}
