<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Contracts\ExamNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that parents would be told, never who. Replace
 * the binding when Communication and a push/SMS provider exist.
 */
class LogExamNotifier implements ExamNotifier
{
    public function notify(string $examUlid, string $event): void
    {
        Log::info('Parent notification would be sent (no provider configured).', ['exam' => $examUlid, 'event' => $event]);
    }
}
