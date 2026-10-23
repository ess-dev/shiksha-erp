<?php

namespace App\Domain\School\Actions;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\HouseException;
use App\Domain\School\Models\House;
use App\Domain\Shared\Action;

class ReorderHouses extends Action
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * @param  list<string>  $orderedUlids  Every house, in the new order
     */
    public function handle(Identity $actor, array $orderedUlids): void
    {
        if (! $this->scopes->get($actor, 'houses.manage')->allows()) {
            throw HouseException::notAllowed();
        }

        $this->transaction(function () use ($orderedUlids) {
            $houses = House::query()->get()->keyBy('ulid');

            if (count($orderedUlids) !== $houses->count()
                || count(array_unique($orderedUlids)) !== count($orderedUlids)
                || array_diff($orderedUlids, $houses->keys()->all()) !== []) {
                throw HouseException::invalidOrder();
            }

            foreach ($orderedUlids as $position => $ulid) {
                $house = $houses[$ulid];

                if ($house->sort_order !== $position + 1) {
                    $house->update(['sort_order' => $position + 1]);
                }
            }

            $this->audit(new AuditEntry('houses.houses_reordered', 'house', null, null, ['order' => $orderedUlids]));
        });
    }
}
