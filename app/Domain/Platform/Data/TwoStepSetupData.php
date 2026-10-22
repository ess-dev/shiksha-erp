<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * What the setup screen needs to enrol an authenticator app. Only sent while
 * two-step is being set up, never afterwards.
 */
final readonly class TwoStepSetupData implements JsonSerializable
{
    public function __construct(
        public string $qrCodeSvg,
        public string $secretKey,
    ) {}

    /**
     * @return array{qr_code_svg: string, secret_key: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'qr_code_svg' => $this->qrCodeSvg,
            'secret_key' => $this->secretKey,
        ];
    }
}
