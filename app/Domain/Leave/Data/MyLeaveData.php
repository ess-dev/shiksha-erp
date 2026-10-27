<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The staff member's My leave screen: balances by type, the form's limits and
 * their own requests, latest first. `class_label` is the class they lead, if
 * any (the approver then names who marks its register).
 */
#[TypeScript]
class MyLeaveData extends Data
{
    /**
     * @param  list<LeaveBalanceData>  $balances
     * @param  list<StaffLeaveRequestData>  $requests
     */
    public function __construct(
        public array $balances,
        public array $requests,
        public ?string $class_label,
        public string $earliest_from,
        public string $today,
        public int $backdate_days,
    ) {}
}
