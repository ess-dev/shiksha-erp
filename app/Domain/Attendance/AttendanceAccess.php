<?php

namespace App\Domain\Attendance;

use App\Domain\Access\Data\StaffRefData;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SchoolSettingsQuery;

/**
 * Who may see and change which register, in one place so the screen and the
 * Action cannot disagree.
 *
 * - See: `attendance.view` or `attendance.mark` over the section's scope.
 * - Mark and edit freely: the section's own class teacher (with
 *   `attendance.mark` over it), while the register is inside the correction
 *   window; a reason is needed once the day has passed.
 * - Correct: `attendance.correct` over the section, on any day, always with a
 *   reason.
 */
class AttendanceAccess
{
    /** @var array<string, StaffRefData|null> */
    private array $staffCache = [];

    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    public function mayView(Identity $actor, SectionRefData $section): bool
    {
        $target = $section->scopeTarget();

        return $this->scopes->get($actor, 'attendance.view')->allows($target)
            || $this->scopes->get($actor, 'attendance.mark')->allows($target);
    }

    public function isClassTeacher(Identity $actor, SectionRefData $section): bool
    {
        if ($section->classTeacherStaffKey === null) {
            return false;
        }

        $staff = $this->staffOf($actor);

        return $staff !== null && $staff->isActive && $staff->staffKey === $section->classTeacherStaffKey
            && $this->scopes->get($actor, 'attendance.mark')->allows($section->scopeTarget());
    }

    /**
     * The actor's staff record, looked up once per request.
     */
    public function staffOf(Identity $actor): ?StaffRefData
    {
        if (! array_key_exists($actor->ulid, $this->staffCache)) {
            $this->staffCache[$actor->ulid] = $this->staff->forIdentity($actor);
        }

        return $this->staffCache[$actor->ulid];
    }

    public function mayCorrect(Identity $actor, SectionRefData $section): bool
    {
        return $this->scopes->get($actor, 'attendance.correct')->allows($section->scopeTarget());
    }

    public function correctionWindowDays(): int
    {
        return $this->settings->attendanceCorrectionWindowDays();
    }

    /**
     * What the actor may do to the register of a day that is `$ageDays` old
     * (0 is today): whether they can change it, and whether they must say why.
     */
    public function rights(Identity $actor, SectionRefData $section, int $ageDays): EditRights
    {
        if ($this->isClassTeacher($actor, $section) && $ageDays <= $this->correctionWindowDays()) {
            return new EditRights(canEdit: true, needsReason: $ageDays > 0);
        }

        if ($this->mayCorrect($actor, $section)) {
            return new EditRights(canEdit: true, needsReason: true);
        }

        return new EditRights(canEdit: false, needsReason: false);
    }
}
