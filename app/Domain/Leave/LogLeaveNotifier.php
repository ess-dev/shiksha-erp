<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Contracts\LeaveNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that people would be told, never who. Replace the
 * binding when the alerts and notifications module and a provider exist.
 */
class LogLeaveNotifier implements LeaveNotifier
{
    public function notify(string $event, string $leaveUlid, string $studentUlid): void
    {
        Log::info('Leave notification would be sent (not built yet).', [
            'event' => $event,
            'leave' => $leaveUlid,
            'student' => $studentUlid,
        ]);
    }
}
