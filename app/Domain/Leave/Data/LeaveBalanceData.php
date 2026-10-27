<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Enums\StaffLeaveType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One leave type's allowance for the academic year, in half-days (2 per day)
 * so a half day needs no fraction. `allowed_half_days` is null for "as needed".
 */
#[TypeScript]
class LeaveBalanceData extends Data
{
    public function __construct(
        public StaffLeaveType $type,
        public string $label,
        public ?int $allowed_half_days,
        public int $used_half_days,
        public int $pending_half_days,
        public ?int $document_after_days,
    ) {}
}
