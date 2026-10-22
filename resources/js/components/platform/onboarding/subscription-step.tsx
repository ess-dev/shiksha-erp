import Field, { selectClass } from '@/components/platform/onboarding/field';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Input } from '@/components/ui/input';
import { formatPaise } from '@/lib/format';
import { cn } from '@/lib/utils';

export default function SubscriptionStep({
    draft,
    options,
    update,
    errors,
}: StepProps) {
    const selected = options.plans.find(
        (plan) => plan.ulid === draft.subscription.plan,
    );
    const expected = Math.round(Number(draft.subscription.expected_students));
    // Display only: billing is on the higher of actual students or the plan minimum.
    const estimate =
        selected && expected >= 1
            ? Math.max(expected, selected.min_students) *
              selected.price_per_student_year_paise
            : null;

    return (
        <div>
            <fieldset>
                <legend className="sr-only">Plan</legend>
                <div className="grid gap-3 md:grid-cols-3">
                    {options.plans.map((plan) => {
                        const checked = plan.ulid === draft.subscription.plan;

                        return (
                            <label
                                key={plan.ulid}
                                className={cn(
                                    'flex cursor-pointer flex-col gap-1.5 rounded-xl border p-4 focus-within:ring-[3px] focus-within:ring-ring/40',
                                    checked
                                        ? 'border-2 border-primary bg-secondary/60'
                                        : 'border-border',
                                )}
                            >
                                <span className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="plan"
                                        className="accent-primary"
                                        checked={checked}
                                        onChange={() =>
                                            update(
                                                (d) => ({
                                                    ...d,
                                                    modules: null,
                                                    subscription: {
                                                        ...d.subscription,
                                                        plan: plan.ulid,
                                                    },
                                                }),
                                                [
                                                    'subscription.plan',
                                                    'modules',
                                                ],
                                            )
                                        }
                                    />
                                    <strong>{plan.name}</strong>
                                </span>
                                <span className="text-[13px] text-muted-foreground">
                                    {formatPaise(
                                        plan.price_per_student_year_paise,
                                    )}{' '}
                                    per student / year · min {plan.min_students}
                                </span>
                                <span className="text-[13px]">
                                    {plan.module_keys.length} modules
                                </span>
                            </label>
                        );
                    })}
                </div>
                {errors['subscription.plan'] && (
                    <p
                        role="alert"
                        className="mt-2 text-[13px] text-destructive"
                    >
                        {errors['subscription.plan']}
                    </p>
                )}
            </fieldset>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <Field
                    id="sub-students"
                    label="Expected students"
                    hint={
                        estimate === null
                            ? undefined
                            : `Annual estimate: ${formatPaise(estimate)}`
                    }
                    error={errors['subscription.expected_students']}
                >
                    {(props) => (
                        <Input
                            {...props}
                            type="number"
                            min={1}
                            step={1}
                            value={draft.subscription.expected_students}
                            onChange={(event) =>
                                update(
                                    (d) => ({
                                        ...d,
                                        subscription: {
                                            ...d.subscription,
                                            expected_students:
                                                event.target.value,
                                        },
                                    }),
                                    ['subscription.expected_students'],
                                )
                            }
                        />
                    )}
                </Field>

                <Field
                    id="sub-start"
                    label="Start with"
                    error={errors['subscription.start']}
                >
                    {(props) => (
                        <select
                            {...props}
                            className={selectClass}
                            value={draft.subscription.start}
                            onChange={(event) =>
                                update(
                                    (d) => ({
                                        ...d,
                                        subscription: {
                                            ...d.subscription,
                                            start:
                                                event.target.value === 'paid'
                                                    ? 'paid'
                                                    : 'trial',
                                        },
                                    }),
                                    ['subscription.start'],
                                )
                            }
                        >
                            <option value="trial">
                                {options.trial_days}-day free trial
                            </option>
                            <option value="paid">Paid subscription now</option>
                        </select>
                    )}
                </Field>
            </div>
        </div>
    );
}
