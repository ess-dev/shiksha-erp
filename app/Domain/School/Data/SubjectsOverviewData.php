<?php

namespace App\Domain\School\Data;

use App\Domain\Access\Data\StaffOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the Subjects screen shows: what each class studies, the school's
 * subject list and who teaches what in the chosen section.
 */
#[TypeScript]
class SubjectsOverviewData extends Data
{
    /**
     * @param  list<SubjectClassData>  $classes  Lowest level first
     * @param  list<SubjectRowData>  $subjects  In the order they were added
     * @param  list<SubjectSectionOptionData>  $sections  Sections of the current year the person may see
     * @param  list<StaffOptionData>  $staff  Active staff to choose from; empty unless the person may configure subjects
     */
    public function __construct(
        public bool $can_configure,
        public ?string $campus_name,
        public array $classes,
        public array $subjects,
        public array $sections,
        public ?SectionTeachersData $selected_section,
        public array $staff,
    ) {}
}
