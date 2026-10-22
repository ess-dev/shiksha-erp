<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * One published version of a role template, with its permission keys.
 *
 * @property int $id
 * @property int $role_template_id
 * @property int $version
 * @property bool $is_owner_template
 */
class RoleTemplateVersion extends Model
{
    use UsesLandlordConnection;

    protected $table = 'role_template_versions';

    /** @var list<string> */
    protected $fillable = ['role_template_id', 'version', 'changelog', 'is_owner_template'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_owner_template' => 'boolean'];
    }

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'role_template_id');
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return array_values($this->getConnection()->table('role_template_version_permissions')
            ->where('role_template_version_id', $this->id)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all());
    }
}
