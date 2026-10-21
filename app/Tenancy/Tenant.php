<?php

namespace App\Tenancy;

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
 * @property string $status
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
}
