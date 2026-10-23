<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A school's role (tenant database): a named set of permission keys.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $source_template_key
 * @property int|null $source_template_version
 * @property bool $is_owner
 */
class Role extends Model
{
    use UsesTenantConnection;

    protected $table = 'roles';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'source_template_key', 'source_template_version', 'is_owner'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return array_values($this->permissions()->orderBy('permission_key')->pluck('permission_key')
            ->map(fn (mixed $key): string => (string) $key)->all());
    }
}
