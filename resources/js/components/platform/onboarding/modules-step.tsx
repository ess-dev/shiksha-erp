import { enabledModules } from '@/components/platform/onboarding/draft';
import type { StepProps } from '@/components/platform/onboarding/draft';
import ModuleToggle from '@/components/platform/module-toggle';

export default function ModulesStep({
    draft,
    options,
    update,
    errors,
}: StepProps) {
    const plan = options.plans.find((p) => p.ulid === draft.subscription.plan);
    const planKeys = plan?.module_keys ?? [];
    const on = enabledModules(draft, planKeys);

    return (
        <div>
            {options.modules.map((module) => {
                const inPlan = planKeys.includes(module.key);

                return (
                    <ModuleToggle
                        key={module.key}
                        label={module.label}
                        checked={inPlan && on.includes(module.key)}
                        disabled={!inPlan}
                        onChange={(checked) =>
                            update(
                                (d) => ({
                                    ...d,
                                    modules: {
                                        ...Object.fromEntries(
                                            planKeys.map((key) => [
                                                key,
                                                enabledModules(
                                                    d,
                                                    planKeys,
                                                ).includes(key),
                                            ]),
                                        ),
                                        [module.key]: checked,
                                    },
                                }),
                                ['modules'],
                            )
                        }
                        badge={
                            !inPlan && (
                                <span className="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    Not in {plan?.name}
                                </span>
                            )
                        }
                    />
                );
            })}
            {errors.modules && (
                <p role="alert" className="mt-2 text-[13px] text-destructive">
                    {errors.modules}
                </p>
            )}
        </div>
    );
}
