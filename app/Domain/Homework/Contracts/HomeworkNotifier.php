<?php

namespace App\Domain\Homework\Contracts;

/**
 * Tells families about homework (new, changed or removed, feedback given) and
 * the teacher about uploaded work. The notification provider and the alerts
 * screen are not built yet; the bound driver only logs. Implementations get
 * ulids only and work out the people themselves.
 */
interface HomeworkNotifier
{
    /**
     * @param  'assigned'|'changed'|'removed'|'feedback'|'submitted'  $event
     * @param  list<string>  $studentUlids  The students the event is about
     */
    public function notify(string $event, string $assignmentUlid, string $sectionUlid, array $studentUlids): void;
}
