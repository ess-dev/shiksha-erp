<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

/**
 * Changes a plan's price, minimum billable students and included modules.
 *
 * Module changes apply at once to every school on the plan (their data is
 * kept); a new price is only a record here, applied at each school's next
 * renewal by billing.
 */
class UpdatePlan extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    /**
     * @param  list<string>  $moduleKeys
     */
    public function handle(Plan $plan, int $pricePerStudentYearPaise, int $minStudents, array $moduleKeys): Plan
    {
        return $this->transaction(function () use ($plan, $pricePerStudentYearPaise, $minStudents, $moduleKeys) {
            $moduleKeys = array_values(array_unique($moduleKeys));
            $currentKeys = array_values($plan->planModules()->get()->map(fn (PlanModule $row) => $row->module_key)->all());

            $added = array_values(array_diff($moduleKeys, $currentKeys));
            $removed = array_values(array_diff($currentKeys, $moduleKeys));

            $before = [
                'price_per_student_year_paise' => $plan->price_per_student_year_paise,
                'min_students' => $plan->min_students,
                'modules' => $this->sorted($currentKeys),
            ];

            $plan->forceFill([
                'price_per_student_year_paise' => $pricePerStudentYearPaise,
                'min_students' => $minStudents,
            ])->save();

            if ($removed !== []) {
                $plan->planModules()->whereIn('module_key', $removed)->delete();
            }

            foreach ($added as $key) {
                PlanModule::query()->create(['plan_id' => $plan->id, 'module_key' => $key]);
            }

            $this->syncSchoolEntitlements($plan, $added, $removed);

            $this->audit(new AuditEntry(
                action: 'platform.plan_updated',
                subjectType: 'plan',
                subjectUlid: $plan->ulid,
                before: $before,
                after: [
                    'price_per_student_year_paise' => $pricePerStudentYearPaise,
                    'min_students' => $minStudents,
                    'modules' => $this->sorted($moduleKeys),
                ],
            ));

            return $plan;
        });
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    private function syncSchoolEntitlements(Plan $plan, array $added, array $removed): void
    {
        $table = DB::connection(config('multitenancy.landlord_database_connection_name'))->table('tenant_modules');
        $tenantIds = DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenants')->where('plan_id', $plan->id)->pluck('id');

        if ($tenantIds->isEmpty()) {
            return;
        }

        if ($removed !== []) {
            (clone $table)->whereIn('tenant_id', $tenantIds)->whereIn('module', $removed)->delete();
        }

        foreach ($tenantIds as $tenantId) {
            foreach ($added as $key) {
                (clone $table)->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'module' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }
}
