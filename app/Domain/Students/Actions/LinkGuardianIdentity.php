<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Shared\Action;
use App\Domain\Students\Models\Guardian;

class LinkGuardianIdentity extends Action
{
    /**
     * Records which sign-in identity belongs to the guardian with this phone,
     * after the guardian proved the phone with a one-time code. Does nothing
     * when the link already exists.
     */
    public function handle(string $phoneE164, string $identityUlid): ?Guardian
    {
        $guardian = Guardian::query()->where('phone', $phoneE164)->first();

        if ($guardian === null || $guardian->identity_ulid === $identityUlid) {
            return $guardian;
        }

        return $this->transaction(function () use ($guardian, $identityUlid) {
            $before = ['identity_linked' => $guardian->identity_ulid !== null];

            $guardian->forceFill(['identity_ulid' => $identityUlid])->save();

            $this->audit(new AuditEntry('guardians.identity_linked', 'guardian', $guardian->ulid, $before, ['identity_linked' => true]));

            return $guardian;
        });
    }
}
