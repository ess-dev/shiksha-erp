<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Models\RoleAssignment;

/**
 * Who holds a permission over a part of the school, for messages such as
 * "Report cards go to Rachna Kapoor (Coordinator)". Active staff only; the
 * owner role holds every permission.
 */
class PermissionHoldersQuery
{
    /**
     * @return list<array{staff_key: int, name: string, role: string, is_owner: bool}> Owners last
     */
    public function over(string $permission, ScopeTarget $target): array
    {
        $assignments = RoleAssignment::query()
            ->with(['role', 'staff'])
            ->whereHas('staff', fn ($query) => $query->where('status', 'active'))
            ->whereHas('role', fn ($query) => $query->where('is_owner', true)
                ->orWhereHas('permissions', fn ($permissions) => $permissions->where('permission_key', $permission)))
            ->get();

        $holders = [];

        foreach ($assignments as $assignment) {
            if (! $assignment->toScope()->covers($target) || isset($holders[$assignment->staff_id])) {
                continue;
            }

            $holders[$assignment->staff_id] = [
                'staff_key' => (int) $assignment->staff_id,
                'name' => (string) $assignment->staff->full_name,
                'role' => (string) $assignment->role->name,
                'is_owner' => (bool) $assignment->role->is_owner,
            ];
        }

        $holders = array_values($holders);
        usort($holders, fn (array $a, array $b) => [$a['is_owner'], $a['name']] <=> [$b['is_owner'], $b['name']]);

        return $holders;
    }
}
