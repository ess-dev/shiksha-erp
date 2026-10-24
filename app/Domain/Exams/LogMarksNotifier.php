<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Contracts\MarksNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records that parents would be told, never who. Replace
 * the binding when Communication and a push/SMS provider exist.
 */
class LogMarksNotifier implements MarksNotifier
{
    public function marksPublished(string $examUlid, string $sectionUlid, string $subjectUlid): void
    {
        Log::info('Parent notification would be sent (no provider configured).', [
            'exam' => $examUlid,
            'section' => $sectionUlid,
            'subject' => $subjectUlid,
            'event' => 'marks_published',
        ]);
    }
}
