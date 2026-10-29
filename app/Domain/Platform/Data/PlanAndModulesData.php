<?php

namespace App\Domain\Platform\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The school's own plan and module list for the Principal's Settings tab.
 * Read-only; no prices, no internal ids.
 */
#[TypeScript]
class PlanAndModulesData extends Data
{
    /**
     * @param  list<array{key: string, label: string, state: string}>  $modules  state: "in_plan", "add_on", "turned_off" or "not_in_plan"
     */
    public function __construct(
        public string $school_name,
        public ?string $plan_name,
        public string $status,
        public ?string $trial_ends_on,
        public ?string $renews_on,
        public int $students,
        public ?int $plan_min_students,
        public array $modules,
    ) {}
}
