<?php

namespace App\Domain\Exams\Contracts;

/**
 * Tells people about report cards: approvers that some wait for them, the
 * class teacher that some were sent back, parents that one is ready. Staff
 * notifications and parent providers are not built yet; the bound driver only
 * logs. Implementations receive ulids only and work out the people themselves.
 */
interface ReportCardNotifier
{
    /**
     * @param  'submitted'|'published'|'returned'  $event
     * @param  list<string>  $studentUlids
     */
    public function notify(string $event, string $examUlid, string $sectionUlid, array $studentUlids): void;
}
