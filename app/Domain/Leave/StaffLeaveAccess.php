<?php

namespace App\Domain\Leave;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;

/**
 * Who may see and decide which staff leave request, in one place so the
 * screens and the Actions cannot disagree. Current academic year only.
 *
 * Holders of `staff.leave_approve` decide the leave of a class teacher when
 * their scope covers that teacher's class (a coordinator: the classes of their
 * wing). Leave of anyone else (no class of their own) can be decided only by a
 * holder whose scope is the whole school. Nobody decides their own leave.
 */
class StaffLeaveAccess
{
    /** @var array<int, SectionRefData>|null */
    private ?array $sections = null;

    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $directory,
    ) {}

    public function mayApprove(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'staff.leave_approve')->allows();
    }

    public function scopeOf(Identity $actor): ScopeSet
    {
        return $this->scopes->get($actor, 'staff.leave_approve');
    }

    public function staffKey(Identity $actor): ?int
    {
        $me = $this->staff->forIdentity($actor);

        return $me !== null && $me->isActive ? $me->staffKey : null;
    }

    /**
     * Current-year sections by section key.
     *
     * @return array<int, SectionRefData>
     */
    public function sections(): array
    {
        if ($this->sections === null) {
            $this->sections = [];

            foreach ($this->directory->currentYear() as $section) {
                $this->sections[$section->sectionKey] = $section;
            }
        }

        return $this->sections;
    }

    /** The class the member of staff leads this year, if any. */
    public function classOf(int $staffKey): ?SectionRefData
    {
        foreach ($this->sections() as $section) {
            if ($section->classTeacherStaffKey === $staffKey) {
                return $section;
            }
        }

        return null;
    }

    /** Whether the scope covers this request (the applicant's class, or the whole school without one). */
    public function covers(ScopeSet $scope, StaffLeaveRequest $leave): bool
    {
        if ($leave->section_id === null) {
            return $scope->isSchoolWide();
        }

        $section = $this->sections()[$leave->section_id] ?? null;

        return $section === null ? $scope->isSchoolWide() : $scope->allows($section->scopeTarget());
    }

    public function maySee(Identity $actor, StaffLeaveRequest $leave): bool
    {
        return $this->covers($this->scopeOf($actor), $leave);
    }

    public function mayDecide(Identity $actor, StaffLeaveRequest $leave): bool
    {
        $me = $this->staffKey($actor);

        return $me !== null && $me !== $leave->staff_id && $this->maySee($actor, $leave);
    }

    /**
     * A request of the current year the person may decide on, or a 404.
     *
     * @throws LeaveException
     */
    public function requestOrFail(Identity $actor, string $leaveUlid): StaffLeaveRequest
    {
        $year = $this->directory->currentYearRef();
        $leave = StaffLeaveRequest::query()->where('ulid', $leaveUlid)->first();

        if ($leave === null || $year === null || $leave->academic_year_id !== $year['key'] || ! $this->maySee($actor, $leave)) {
            throw LeaveException::notFound();
        }

        return $leave;
    }
}
