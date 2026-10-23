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
 * @property int|null $plan_id
 * @property string|null $city
 * @property string|null $state
 * @property string|null $board
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property int $campus_count
 * @property int $student_count
 * @property string|null $since_on
 * @property string|null $trial_ends_on
 * @property string|null $renews_on
 * @property string|null $suspended_reason
 */
class Tenant extends BaseTenant
{
    /** Module every school has, whatever its plan. */
    public const CORE_MODULE = 'core';

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
        'city',
        'state',
        'board',
        'contact_name',
        'contact_email',
        'campus_count',
        'student_count',
        'since_on',
        'trial_ends_on',
        'renews_on',
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
        if ($module === self::CORE_MODULE) {
            return true;
        }

        return DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenant_modules')
            ->where('tenant_id', $this->id)
            ->where('module', $module)
            ->exists();
    }
}
