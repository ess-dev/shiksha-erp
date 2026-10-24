<?php

namespace App\Domain\Exams\Contracts;

/**
 * Tells parents that a subject's marks are on their child's report card. The
 * channel and provider are still open decisions; the bound driver only logs
 * until Communication exists. Implementations receive ulids only and work out
 * the guardians themselves.
 */
interface MarksNotifier
{
    public function marksPublished(string $examUlid, string $sectionUlid, string $subjectUlid): void;
}
