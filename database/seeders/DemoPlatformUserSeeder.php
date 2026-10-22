<?php

namespace Database\Seeders;

use App\Domain\Platform\Actions\CreatePlatformUser;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Database\Seeder;

/**
 * A fictional Super Admin for local development only. Safe to re-run.
 * In any other environment create real accounts with platform:create-user.
 */
class DemoPlatformUserSeeder extends Seeder
{
    public function run(CreatePlatformUser $create): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (PlatformUser::query()->where('email', 'superadmin@shiksha.test')->exists()) {
            return;
        }

        $create->handle('Demo Super Admin', 'superadmin@shiksha.test', 'password');
    }
}
