<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the New / Edit exam dialog offers this person: the types, the classes
 * and campuses they may schedule for, the examined subjects of each class (for
 * the date sheet) and the school's closed days (to fill "one subject a day").
 */
#[TypeScript]
class ExamFormOptionsData extends Data
{
    /**
     * @param  list<ExamTypeOptionData>  $types
     * @param  list<ExamClassOptionData>  $classes  Lowest level first
     * @param  list<ExamCampusOptionData>  $campuses
     * @param  list<ExamClassSubjectsData>  $subjects_by_class
     * @param  list<int>  $working_days  ISO weekdays (1 Monday to 7 Sunday)
     * @param  list<ExamClosedDayData>  $holidays  From today on
     */
    public function __construct(
        public array $types,
        public array $classes,
        public array $campuses,
        public bool $may_choose_all_classes,
        public bool $may_choose_all_campuses,
        public array $subjects_by_class,
        public array $working_days,
        public array $holidays,
        public string $today,
        public string $default_starts_on,
    ) {}
}
