<?php

namespace App\Domain\Leave\Contracts;

/**
 * Tells the class teacher about a new request and the parents about what
 * happened to it. The notification provider and the alerts screen are not
 * built yet; the bound driver only logs. Implementations get ulids only and
 * work out the people themselves.
 */
interface LeaveNotifier
{
    /**
     * @param  'applied'|'recommended'|'approved'|'rejected'|'cancelled'  $event
     */
    public function notify(string $event, string $leaveUlid, string $studentUlid): void;
}
