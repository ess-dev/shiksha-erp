<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Enums\PermissionRisk;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\RoleTemplate;

class StaffRoleOptionsQuery
{
    /** Teacher roles follow from class-teacher allocation and the timetable, not from an invite. */
    private const AUTOMATIC = ['class_teacher', 'subject_teacher'];

    /**
     * The role templates a platform user may give a new school's staff.
     *
     * @return list<array{key: string, label: string, requires_two_step: bool}>
     */
    public function templates(): array
    {
        $options = RoleTemplate::query()
            ->whereNotIn('key', self::AUTOMATIC)
            ->orderBy('id')
            ->get()
            ->map(fn (RoleTemplate $template) => [
                'key' => $template->key,
                'label' => $template->name,
                // A role with a critical permission needs two-step sign-in (locked rule).
                'requires_two_step' => Permission::query()
                    ->whereIn('key', $template->currentVersion()->permissionKeys())
                    ->where('risk', PermissionRisk::Critical->value)
                    ->exists(),
            ])
            ->all();

        return array_values($options);
    }
}
