<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * Platform dashboard figures that exist today: schools by status. Revenue,
 * students and trial conversion arrive with plans and subscriptions.
 */
final readonly class PlatformOverviewData implements JsonSerializable
{
    /**
     * @param  array<string, int>  $schoolsByStatus  keyed by tenant status
     * @param  list<string>  $planNames  in display order
     */
    public function __construct(
        public string $today,
        public int $schoolsTotal,
        public array $schoolsByStatus,
        public array $planNames,
    ) {}

    /**
     * @return array{today: string, schools_total: int, schools_by_status: array<string, int>, plan_names: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'today' => $this->today,
            'schools_total' => $this->schoolsTotal,
            'schools_by_status' => $this->schoolsByStatus,
            'plan_names' => $this->planNames,
        ];
    }
}
