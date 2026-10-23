<?php

namespace App\Domain\Identity\Models;

use App\Domain\Shared\PhoneNumber;
use Database\Factories\IdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A global sign-in identity (landlord). Holds no names or other personal data.
 *
 * @property int $id
 * @property string $ulid
 * @property string|null $phone_e164
 * @property string|null $email
 * @property string|null $password
 * @property string $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Identity extends Authenticatable
{
    /** @use HasFactory<IdentityFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, UsesLandlordConnection;

    protected $table = 'identities';

    /** @var list<string> */
    protected $fillable = ['ulid', 'phone_e164', 'email', 'password', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * What the UI shows in place of a name, which this table never holds.
     */
    public function displayLabel(): string
    {
        if ($this->email !== null) {
            return $this->email;
        }

        return $this->phone_e164 !== null ? PhoneNumber::mask($this->phone_e164) : $this->ulid;
    }

    protected static function newFactory(): IdentityFactory
    {
        return IdentityFactory::new();
    }
}
