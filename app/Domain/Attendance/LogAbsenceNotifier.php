<?php

namespace App\Domain\Attendance;

use App\Domain\Attendance\Contracts\AbsenceNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in notifier: records only how many guardians would be told, never who.
 * Replace the binding when Communication and an SMS/push provider exist.
 */
class LogAbsenceNotifier implements AbsenceNotifier
{
    public function notify(string $sectionUlid, string $date, array $newlyAbsent, array $noLongerAbsent): void
    {
        if ($newlyAbsent === [] && $noLongerAbsent === []) {
            return;
        }

        Log::info('Absence alerts would be sent (no provider configured).', [
            'section' => $sectionUlid,
            'date' => $date,
            'absence_alerts' => count($newlyAbsent),
            'correction_notices' => count($noLongerAbsent),
        ]);
    }
}
