<?php

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Models\Identity;
use JsonSerializable;

/**
 * The only shape of the signed-in identity sent to the browser: no internal
 * id, no secrets. "name" is a display label until staff records supply names.
 */
final readonly class AuthenticatedIdentityData implements JsonSerializable
{
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public bool $twoFactorEnabled,
    ) {}

    public static function fromIdentity(Identity $identity): self
    {
        return new self(
            ulid: $identity->ulid,
            name: $identity->displayLabel(),
            email: $identity->email,
            phone: $identity->phone_e164,
            twoFactorEnabled: $identity->hasEnabledTwoFactorAuthentication(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'two_factor_enabled' => $this->twoFactorEnabled,
        ];
    }
}
