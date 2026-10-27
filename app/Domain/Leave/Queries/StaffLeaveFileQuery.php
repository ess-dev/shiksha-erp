<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;

/**
 * Finds the staff request whose certificate or letter someone may open, or a
 * 404: the applicant, or an approver whose scope covers the request.
 */
class StaffLeaveFileQuery
{
    public function __construct(private readonly StaffLeaveAccess $access) {}

    public function forActor(Identity $actor, string $leaveUlid): StaffLeaveRequest
    {
        $leave = StaffLeaveRequest::query()->where('ulid', $leaveUlid)->first();
        $own = $leave !== null && $leave->staff_id === $this->access->staffKey($actor);

        if ($leave === null || (! $own && ! $this->access->maySee($actor, $leave))) {
            throw LeaveException::notFound();
        }

        if ($leave->attachment_path === null) {
            throw LeaveException::fileMissing();
        }

        return $leave;
    }
}
