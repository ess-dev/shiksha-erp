<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Contracts\StaffLeaveNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that people would be told, never who. Replace the
 * binding when the alerts and notifications module exists.
 */
class LogStaffLeaveNotifier implements StaffLeaveNotifier
{
    public function notify(string $event, string $leaveUlid, string $staffUlid): void
    {
        Log::info('Staff leave notification would be sent (not built yet).', [
            'event' => $event,
            'leave' => $leaveUlid,
            'staff' => $staffUlid,
        ]);
    }
}
