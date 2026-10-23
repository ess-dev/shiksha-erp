<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Admissions\Queries\AdmissionFunnelQuery;
use App\Domain\Attendance\Queries\AttendanceDashboardQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SchoolDashboardData;
use App\Domain\School\Data\UpcomingHolidayData;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\Holiday;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;

/**
 * The staff dashboard: real figures from the modules that exist (attendance,
 * admissions, holidays), each limited to the person's own scope and the
 * campus chosen in the top bar.
 */
class SchoolDashboardQuery
{
    /** Holidays shown under "Upcoming events", in calendar days from today. */
    private const HOLIDAY_HORIZON_DAYS = 30;

    public function __construct(
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly AttendanceDashboardQuery $attendance,
        private readonly AdmissionFunnelQuery $admissions,
    ) {}

    public function get(Identity $identity, ?string $campusUlid): SchoolDashboardData
    {
        $tenant = Tenant::current();
        $timezone = (string) config('shiksha.timezone');
        $hour = (int) CarbonImmutable::now($timezone)->format('G');
        $today = CalendarDate::today();

        return new SchoolDashboardData(
            staff_name: $this->staff->forIdentity($identity)?->fullName,
            part_of_day: $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening'),
            today: $today,
            campus_name: $campusUlid === null ? null : Campus::query()->where('ulid', $campusUlid)->value('name'),
            academic_year_name: $this->sections->currentYearRef()['name'] ?? null,
            timezone: $timezone,
            attendance: $tenant instanceof Tenant && $tenant->hasModule('attendance') ? $this->attendance->get($identity, $campusUlid) : null,
            admissions: $tenant instanceof Tenant && $tenant->hasModule('admissions') ? $this->admissions->get($identity, $campusUlid) : null,
            holidays: $this->holidays($today),
        );
    }

    /**
     * Holidays from today to the horizon; consecutive days with the same name
     * are joined into one entry.
     *
     * @return list<UpcomingHolidayData>
     */
    private function holidays(string $today): array
    {
        $start = CarbonImmutable::parse($today);
        $rows = Holiday::query()
            ->whereBetween('holiday_on', [$today, $start->addDays(self::HOLIDAY_HORIZON_DAYS)->toDateString()])
            ->orderBy('holiday_on')
            ->get(['holiday_on', 'name']);

        /** @var list<array{name: string, from: string, to: string}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['name'] === $row->name
                && CarbonImmutable::parse($groups[$last]['to'])->addDay()->toDateString() === $row->holiday_on) {
                $groups[$last]['to'] = $row->holiday_on;

                continue;
            }

            $groups[] = ['name' => $row->name, 'from' => $row->holiday_on, 'to' => $row->holiday_on];
        }

        return array_map(fn (array $group) => new UpcomingHolidayData(
            name: $group['name'],
            starts_on: $group['from'],
            ends_on: $group['to'] === $group['from'] ? null : $group['to'],
            days_away: (int) $start->diffInDays(CarbonImmutable::parse($group['from'])),
        ), $groups);
    }
}
