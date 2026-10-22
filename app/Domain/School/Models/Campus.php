<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $code
 * @property string $status
 */
class Campus extends Model
{
    use UsesTenantConnection;

    protected $table = 'campuses';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'code', 'status'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
