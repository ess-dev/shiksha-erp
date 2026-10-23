<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A platform-owned, versioned role template (landlord). Schools copy it.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $current_version
 */
class RoleTemplate extends Model
{
    use UsesLandlordConnection;

    protected $table = 'role_templates';

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'current_version'];

    /**
     * @return HasMany<RoleTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RoleTemplateVersion::class);
    }

    public function currentVersion(): RoleTemplateVersion
    {
        return $this->versions()->where('version', $this->current_version)->firstOrFail();
    }
}
