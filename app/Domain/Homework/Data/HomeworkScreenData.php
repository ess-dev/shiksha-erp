<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The teacher's Homework screen: the list on the left, the selected
 * assignment on the right, and what the New assignment dialog needs.
 *
 * @param  string|null  $section_ulid  The section filter in effect
 * @param  string  $today  School calendar date
 */
#[TypeScript]
class HomeworkScreenData extends Data
{
    /**
     * @param  list<HomeworkSectionFilterData>  $sections
     * @param  list<HomeworkListItemData>  $assignments
     */
    public function __construct(
        public string $today,
        public bool $can_assign,
        public array $sections,
        public ?string $section_ulid,
        public array $assignments,
        public ?string $selected_ulid,
        public ?HomeworkDetailData $selected,
        public ?HomeworkFormOptionsData $form,
    ) {}
}
