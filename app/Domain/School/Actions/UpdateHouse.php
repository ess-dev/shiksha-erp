<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Models\House;
use App\Domain\Shared\Action;
use InvalidArgumentException;

class UpdateHouse extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Renames a house or sets it active/inactive. Houses are never deleted;
     * an inactive house stays on the students who have it.
     */
    public function handle(Identity $actor, House $house, string $name, string $status): House
    {
        if (! $this->scopes->get($actor, 'houses.manage')->allows()) {
            throw HouseException::notAllowed();
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('A house is active or inactive.');
        }

        $name = trim($name);

        return $this->transaction(function () use ($house, $name, $status) {
            $taken = House::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->where('id', '!=', $house->id)
                ->exists();

            if ($taken) {
                throw HouseException::duplicateName($name);
            }

            $before = ['name' => $house->name, 'status' => $house->status];
            $house->update(['name' => $name, 'status' => $status]);

            $this->audit(new AuditEntry('houses.house_updated', 'house', $house->ulid, $before, ['name' => $name, 'status' => $status]));

            return $house;
        });
    }
}
