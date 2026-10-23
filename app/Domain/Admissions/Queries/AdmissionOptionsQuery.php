<?php

namespace App\Domain\Admissions\Queries;

use App\Domain\Admissions\AdmissionVisibility;
use App\Domain\Admissions\Data\AdmissionAbilitiesData;
use App\Domain\Admissions\Data\AdmissionOptionsData;
use App\Domain\Admissions\Data\AdmissionPlaceData;
use App\Domain\Admissions\Data\ClassOptionData;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Students\Data\OptionData;
use App\Domain\Students\Data\SectionOptionData;
use App\Domain\Students\Queries\StudentOptionsQuery;
use Illuminate\Support\Facades\DB;

class AdmissionOptionsQuery
{
    /** illustrative — verify: the relations the prototype offers. */
    private const RELATIONS = ['Mother', 'Father', 'Guardian'];

    public function __construct(
        private readonly AdmissionVisibility $visibility,
        private readonly SectionDirectoryQuery $sections,
        private readonly StudentOptionsQuery $studentOptions,
    ) {}

    public function abilities(Identity $identity): AdmissionAbilitiesData
    {
        $holds = fn (string $permission) => ! $this->visibility->scopes($identity, $permission)->isEmpty();

        return new AdmissionAbilitiesData(
            manage: $holds('admissions.manage'),
            enroll: $holds('admissions.enroll'),
            documents: $holds('students.documents'),
            view_sensitive: $holds('students.view_sensitive'),
            settings: $holds('settings.manage'),
        );
    }

    /**
     * Choices for the forms. Only the campus and class pairs the person may
     * take enquiries for (admissions.manage scope, and a section exists this year).
     */
    public function get(Identity $identity): AdmissionOptionsData
    {
        $base = $this->studentOptions->get();
        $set = $this->visibility->scopes($identity, 'admissions.manage');

        $campuses = [];
        $classes = [];
        $offered = [];

        foreach ($this->sections->currentYear() as $section) {
            if ($set->isEmpty() || ! $set->allows($section->scopeTarget())) {
                continue;
            }

            $campuses[$section->campusUlid] = new OptionData($section->campusUlid, $section->campusName);
            $classes[$section->classUlid] = new ClassOptionData($section->classUlid, $section->className, $section->classLevel);
            $offered[$section->campusUlid.'|'.$section->classUlid] = new AdmissionPlaceData($section->campusUlid, $section->classUlid);
        }

        $classes = array_values($classes);
        usort($classes, fn (ClassOptionData $a, ClassOptionData $b) => $a->level <=> $b->level);

        return new AdmissionOptionsData(
            genders: $base->genders,
            blood_groups: $base->blood_groups,
            houses: $base->houses,
            sources: array_map(fn (AdmissionSource $source) => new OptionData($source->value, $source->label()), AdmissionSource::cases()),
            relations: array_map(fn (string $relation) => new OptionData($relation, $relation), self::RELATIONS),
            campuses: array_values($campuses),
            classes: $classes,
            offered: array_values($offered),
        );
    }

    /**
     * Sections the person may enrol this applicant into: the class applied
     * for, this year, in their `admissions.enroll` scope, the applicant's own
     * campus first.
     *
     * @return list<SectionOptionData>
     */
    public function enrolSections(Identity $identity, string $applicationUlid): array
    {
        $set = $this->visibility->scopes($identity, 'admissions.enroll');
        $application = AdmissionApplication::query()->where('ulid', $applicationUlid)->first();

        if ($set->isEmpty() || $application === null || ! $this->visibility->allows($identity, $application, 'admissions.view')) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classUlid = (string) $connection->table('school_classes')->where('id', $application->school_class_id)->value('ulid');
        $campusUlid = (string) $connection->table('campuses')->where('id', $application->campus_id)->value('ulid');

        $sections = array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => $section->classUlid === $classUlid && $set->allows($section->scopeTarget()),
        );

        usort($sections, fn (SectionRefData $a, SectionRefData $b) => ($a->campusUlid === $campusUlid ? 0 : 1) <=> ($b->campusUlid === $campusUlid ? 0 : 1));

        return array_map(fn (SectionRefData $section) => new SectionOptionData(
            ulid: $section->ulid,
            label: $section->label(),
            campus_name: $section->campusName,
            enrolled: $section->enrolledCount,
            capacity: $section->capacity,
            is_full: $section->isFull(),
        ), $sections);
    }
}
