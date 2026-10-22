<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A member of staff in one school (tenant database). References the global
 * identity by ulid. Holds only what access control needs so far.
 *
 * @property int $id
 * @property string $ulid
 * @property string $identity_ulid
 * @property string $full_name
 * @property string $status
 */
class Staff extends Model
{
    use UsesTenantConnection;

    protected $table = 'staff';

    /** @var list<string> */
    protected $fillable = ['ulid', 'identity_ulid', 'full_name', 'status'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
