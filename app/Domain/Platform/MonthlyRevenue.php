<?php

namespace App\Domain\Platform;

use App\Domain\Platform\Models\Plan;
use App\Tenancy\TenantStatus;

/**
 * Monthly recurring revenue of one school, in paise.
 *
 * Billed on the higher of actual students or the plan minimum, per student
 * per year, spread over 12 months. Only active schools count. Rounding the
 * monthly figure half-up to the paisa is illustrative — verify (accountant
 * sign-off on money rounding is pending); add-ons are not built yet.
 */
final class MonthlyRevenue
{
    public static function paise(?Plan $plan, TenantStatus $status, int $students): int
    {
        if ($plan === null || $status !== TenantStatus::Active) {
            return 0;
        }

        return intdiv(self::annualPaise($plan, $students) + 6, 12);
    }

    /**
     * Yearly value of a school on the plan, whatever its status.
     */
    public static function annualPaise(Plan $plan, int $students): int
    {
        return max($students, $plan->min_students) * $plan->price_per_student_year_paise;
    }
}
