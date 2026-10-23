<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A child in the parent portal: only what the child switcher and the child
 * card need.
 */
#[TypeScript]
class ParentChildData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $first_name,
        public string $relation,
        public string $section_label,
        public string $campus_name,
        public ?int $roll_number,
        public ?string $class_teacher_name,
    ) {}
}
