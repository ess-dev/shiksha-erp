import { Plus, Trash2 } from 'lucide-react';
import Field from '@/components/platform/onboarding/field';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function CampusStep({
    draft,
    options,
    update,
    errors,
}: StepProps) {
    const plan = options.plans.find((p) => p.ulid === draft.subscription.plan);
    const lacksMultiCampus =
        draft.campuses.length > 1 &&
        plan !== undefined &&
        !plan.module_keys.includes('multi_campus');

    const change = (index: number, key: 'name' | 'address', value: string) =>
        update(
            (d) => ({
                ...d,
                campuses: d.campuses.map((campus, i) =>
                    i === index ? { ...campus, [key]: value } : campus,
                ),
            }),
            [`campuses.${index}.${key}`, 'campuses'],
        );

    return (
        <div>
            {draft.campuses.map((campus, index) => (
                <div
                    key={index}
                    className={
                        index > 0
                            ? 'mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-2'
                            : 'grid gap-4 sm:grid-cols-2'
                    }
                >
                    <Field
                        id={`campus-name-${index}`}
                        label={`Campus ${index + 1} name`}
                        error={errors[`campuses.${index}.name`]}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                value={campus.name}
                                maxLength={80}
                                onChange={(event) =>
                                    change(index, 'name', event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        id={`campus-address-${index}`}
                        label="Address"
                        error={errors[`campuses.${index}.address`]}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                value={campus.address}
                                maxLength={200}
                                placeholder="Area, city"
                                onChange={(event) =>
                                    change(index, 'address', event.target.value)
                                }
                            />
                        )}
                    </Field>
                    {index > 0 && (
                        <div className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    update((d) => ({
                                        ...d,
                                        campuses: d.campuses.filter(
                                            (_, i) => i !== index,
                                        ),
                                    }))
                                }
                            >
                                <Trash2 aria-hidden="true" />
                                Remove campus
                            </Button>
                        </div>
                    )}
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-4"
                disabled={draft.campuses.length >= 20}
                onClick={() =>
                    update((d) => ({
                        ...d,
                        campuses: [...d.campuses, { name: '', address: '' }],
                    }))
                }
            >
                <Plus aria-hidden="true" />
                Add another campus
            </Button>

            {errors.campuses && (
                <p role="alert" className="mt-2 text-[13px] text-destructive">
                    {errors.campuses}
                </p>
            )}

            {lacksMultiCampus && (
                <p className="mt-3 text-[13px] text-amber-700">
                    Multi-campus isn’t in the {plan?.name} plan. Choose Growth
                    or Premium, or add it as a module later.
                </p>
            )}
        </div>
    );
}
