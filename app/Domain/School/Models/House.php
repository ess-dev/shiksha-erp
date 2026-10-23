<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A school house ("Raman"). A student without a house is "Not assigned".
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $status
 * @property int $sort_order
 */
class House extends Model
{
    use UsesTenantConnection;

    protected $table = 'houses';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'status', 'sort_order'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
