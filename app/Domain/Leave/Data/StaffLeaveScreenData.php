<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The staff Student leave screen: requests from the sections the person
 * covers. `tab` is "review" (waiting for them) or "all".
 */
#[TypeScript]
class StaffLeaveScreenData extends Data
{
    /**
     * @param  list<StaffLeaveItemData>  $items
     * @param  list<string>  $on_leave_today  Names of students on approved leave today
     */
    public function __construct(
        public string $tab,
        public array $items,
        public int $review_count,
        public array $on_leave_today,
        public int $escalation_days,
        public bool $can_configure,
    ) {}
}
