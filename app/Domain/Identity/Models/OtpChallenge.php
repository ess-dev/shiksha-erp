<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A one-time sign-in code sent to a guardian's phone for one school. Only a
 * keyed hash of the code is stored.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property string $phone_e164
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon $created_at
 */
class OtpChallenge extends Model
{
    use UsesLandlordConnection;

    public const UPDATED_AT = null;

    protected $table = 'otp_challenges';

    /** @var list<string> */
    protected $fillable = ['ulid', 'tenant_id', 'phone_e164', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
