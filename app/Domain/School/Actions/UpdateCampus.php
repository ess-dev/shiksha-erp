<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\Campus;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use InvalidArgumentException;

class UpdateCampus extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    /**
     * Renames a campus or sets it active/inactive (campuses are never deleted).
     */
    public function handle(Identity $actor, Campus $campus, string $name, string $status): Campus
    {
        $this->guard->assertMayManageSchool($actor);

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException("Unknown campus status [{$status}].");
        }

        return $this->transaction(function () use ($campus, $name, $status) {
            $before = ['name' => $campus->name, 'status' => $campus->status];

            $campus->forceFill(['name' => trim($name), 'status' => $status])->save();

            $this->audit(new AuditEntry('structure.campus_updated', 'campus', $campus->ulid, $before, [
                'name' => $campus->name, 'status' => $campus->status,
            ]));

            return $campus;
        });
    }
}
