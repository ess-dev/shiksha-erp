<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * @property int $role_id
 * @property string $permission_key
 */
class RolePermission extends Model
{
    use UsesTenantConnection;

    public $timestamps = false;

    protected $table = 'role_permissions';

    /** @var list<string> */
    protected $fillable = ['role_id', 'permission_key'];
}
