<?php

namespace App\Domain\Exams\Contracts;

/**
 * Tells parents about an exam. The channel and provider are still open
 * decisions, so Exams only talks to this interface; the bound driver is the
 * log driver until Communication exists. Implementations receive the exam's
 * ulid only and work out the guardians themselves.
 */
interface ExamNotifier
{
    /**
     * @param  string  $event  'scheduled', 'changed' or 'results_published'
     */
    public function notify(string $examUlid, string $event): void;
}
