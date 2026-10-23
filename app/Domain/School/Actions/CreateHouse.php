<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Models\House;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class CreateHouse extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Adds a house at the end of the list. Needs `houses.manage` (any scope:
     * houses belong to the whole school).
     */
    public function handle(Identity $actor, string $name): House
    {
        if (! $this->scopes->get($actor, 'houses.manage')->allows()) {
            throw HouseException::notAllowed();
        }

        $name = trim($name);

        return $this->transaction(function () use ($name) {
            if (House::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw HouseException::duplicateName($name);
            }

            $house = House::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'status' => 'active',
                'sort_order' => (int) House::query()->max('sort_order') + 1,
            ]);

            $this->audit(new AuditEntry('houses.house_created', 'house', $house->ulid, null, ['name' => $name]));

            return $house;
        });
    }
}
