<?php

namespace Tests\Concerns;

use App\Domain\Access\Data\Scope;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\TenantStatus;
use Closure;
use Illuminate\Testing\TestResponse;

/**
 * The authorization matrix every feature needs (`.ai/40-security-quality-testing.md`):
 * allowed in scope, denied out of scope, denied without the permission, denied
 * when the module is not in the plan, blocked when the school is suspended.
 *
 * Needs HasCurrentSchool and ManagesSchoolPlan. The caller supplies how to make
 * an actor and how to send the request, so it fits any route. Skip the
 * out-of-scope case (pass null) for routes that filter a list instead of
 * addressing one record. A core-module route cannot be taken out of a plan, so
 * pass a non-core module only. The owner role is built from the plan when the
 * school is set up, so enable the module in `beforeEach` before building the
 * school, or the owner cannot grant the permission to the test actors.
 */
trait AssertsAuthorizationMatrix
{
    /**
     * @param  string  $module  plan module the route sits behind
     * @param  string  $permission  the permission key the route needs
     * @param  Scope  $inScope  scope that covers the record the request touches
     * @param  Scope|null  $outOfScope  scope that does not cover it; null skips that case
     * @param  Closure(list<string>, Scope): Identity  $makeActor  a staff identity holding the permissions over the scope
     * @param  Closure(Identity): TestResponse  $send  sends the request as that actor
     * @param  string  $otherPermission  a real permission that is not the one under test
     * @param  int  $outOfScopeStatus  404 by the access rules (do not confirm existence)
     * @param  int  $noPermissionStatus  status when the permission is missing
     * @param  int  $noModuleStatus  status when the module is not in the plan (module middleware aborts 404)
     * @param  int  $suspendedStatus  status when the school is suspended
     */
    protected function assertAuthorizationMatrix(
        string $module,
        string $permission,
        Scope $inScope,
        ?Scope $outOfScope,
        Closure $makeActor,
        Closure $send,
        string $otherPermission = 'students.view',
        int $outOfScopeStatus = 404,
        int $noPermissionStatus = 403,
        int $noModuleStatus = 404,
        int $suspendedStatus = 403,
    ): void {
        $this->enableModules([$module]);

        $actor = $makeActor([$permission], $inScope);
        expect($send($actor)->getStatusCode())->toBeLessThan(400, 'allowed in scope');

        if ($outOfScope !== null) {
            $send($makeActor([$permission], $outOfScope))->assertStatus($outOfScopeStatus);
        }

        $send($makeActor([$otherPermission], $inScope))->assertStatus($noPermissionStatus);

        $this->disableModule($module);
        $send($actor)->assertStatus($noModuleStatus);
        $this->enableModules([$module]);

        $this->setSchoolStatus(TenantStatus::Suspended);
        $send($actor)->assertStatus($suspendedStatus);
        $this->setSchoolStatus(TenantStatus::Active);
    }
}
