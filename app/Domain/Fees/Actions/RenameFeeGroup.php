<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Database\UniqueConstraintViolationException;

class RenameFeeGroup extends Action
{
    public function __construct(private readonly FeeVisibility $visibility) {}

    /**
     * Renames a fee group. Needs `fee_structures.manage` over the whole school.
     */
    public function handle(Identity $actor, FeeGroup $group, string $name): FeeGroup
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 40) {
            throw FeeException::invalidHeads('Enter a group name of up to 40 characters.');
        }

        try {
            return $this->transaction(function () use ($group, $name) {
                $before = $group->name;
                $group->forceFill(['name' => $name])->save();

                $this->audit(new AuditEntry('fees.group_renamed', 'fee_group', $group->ulid, ['name' => $before], ['name' => $name]));

                return $group;
            });
        } catch (UniqueConstraintViolationException) {
            throw FeeException::nameTaken();
        }
    }
}
