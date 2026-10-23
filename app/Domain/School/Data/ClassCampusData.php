<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A campus on the Classes and sections screen, with the classes the person
 * may still add there (classes that have no section at it this year).
 */
#[TypeScript]
class ClassCampusData extends Data
{
    /**
     * @param  list<ClassLevelOptionData>  $addable_classes  Lowest level first
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public array $addable_classes,
    ) {}
}
