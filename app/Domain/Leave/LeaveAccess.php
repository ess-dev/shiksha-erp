<?php

namespace App\Domain\Leave;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\LeaveAbilities;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;

/**
 * Who may see and decide which leave request, in one place so the screens and
 * the Actions cannot disagree. Current academic year only.
 *
 * Someone holding `leave.approve` over a student's section decides short leave
 * and, for leave longer than the school's limit, recommends it to the
 * coordinator or rejects it. Someone holding `leave.approve_long` over the
 * section decides any open request, recommended or not.
 */
class LeaveAccess
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'leave.approve')->allows() || $this->scopes->get($actor, 'leave.approve_long')->allows();
    }

    public function mayOpenApprovals(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'leave.approve_long')->allows();
    }

    public function staffKey(Identity $actor): ?int
    {
        $me = $this->staff->forIdentity($actor);

        return $me !== null && $me->isActive ? $me->staffKey : null;
    }

    /**
     * Current-year sections whose leave the person may see, by section key.
     *
     * @return array<int, SectionRefData>
     */
    public function sections(Identity $actor): array
    {
        $short = $this->scopes->get($actor, 'leave.approve');
        $long = $this->scopes->get($actor, 'leave.approve_long');
        $found = [];

        foreach ($this->sections->currentYear() as $section) {
            if ($short->allows($section->scopeTarget()) || $long->allows($section->scopeTarget())) {
                $found[$section->sectionKey] = $section;
            }
        }

        return $found;
    }

    public function abilities(Identity $actor, StudentLeaveRequest $leave, SectionRefData $section): LeaveAbilities
    {
        $canShort = $this->scopes->get($actor, 'leave.approve')->allows($section->scopeTarget());
        $canLong = $this->scopes->get($actor, 'leave.approve_long')->allows($section->scopeTarget());

        return match (true) {
            $leave->status === LeaveStatus::Pending && $canLong => new LeaveAbilities(approve: true, reject: true),
            $leave->status === LeaveStatus::Pending && $canShort && $leave->needs_coordinator => new LeaveAbilities(reject: true, recommend: true),
            $leave->status === LeaveStatus::Pending && $canShort => new LeaveAbilities(approve: true, reject: true),
            $leave->status === LeaveStatus::Recommended && $canLong => new LeaveAbilities(approve: true, reject: true),
            default => new LeaveAbilities,
        };
    }

    /**
     * A current-year request in a section the person may see, or a 404.
     *
     * @return array{0: StudentLeaveRequest, 1: SectionRefData}
     */
    public function requestOrFail(Identity $actor, string $leaveUlid): array
    {
        $leave = StudentLeaveRequest::query()->where('ulid', $leaveUlid)->first();
        $section = $leave === null ? null : ($this->sections($actor)[$leave->section_id] ?? null);

        if ($leave === null || $section === null) {
            throw LeaveException::notFound();
        }

        return [$leave, $section];
    }
}
