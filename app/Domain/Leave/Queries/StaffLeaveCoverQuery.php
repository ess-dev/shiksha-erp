<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StaffLeaveRequest;
use DateTimeImmutable;

/**
 * Who covers a class's register, for Attendance. Cover is not stored on its
 * own: it is the named staff member of an approved leave of the class
 * teacher, valid only on the leave's dates, so cancelling the leave ends it.
 */
class StaffLeaveCoverQuery
{
    /** Whether the staff member is the named cover of that section on that calendar day. */
    public function coversOn(int $staffKey, int $sectionKey, string $date): bool
    {
        return StaffLeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->where('cover_staff_id', $staffKey)
            ->where('section_id', $sectionKey)
            ->where('from_on', '<=', $date)
            ->where('to_on', '>=', $date)
            ->exists();
    }

    /**
     * Whether the staff member may open the section's register: the leave has
     * begun and ended no more than `$windowDays` days ago (the register's
     * correction window), so a cover can still finish the last days.
     */
    public function mayOpen(int $staffKey, int $sectionKey, string $today, int $windowDays): bool
    {
        return StaffLeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->where('cover_staff_id', $staffKey)
            ->where('section_id', $sectionKey)
            ->where('from_on', '<=', $today)
            ->where('to_on', '>=', (new DateTimeImmutable($today))->modify("-{$windowDays} days")->format('Y-m-d'))
            ->exists();
    }

    /**
     * Whether the staff member covers any class register right now (same window as `mayOpen`).
     */
    public function hasAnyOpen(int $staffKey, string $today, int $windowDays): bool
    {
        return StaffLeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->where('cover_staff_id', $staffKey)
            ->whereNotNull('section_id')
            ->where('from_on', '<=', $today)
            ->where('to_on', '>=', (new DateTimeImmutable($today))->modify("-{$windowDays} days")->format('Y-m-d'))
            ->exists();
    }

    /**
     * Whether the staff member is on approved leave on any day of the range.
     */
    public function isOnLeave(int $staffKey, string $from, string $to): bool
    {
        return StaffLeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->where('staff_id', $staffKey)
            ->where('from_on', '<=', $to)
            ->where('to_on', '>=', $from)
            ->exists();
    }

    /**
     * Staff keys on approved leave on the day.
     *
     * @return list<int>
     */
    public function onLeaveOn(string $date): array
    {
        return array_values(StaffLeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->where('from_on', '<=', $date)
            ->where('to_on', '>=', $date)
            ->pluck('staff_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
