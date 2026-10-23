<?php

namespace App\Domain\Platform\Data;

use App\Domain\Platform\Models\PlatformUser;
use JsonSerializable;

/**
 * The only shape of the signed-in Super Admin sent to the browser.
 */
final readonly class PlatformUserData implements JsonSerializable
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $email,
    ) {}

    public static function fromUser(PlatformUser $user): self
    {
        return new self($user->ulid, $user->name, $user->email);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
