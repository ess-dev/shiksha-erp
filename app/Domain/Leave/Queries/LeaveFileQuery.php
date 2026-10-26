<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\LeaveAccess;
use App\Domain\Leave\Models\StudentLeaveRequest;

/**
 * Finds the request whose medical certificate or letter someone may open, or
 * a 404: staff covering the student's section.
 */
class LeaveFileQuery
{
    public function __construct(private readonly LeaveAccess $access) {}

    public function forStaff(Identity $actor, string $leaveUlid): StudentLeaveRequest
    {
        [$leave] = $this->access->requestOrFail($actor, $leaveUlid);

        if ($leave->attachment_path === null) {
            throw LeaveException::fileMissing();
        }

        return $leave;
    }
}
