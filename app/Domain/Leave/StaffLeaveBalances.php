<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Data\LeaveBalanceData;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\StaffLeaveType;
use App\Domain\Leave\Models\StaffLeaveRequest;

/**
 * What each leave type allows a member of staff this academic year and how
 * much is used (approved) or held back (pending). Counted in half-days.
 */
class StaffLeaveBalances
{
    public function __construct(private readonly StaffLeavePolicy $policy) {}

    /**
     * @return list<LeaveBalanceData>
     */
    public function forStaff(int $staffKey, int $yearKey): array
    {
        $policy = $this->policy->all();
        $used = [];
        $pending = [];

        foreach (StaffLeaveRequest::query()
            ->where('staff_id', $staffKey)
            ->where('academic_year_id', $yearKey)
            ->whereIn('status', [LeaveStatus::Approved->value, LeaveStatus::Pending->value])
            ->get(['leave_type', 'status', 'half_days']) as $leave) {
            $type = $leave->leave_type->value;

            if ($leave->status === LeaveStatus::Approved) {
                $used[$type] = ($used[$type] ?? 0) + $leave->half_days;
            } else {
                $pending[$type] = ($pending[$type] ?? 0) + $leave->half_days;
            }
        }

        return array_map(fn (StaffLeaveType $type) => new LeaveBalanceData(
            $type,
            $type->label(),
            $policy[$type->value]['days'] === null ? null : $policy[$type->value]['days'] * 2,
            $used[$type->value] ?? 0,
            $pending[$type->value] ?? 0,
            $policy[$type->value]['document_after_days'],
        ), StaffLeaveType::cases());
    }
}
