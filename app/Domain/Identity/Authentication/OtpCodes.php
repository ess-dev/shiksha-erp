<?php

namespace App\Domain\Identity\Authentication;

/**
 * Makes and hashes one-time codes. The hash is keyed with the app key and the
 * challenge ulid, so a leaked table cannot be reversed with a rainbow table.
 */
class OtpCodes
{
    public function generate(): string
    {
        $length = (int) config('shiksha.otp.length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    public function hash(string $code, string $challengeUlid): string
    {
        return hash_hmac('sha256', $code, config('app.key').'|'.$challengeUlid);
    }

    public function matches(string $code, string $challengeUlid, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code, $challengeUlid));
    }
}
