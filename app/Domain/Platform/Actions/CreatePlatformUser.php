<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

/**
 * Adds a Super Admin. There is no public registration: accounts are created
 * by the platform team (the platform:create-user command).
 */
class CreatePlatformUser extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(string $name, string $email, string $password): PlatformUser
    {
        return $this->transaction(function () use ($name, $email, $password) {
            $user = PlatformUser::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'email' => strtolower(trim($email)),
                'password' => $password,
                'status' => 'active',
            ]);

            $this->audit(new AuditEntry(
                action: 'platform.user_created',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
                after: ['email' => $user->email],
            ));

            return $user;
        });
    }
}
