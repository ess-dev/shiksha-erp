<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Identity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Identity>
 */
class IdentityFactory extends Factory
{
    protected $model = Identity::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => strtolower((string) Str::ulid()),
            'email' => fake()->unique()->safeEmail(),
            'phone_e164' => '+91'.fake()->unique()->numerify('9#########'),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
