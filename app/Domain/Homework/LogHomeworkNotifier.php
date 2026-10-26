<?php

namespace App\Domain\Homework;

use App\Domain\Homework\Contracts\HomeworkNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that people would be told, never who. Replace the
 * binding when the alerts and notifications module and a provider exist.
 */
class LogHomeworkNotifier implements HomeworkNotifier
{
    public function notify(string $event, string $assignmentUlid, string $sectionUlid, array $studentUlids): void
    {
        Log::info('Homework notification would be sent (not built yet).', [
            'event' => $event,
            'assignment' => $assignmentUlid,
            'section' => $sectionUlid,
            'students' => count($studentUlids),
        ]);
    }
}
