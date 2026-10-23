<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\AssignmentData;
use App\Domain\Access\Data\StaffAccessData;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;

class StaffAccessQuery
{
    /**
     * Staff with the roles they hold and the part of the school each covers.
     * Active staff come first.
     *
     * @return list<StaffAccessData>
     */
    public function get(): array
    {
        $staff = Staff::query()
            ->with(['assignments.role'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('full_name')
            ->get();

        return array_values($staff->map(fn (Staff $member) => new StaffAccessData(
            ulid: $member->ulid,
            full_name: $member->full_name,
            status: $member->status,
            assignments: array_values($member->assignments->map(fn (RoleAssignment $assignment) => $this->assignment($assignment))->all()),
        ))->all());
    }

    private function assignment(RoleAssignment $assignment): AssignmentData
    {
        $scope = $assignment->toScope();
        $range = $scope->levelRange();

        return new AssignmentData(
            ulid: $assignment->ulid,
            role_ulid: $assignment->role->ulid,
            role_name: $assignment->role->name,
            is_owner_role: $assignment->role->is_owner,
            scope_type: $assignment->scope_type,
            campus_ulids: $scope->campusUlids(),
            section_ulids: $scope->sectionUlids(),
            from_level: $range[0] ?? null,
            to_level: $range[1] ?? null,
        );
    }
}
