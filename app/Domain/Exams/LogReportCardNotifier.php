<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Contracts\ReportCardNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that people would be told, never who. Replace the
 * binding when staff notifications and a parent provider exist.
 */
class LogReportCardNotifier implements ReportCardNotifier
{
    public function notify(string $event, string $examUlid, string $sectionUlid, array $studentUlids): void
    {
        Log::info('Report card notification would be sent (not built yet).', [
            'event' => $event,
            'exam' => $examUlid,
            'section' => $sectionUlid,
            'students' => count($studentUlids),
        ]);
    }
}
