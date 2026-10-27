<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\MyLeaveData;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;
use App\Domain\Leave\StaffLeaveBalances;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;

class MyLeaveQuery
{
    /** At most this many of the person's own requests are sent. */
    private const LIMIT = 100;

    public function __construct(
        private readonly StaffLeaveAccess $access,
        private readonly StaffLeaveBalances $balances,
        private readonly StaffLeaveItemBuilder $items,
        private readonly SectionDirectoryQuery $directory,
        private readonly LeaveRules $rules,
    ) {}

    /**
     * The My leave screen of the signed-in staff member for the current
     * academic year (null when they have no active staff record or the school
     * has no current year).
     */
    public function get(Identity $actor): ?MyLeaveData
    {
        $staffKey = $this->access->staffKey($actor);
        $year = $this->directory->currentYearRef();

        if ($staffKey === null || $year === null) {
            return null;
        }

        $requests = array_values(StaffLeaveRequest::query()
            ->where('staff_id', $staffKey)
            ->where('academic_year_id', $year['key'])
            ->orderByDesc('from_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->all());

        return new MyLeaveData(
            $this->balances->forStaff($staffKey, $year['key']),
            $this->items->build($actor, $requests),
            $this->access->classOf($staffKey)?->label(),
            $this->rules->earliestFrom(),
            CalendarDate::today(),
            LeaveRules::BACKDATE_DAYS,
        );
    }
}
