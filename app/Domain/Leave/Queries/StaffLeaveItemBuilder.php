<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\LeaveAttachmentData;
use App\Domain\Leave\Data\StaffLeaveRequestData;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;
use Illuminate\Support\Facades\URL;

/**
 * Turns staff leave requests into the shape the pages get: names instead of
 * keys, a short-lived signed link to the certificate, and what the signed-in
 * person may do with each request.
 */
class StaffLeaveItemBuilder
{
    /** illustrative — verify: how long a certificate link stays valid. */
    private const LINK_MINUTES = 10;

    public function __construct(
        private readonly StaffLeaveAccess $access,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * @param  list<StaffLeaveRequest>  $requests
     * @param  array<string, string>  $roleNotes  staff ulid => role names, for applicants who lead no class
     * @return list<StaffLeaveRequestData>
     */
    public function build(Identity $actor, array $requests, array $roleNotes = []): array
    {
        if ($requests === []) {
            return [];
        }

        $keys = array_merge(
            array_map(fn (StaffLeaveRequest $leave) => $leave->staff_id, $requests),
            array_map(fn (StaffLeaveRequest $leave) => $leave->decided_by_staff_id, $requests),
            array_map(fn (StaffLeaveRequest $leave) => $leave->cover_staff_id, $requests),
        );
        $names = $this->staff->namesByKey($keys);
        $ulids = $this->staff->ulidsByKey($keys);
        $me = $this->access->staffKey($actor);
        $mayApprove = $this->access->mayApprove($actor);
        $sections = $this->access->sections();
        $scope = $this->access->scopeOf($actor);
        $items = [];

        foreach ($requests as $leave) {
            $section = $leave->section_id === null ? null : ($sections[$leave->section_id] ?? null);
            $own = $leave->staff_id === $me;
            $note = $section !== null ? "Class teacher · {$section->label()}" : ($roleNotes[$ulids[$leave->staff_id] ?? ''] ?? 'Staff');

            $items[] = new StaffLeaveRequestData(
                $leave->ulid,
                $ulids[$leave->staff_id] ?? '',
                $names[$leave->staff_id] ?? 'Staff',
                $note,
                $leave->leave_type,
                $leave->leave_type->label(),
                $leave->from_on,
                $leave->to_on,
                $leave->half_days,
                $leave->reason,
                $leave->suggested_cover,
                $leave->applied_at->setTimezone((string) config('shiksha.timezone'))->format('Y-m-d'),
                $leave->status,
                $leave->attachment_name === null ? null : new LeaveAttachmentData(
                    $leave->attachment_name,
                    (string) $leave->attachment_mime,
                    (int) $leave->attachment_size,
                    URL::temporarySignedRoute('staff-leave.attachment', now()->addMinutes(self::LINK_MINUTES), ['leave' => $leave->ulid]),
                    str_starts_with((string) $leave->attachment_mime, 'image/'),
                ),
                $leave->decided_by_staff_id === null ? null : ($names[$leave->decided_by_staff_id] ?? null),
                $leave->decision_note,
                $leave->cover_staff_id === null ? null : ($names[$leave->cover_staff_id] ?? null),
                $section?->label(),
                $mayApprove && ! $own && $leave->status === LeaveStatus::Pending && $this->access->covers($scope, $leave),
                $own && $leave->status === LeaveStatus::Pending,
            );
        }

        return $items;
    }
}
