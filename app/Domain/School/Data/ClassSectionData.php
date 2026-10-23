<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One section as the Classes and sections screen shows it: strength, class
 * teacher and today's register. `register_saved_at` is null while the
 * register is unmarked, or when the attendance module is not in the plan.
 */
#[TypeScript]
class ClassSectionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $class_ulid,
        public string $class_name,
        public int $class_level,
        public string $campus_ulid,
        public ?string $room,
        public ?int $capacity,
        public int $students,
        public int $girls,
        public ?string $class_teacher_name,
        public ?string $register_saved_at,
        public int $register_absent,
        public bool $can_manage,
        public ?string $next_section_name,
    ) {}
}
