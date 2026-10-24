<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A subject in the marks screen's Subject picker: "yours" when the person
 * teaches it, otherwise view only unless they oversee marks for the section.
 */
#[TypeScript]
class MarksSubjectOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public bool $is_mine,
        public bool $may_enter,
    ) {}
}
