<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\Storage;

class OpenLeaveAttachment extends Action
{
    /**
     * Records that a child's medical certificate or letter was opened
     * (downloads of children's documents are audited). The caller has already
     * checked who may see it.
     */
    public function handle(StudentLeaveRequest $leave): string
    {
        $path = $leave->attachment_path;

        if ($path === null || ! Storage::disk('tenant')->exists($path)) {
            throw LeaveException::fileMissing();
        }

        return $this->transaction(function () use ($leave, $path) {
            $this->audit(new AuditEntry('student_leave.attachment_opened', 'student_leave_request', $leave->ulid));

            return $path;
        });
    }
}
