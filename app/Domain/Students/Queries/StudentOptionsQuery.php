<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\HouseData;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\HousesQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Students\Data\OptionData;
use App\Domain\Students\Data\SectionOptionData;
use App\Domain\Students\Data\StudentOptionsData;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\DocumentKind;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Enums\TransferReason;
use App\Domain\Students\StudentVisibility;

class StudentOptionsQuery
{
    public function __construct(
        private readonly HousesQuery $houses,
        private readonly SectionDirectoryQuery $sections,
        private readonly StudentVisibility $visibility,
    ) {}

    /**
     * Choices for the student forms and dialogs, labelled for display.
     */
    public function get(): StudentOptionsData
    {
        return new StudentOptionsData(
            genders: array_map(fn (Gender $case) => new OptionData($case->value, $case->label()), Gender::cases()),
            blood_groups: array_map(fn (BloodGroup $case) => new OptionData($case->value, $case->label()), BloodGroup::cases()),
            houses: array_map(fn (HouseData $house) => new OptionData($house->ulid, $house->name), $this->houses->active()),
            transfer_reasons: array_map(fn (TransferReason $case) => new OptionData($case->value, $case->label()), TransferReason::cases()),
            document_kinds: array_map(fn (DocumentKind $case) => new OptionData($case->value, $case->label()), DocumentKind::cases()),
        );
    }

    /**
     * Sections of the current academic year the identity may see students in
     * (their `students.view` scope), for the Students list's class filter.
     *
     * @return list<SectionOptionData>
     */
    public function classFilter(Identity $identity, ?string $campusUlid = null, ?int $yearId = null): array
    {
        $set = $this->visibility->scopes($identity, 'students.view');

        return array_values(array_map(
            fn (SectionRefData $section) => new SectionOptionData(
                ulid: $section->ulid,
                label: $section->label(),
                campus_name: $section->campusName,
                enrolled: $section->enrolledCount,
                capacity: $section->capacity,
                is_full: $section->isFull(),
            ),
            array_filter(
                $yearId === null ? $this->sections->currentYear() : $this->sections->inYear($yearId),
                fn (SectionRefData $section) => $set->allows($section->scopeTarget()) && ($campusUlid === null || $section->campusUlid === $campusUlid),
            ),
        ));
    }
}
