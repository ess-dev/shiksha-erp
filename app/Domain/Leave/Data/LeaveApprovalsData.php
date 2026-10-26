<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Long student leave waiting for the coordinator or principal: recommended by
 * a class teacher, or longer than the limit and not yet looked at.
 */
#[TypeScript]
class LeaveApprovalsData extends Data
{
    /**
     * @param  list<StaffLeaveItemData>  $items
     */
    public function __construct(
        public array $items,
        public int $escalation_days,
    ) {}
}
