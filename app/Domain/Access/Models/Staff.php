<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A member of staff in one school (tenant database). References the global
 * identity by ulid. Holds only what access control needs so far.
 *
 * @property int $id
 * @property string $ulid
 * @property string $identity_ulid
 * @property string $full_name
 * @property string $status
 * @property string|null $employee_code
 * @property string|null $job_title
 * @property string|null $department
 * @property string|null $joined_on
 * @property string|null $personal_email
 * @property string|null $qualifications
 * @property string|null $languages
 * @property string|null $home_address
 * @property string|null $about
 * @property string|null $emergency_name
 * @property string|null $emergency_relation
 * @property string|null $emergency_phone
 * @property string|null $photo_path
 * @property array<string, bool>|null $notification_channels
 */
class Staff extends Model
{
    use UsesTenantConnection;

    protected $table = 'staff';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'identity_ulid', 'full_name', 'status',
        'employee_code', 'job_title', 'department', 'joined_on',
        'personal_email', 'qualifications', 'languages', 'home_address', 'about',
        'emergency_name', 'emergency_relation', 'emergency_phone', 'photo_path', 'notification_channels',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notification_channels' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
