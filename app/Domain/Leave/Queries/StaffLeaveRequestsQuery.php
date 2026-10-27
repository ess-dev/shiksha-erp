<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Access\Data\StaffAccessData;
use App\Domain\Access\Data\StaffOptionData;
use App\Domain\Access\Queries\ActiveStaffQuery;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffAccessQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\StaffDirectoryRowData;
use App\Domain\Leave\Data\StaffLeaveRequestData;
use App\Domain\Leave\Data\StaffLeaveRequestsScreenData;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;

class StaffLeaveRequestsQuery
{
    /** At most this many requests are sent. */
    private const LIMIT = 200;

    public function __construct(
        private readonly StaffLeaveAccess $access,
        private readonly StaffLeaveItemBuilder $items,
        private readonly StaffLeaveCoverQuery $cover,
        private readonly StaffAccessQuery $staffRoles,
        private readonly ActiveStaffQuery $activeStaff,
        private readonly StaffRefQuery $staff,
        private readonly PermissionScopesQuery $scopes,
        private readonly SectionDirectoryQuery $directory,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->access->mayApprove($actor) || $this->scopes->get($actor, 'staff.view')->allows();
    }

    /**
     * The Staff and leave screen. Leave requests (waiting for the person
     * first, then the rest, latest first) cover current-year requests of staff
     * the person may decide on, never their own. The directory needs
     * `staff.view`.
     */
    public function screen(Identity $actor, string $tab): StaffLeaveRequestsScreenData
    {
        $canLeave = $this->access->mayApprove($actor);
        $canDirectory = $this->scopes->get($actor, 'staff.view')->allows();
        $tab = $tab === 'directory' && $canDirectory ? 'directory' : ($canLeave ? 'leave' : 'directory');

        $staff = $this->staffRoles->get();
        $requests = $canLeave ? $this->requests($actor, $staff) : [];

        return new StaffLeaveRequestsScreenData(
            $tab,
            $canLeave,
            $canDirectory,
            count(array_filter($requests, fn (StaffLeaveRequestData $item) => $item->can_decide)),
            $tab === 'leave' ? $requests : [],
            $tab === 'directory' && $canDirectory ? $this->directoryRows($staff) : [],
            $tab === 'leave' && $canLeave ? $this->coverOptions() : [],
        );
    }

    /**
     * @param  list<StaffAccessData>  $staff
     * @return list<StaffLeaveRequestData>
     */
    private function requests(Identity $actor, array $staff): array
    {
        $year = $this->directory->currentYearRef();

        if ($year === null) {
            return [];
        }

        $scope = $this->access->scopeOf($actor);
        $me = $this->access->staffKey($actor);

        $found = array_values(array_filter(
            StaffLeaveRequest::query()
                ->where('academic_year_id', $year['key'])
                ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value, LeaveStatus::Rejected->value])
                ->orderByDesc('from_on')
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get()
                ->all(),
            fn (StaffLeaveRequest $leave) => $leave->staff_id !== $me && $this->access->covers($scope, $leave),
        ));

        $items = $this->items->build($actor, $found, $this->roleNotes($staff));

        usort($items, fn (StaffLeaveRequestData $a, StaffLeaveRequestData $b) => [$b->can_decide, $b->from_on] <=> [$a->can_decide, $a->from_on]);

        return $items;
    }

    /**
     * @param  list<StaffAccessData>  $staff
     * @return array<string, string> staff ulid => role names
     */
    private function roleNotes(array $staff): array
    {
        $notes = [];

        foreach ($staff as $member) {
            $notes[$member->ulid] = $this->roles($member) === [] ? 'Staff' : implode(', ', $this->roles($member));
        }

        return $notes;
    }

    /**
     * @return list<string>
     */
    private function roles(StaffAccessData $member): array
    {
        return array_values(array_unique(array_map(fn ($assignment) => $assignment->role_name, $member->assignments)));
    }

    /**
     * @param  list<StaffAccessData>  $staff
     * @return list<StaffDirectoryRowData>
     */
    private function directoryRows(array $staff): array
    {
        $onLeave = array_flip($this->staff->ulidsByKey($this->cover->onLeaveOn(CalendarDate::today())));
        $classes = [];

        foreach ($this->access->sections() as $section) {
            if ($section->classTeacherUlid !== null) {
                $classes[$section->classTeacherUlid] = $section->label();
            }
        }

        return array_map(fn (StaffAccessData $member) => new StaffDirectoryRowData(
            $member->ulid,
            $member->full_name,
            $this->roles($member),
            $classes[$member->ulid] ?? null,
            $member->status === 'active',
            isset($onLeave[$member->ulid]),
        ), $staff);
    }

    /**
     * @return list<StaffOptionData>
     */
    private function coverOptions(): array
    {
        return $this->activeStaff->options();
    }
}
