<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Data\Scope;
use App\Domain\Access\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A role given to one member of staff, limited to a scope.
 *
 * @property int $id
 * @property string $ulid
 * @property int $role_id
 * @property int $staff_id
 * @property ScopeType $scope_type
 * @property array<string, mixed>|null $scope
 * @property string $source
 */
class RoleAssignment extends Model
{
    use UsesTenantConnection;

    protected $table = 'role_assignments';

    /** @var list<string> */
    protected $fillable = ['ulid', 'role_id', 'staff_id', 'scope_type', 'scope', 'source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope_type' => ScopeType::class, 'scope' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function toScope(): Scope
    {
        return Scope::fromStored($this->scope_type, $this->scope);
    }
}
