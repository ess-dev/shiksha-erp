<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A day the school is closed ("Diwali"), on top of its weekly days off.
 *
 * @property int $id
 * @property string $ulid
 * @property string $holiday_on
 * @property string $name
 */
class Holiday extends Model
{
    use UsesTenantConnection;

    protected $table = 'school_holidays';

    /** @var list<string> */
    protected $fillable = ['ulid', 'holiday_on', 'name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['holiday_on' => 'string'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
