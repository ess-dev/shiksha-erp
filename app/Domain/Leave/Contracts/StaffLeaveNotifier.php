<?php

namespace App\Domain\Leave\Contracts;

/**
 * Tells the approver about a new staff leave request and the applicant (and the
 * named cover) about what happened to it. The notification provider and the
 * alerts screen are not built yet; the bound driver only logs. Implementations
 * get ulids only and work out the people themselves.
 */
interface StaffLeaveNotifier
{
    /**
     * @param  'applied'|'approved'|'rejected'|'cancelled'  $event
     */
    public function notify(string $event, string $leaveUlid, string $staffUlid): void;
}
