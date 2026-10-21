<?php

namespace App\Domain\Identity\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * Links an identity to one school with a kind (staff or guardian).
 *
 * @property int $id
 * @property string $ulid
 * @property int $identity_id
 * @property int $tenant_id
 * @property string $kind
 * @property string $status
 * @property Carbon|null $revoked_at
 */
class Membership extends Model
{
    use UsesLandlordConnection;

    public const KIND_STAFF = 'staff';

    public const KIND_GUARDIAN = 'guardian';

    protected $table = 'memberships';

    /** @var list<string> */
    protected $fillable = ['ulid', 'identity_id', 'tenant_id', 'kind', 'status', 'revoked_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Identity, $this>
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
