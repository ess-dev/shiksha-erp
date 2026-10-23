<?php

namespace App\Domain\Attendance\Contracts;

/**
 * Tells guardians when their child is marked absent, or that an absence was
 * corrected. The channel (SMS, push, WhatsApp) and provider are still open
 * decisions, so Attendance only talks to this interface; the bound driver is
 * the log driver until Communication exists. Implementations receive student
 * ulids only (never names or phone numbers) and resolve guardians themselves.
 */
interface AbsenceNotifier
{
    /**
     * @param  list<string>  $newlyAbsent  Student ulids now absent who were not before
     * @param  list<string>  $noLongerAbsent  Student ulids who were absent and are not any more
     */
    public function notify(string $sectionUlid, string $date, array $newlyAbsent, array $noLongerAbsent): void;
}
