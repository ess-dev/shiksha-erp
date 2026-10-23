<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\StaffOptionData;
use App\Domain\Access\Models\Staff;

class ActiveStaffQuery
{
    /**
     * Active staff by name, for pickers such as "Class teacher". Names only.
     *
     * @return list<StaffOptionData>
     */
    public function options(): array
    {
        return array_values(Staff::query()->where('status', 'active')->orderBy('full_name')->get()
            ->map(fn (Staff $staff) => new StaffOptionData($staff->ulid, $staff->full_name))
            ->all());
    }
}
