<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\Storage;

class OpenStaffLeaveAttachment extends Action
{
    /**
     * Records that a staff member's medical certificate or letter was opened
     * (downloads of personal documents are audited). The caller has already
     * checked who may see it.
     */
    public function handle(StaffLeaveRequest $leave): string
    {
        $path = $leave->attachment_path;

        if ($path === null || ! Storage::disk('tenant')->exists($path)) {
            throw LeaveException::fileMissing();
        }

        return $this->transaction(function () use ($leave, $path) {
            $this->audit(new AuditEntry('staff_leave.attachment_opened', 'staff_leave_request', $leave->ulid));

            return $path;
        });
    }
}
