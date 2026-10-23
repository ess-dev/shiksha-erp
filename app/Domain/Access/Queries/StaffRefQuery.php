<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\StaffRefData;
use App\Domain\Access\Models\Staff;
use App\Domain\Identity\Models\Identity;

/**
 * Looks staff up for other modules (attendance, later fees and exams) without
 * exposing the Access models.
 */
class StaffRefQuery
{
    /**
     * The signed-in person's staff record in the current school.
     */
    public function forIdentity(Identity $identity): ?StaffRefData
    {
        $staff = Staff::query()->where('identity_ulid', $identity->ulid)->first();

        return $staff === null ? null : $this->ref($staff);
    }

    public function find(string $ulid): ?StaffRefData
    {
        $staff = Staff::query()->where('ulid', $ulid)->first();

        return $staff === null ? null : $this->ref($staff);
    }

    /**
     * Names by staff key, to show "saved by Neha Verma" next to a stored key.
     *
     * @param  list<int>  $staffKeys
     * @return array<int, string>
     */
    public function namesByKey(array $staffKeys): array
    {
        $staffKeys = array_values(array_unique(array_filter($staffKeys)));

        if ($staffKeys === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Staff::query()->whereIn('id', $staffKeys)->pluck('full_name', 'id')->all();

        return $names;
    }

    private function ref(Staff $staff): StaffRefData
    {
        return new StaffRefData($staff->id, $staff->ulid, $staff->full_name, $staff->isActive());
    }
}
