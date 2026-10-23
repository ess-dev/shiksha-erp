<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class CreateStaffRecord extends Action
{
    /**
     * Creates the school's record of a member of staff, linked to a sign-in
     * identity by ulid. Who may call this is decided by the caller's policy.
     */
    public function handle(string $identityUlid, string $fullName): Staff
    {
        return $this->transaction(function () use ($identityUlid, $fullName) {
            $staff = Staff::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'identity_ulid' => $identityUlid,
                'full_name' => $fullName,
                'status' => 'active',
            ]);

            $this->audit(new AuditEntry('staff.created', 'staff', $staff->ulid, null, ['identity_ulid' => $identityUlid]));

            return $staff;
        });
    }
}
