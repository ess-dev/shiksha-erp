<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Bounds are calendar dates, kept as "Y-m-d" strings (no time zone, no Carbon).
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $starts_on
 * @property string $ends_on
 * @property bool $is_current
 */
class AcademicYear extends Model
{
    use UsesTenantConnection;

    protected $table = 'academic_years';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'starts_on', 'ends_on', 'is_current'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'starts_on' => 'string', 'ends_on' => 'string'];
    }
}
