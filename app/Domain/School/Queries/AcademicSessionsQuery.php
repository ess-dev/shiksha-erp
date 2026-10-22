<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\SessionOptionData;
use App\Domain\School\Models\AcademicYear;

/**
 * Academic years ("sessions") for other modules, without exposing the School
 * models. Newest first.
 */
class AcademicSessionsQuery
{
    /**
     * @return list<SessionOptionData>
     */
    public function all(): array
    {
        return array_values(AcademicYear::query()->orderByDesc('starts_on')->get()
            ->map(fn (AcademicYear $year) => new SessionOptionData($year->ulid, $year->name, $year->is_current))
            ->all());
    }

    /**
     * Key and details of one session by ulid, or of the current one when null.
     *
     * @return array{key: int, ulid: string, name: string, is_current: bool}|null
     */
    public function find(?string $ulid = null): ?array
    {
        $query = AcademicYear::query();
        $year = $ulid === null ? $query->where('is_current', true)->first() : $query->where('ulid', $ulid)->first();

        return $year === null ? null : ['key' => $year->id, 'ulid' => $year->ulid, 'name' => $year->name, 'is_current' => $year->is_current];
    }
}
