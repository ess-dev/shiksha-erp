<?php

namespace App\Domain\Students\Data;

use App\Domain\School\Data\SessionSwitcherData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What every parent page needs around its content: the school, the guardian's
 * first name, their children, who is selected and the session switcher.
 */
#[TypeScript]
class ParentShellData extends Data
{
    /**
     * @param  list<ParentChildData>  $children  Children in the viewed session; empty when there are none
     * @param  string|null  $selected_child  Ulid of the child being shown
     */
    public function __construct(
        public string $school_name,
        public string $guardian_name,
        public string $guardian_first_name,
        public array $children,
        public ?string $selected_child,
        public ?SessionSwitcherData $sessions,
        public bool $has_staff_context = false,
        public bool $attendance_enabled = false,
        public bool $exams_enabled = false,
        public string $timezone = 'Asia/Kolkata',
        public bool $fees_enabled = false,
        public bool $homework_enabled = false,
    ) {}
}
