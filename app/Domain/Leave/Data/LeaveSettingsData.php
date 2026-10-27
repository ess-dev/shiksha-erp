<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LeaveSettingsData extends Data
{
    /**
     * @param  list<StaffLeavePolicyRowData>  $staff_policy
     */
    public function __construct(
        public int $escalation_days,
        public array $staff_policy,
    ) {}
}
