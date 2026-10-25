<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class DeleteFeeGroup extends Action
{
    public function __construct(private readonly FeeVisibility $visibility) {}

    /**
     * Deletes a fee group that has no classes and was never used on an
     * invoice (a group that was billed stays, because invoices keep pointing
     * at it). Needs `fee_structures.manage` over the whole school.
     */
    public function handle(Identity $actor, FeeGroup $group): void
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        if (FeeGroupClass::query()->where('fee_group_id', $group->id)->exists()) {
            throw FeeException::groupInUse();
        }

        if (FeeInvoice::query()->where('fee_group_id', $group->id)->exists()) {
            throw FeeException::invalidHeads('This fee group has been billed, so it cannot be deleted.');
        }

        $this->transaction(function () use ($group) {
            $this->audit(new AuditEntry('fees.group_deleted', 'fee_group', $group->ulid, ['name' => $group->name], null));

            $group->delete();
        });
    }
}
