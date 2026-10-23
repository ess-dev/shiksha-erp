<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\PermissionRisk;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * An entry of the platform permission catalog (landlord).
 *
 * @property string $key
 * @property string $module
 * @property PermissionRisk $risk
 * @property bool $is_owner_only
 */
class Permission extends Model
{
    use UsesLandlordConnection;

    protected $table = 'permissions';

    /** @var list<string> */
    protected $fillable = ['key', 'module', 'risk', 'is_owner_only', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk' => PermissionRisk::class,
            'is_owner_only' => 'boolean',
        ];
    }
}
