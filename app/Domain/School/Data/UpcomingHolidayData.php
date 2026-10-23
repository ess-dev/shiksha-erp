<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A holiday on the dashboard's "Upcoming events"; consecutive days with the
 * same name are one entry, `ends_on` is null for a single day.
 */
#[TypeScript]
class UpcomingHolidayData extends Data
{
    public function __construct(
        public string $name,
        public string $starts_on,
        public ?string $ends_on,
        public int $days_away,
    ) {}
}
