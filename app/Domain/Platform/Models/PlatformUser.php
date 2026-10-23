<?php

namespace App\Domain\Platform\Models;

use Database\Factories\PlatformUserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A Super Admin (landlord). Signs in on the platform host only, always with two-step.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_signed_in_at
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class PlatformUser extends Authenticatable
{
    /** @use HasFactory<PlatformUserFactory> */
    use HasFactory, TwoFactorAuthenticatable, UsesLandlordConnection;

    protected $table = 'platform_users';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'email', 'password', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected static function newFactory(): PlatformUserFactory
    {
        return PlatformUserFactory::new();
    }
}
