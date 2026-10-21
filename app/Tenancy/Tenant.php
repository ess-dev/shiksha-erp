<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * A school organization. Lives in the landlord database; its data lives in
 * the database named by `database_name`.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $subdomain
 * @property string $database_name
 * @property string|null $database_username
 * @property string|null $database_password
 * @property TenantStatus $status
 */
class Tenant extends BaseTenant
{
    protected $table = 'tenants';

    /** @var list<string> */
    protected $fillable = [
        'ulid',
        'name',
        'subdomain',
        'database_name',
        'database_username',
        'database_password',
        'status',
    ];

    /** @var list<string> */
    protected $hidden = [
        'database_username',
        'database_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'database_username' => 'encrypted',
            'database_password' => 'encrypted',
            'status' => TenantStatus::class,
        ];
    }

    public function getDatabaseName(): string
    {
        return $this->database_name;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isAccessible(): bool
    {
        return $this->status->allowsAccess();
    }

    /**
     * Whether the school's plan includes the module (plan gating).
     */
    public function hasModule(string $module): bool
    {
        return DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenant_modules')
            ->where('tenant_id', $this->id)
            ->where('module', $module)
            ->exists();
    }
}
