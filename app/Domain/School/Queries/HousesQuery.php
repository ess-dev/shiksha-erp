<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\HouseData;
use App\Domain\School\Models\House;
use Illuminate\Support\Facades\DB;

class HousesQuery
{
    /**
     * Every house in display order, with how many students are in it.
     *
     * @return list<HouseData>
     */
    public function all(): array
    {
        $counts = DB::connection(config('multitenancy.tenant_database_connection_name'))
            ->table('students')
            ->selectRaw('house_id, count(*) as total')
            ->groupBy('house_id')
            ->pluck('total', 'house_id');

        return array_values(House::query()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (House $house) => new HouseData($house->ulid, $house->name, $house->status, (int) ($counts[$house->id] ?? 0)))
            ->all());
    }

    /**
     * Houses a student can be placed in.
     *
     * @return list<HouseData>
     */
    public function active(): array
    {
        return array_values(array_filter($this->all(), fn (HouseData $house) => $house->status === 'active'));
    }

    /**
     * Key of an active house by name (any case), for the CSV import; null when
     * there is none. Inactive houses are not matched.
     */
    public function activeKeyByName(string $name): ?int
    {
        $key = House::query()
            ->where('status', 'active')
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->value('id');

        return $key === null ? null : (int) $key;
    }

    public function anyKeyByUlid(string $ulid): ?int
    {
        $key = House::query()->where('ulid', $ulid)->value('id');

        return $key === null ? null : (int) $key;
    }

    public function activeKeyByUlid(string $ulid): ?int
    {
        $key = House::query()->where('status', 'active')->where('ulid', $ulid)->value('id');

        return $key === null ? null : (int) $key;
    }
}
