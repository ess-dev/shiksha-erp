<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Models\Staff;

class StaffNamesQuery
{
    /**
     * Staff names by identity ulid, so other modules can show "verified by
     * Manoj Bansal" while storing only the identity ulid.
     *
     * @param  list<string>  $identityUlids
     * @return array<string, string>
     */
    public function forIdentities(array $identityUlids): array
    {
        $identityUlids = array_values(array_unique(array_filter($identityUlids)));

        if ($identityUlids === []) {
            return [];
        }

        /** @var array<string, string> $names */
        $names = Staff::query()->whereIn('identity_ulid', $identityUlids)->pluck('full_name', 'identity_ulid')->all();

        return $names;
    }
}
