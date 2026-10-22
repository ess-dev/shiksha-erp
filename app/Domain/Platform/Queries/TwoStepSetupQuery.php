<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Platform\Data\TwoStepSetupData;
use App\Domain\Platform\Models\PlatformUser;
use Laravel\Fortify\Fortify;

class TwoStepSetupQuery
{
    /**
     * Null until setup has been started, and null once two-step is confirmed.
     */
    public function get(PlatformUser $user): ?TwoStepSetupData
    {
        if (blank($user->two_factor_secret) || $user->hasEnabledTwoFactorAuthentication()) {
            return null;
        }

        return new TwoStepSetupData(
            $user->twoFactorQrCodeSvg(),
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
        );
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(PlatformUser $user): array
    {
        return array_values($user->recoveryCodes());
    }
}
