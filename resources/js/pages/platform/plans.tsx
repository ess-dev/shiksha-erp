import { Head } from '@inertiajs/react';
import { Check, Minus, Pencil } from 'lucide-react';
import { useState } from 'react';
import EditPlanDialog from '@/components/platform/edit-plan-dialog';
import { cn } from '@/lib/utils';
import { formatPaise } from '@/lib/format';
import type { PlatformModule, PlatformPlan } from '@/types';

type Props = {
    plans: PlatformPlan[];
    modules: PlatformModule[];
};

export default function PlatformPlans({ plans, modules }: Props) {
    const [editing, setEditing] = useState<string | null>(null);
    const editingPlan = plans.find((plan) => plan.ulid === editing);

    return (
        <>
            <Head title="Plans" />

            <div className="mb-5">
                <h2 className="text-xl font-semibold tracking-tight">
                    Plans and pricing
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    Per-student annual pricing with module entitlements. Schools
                    are billed on the higher of actual students or the plan
                    minimum.
                </p>
            </div>

            <section
                aria-label="Plans"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                {plans.map((plan) => (
                    <article
                        key={plan.ulid}
                        aria-labelledby={`plan-${plan.ulid}`}
                        className={cn(
                            'flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-xs',
                            plan.badge
                                ? 'border-2 border-primary'
                                : 'border-border',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h2
                                id={`plan-${plan.ulid}`}
                                className="text-lg font-semibold"
                            >
                                {plan.name}
                            </h2>
                            {plan.badge && (
                                <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-indigo-800">
                                    {plan.badge}
                                </span>
                            )}
                        </div>

                        <div className="text-[28px] leading-tight font-bold tracking-tight">
                            {formatPaise(plan.price_per_student_year_paise)}{' '}
                            <small className="text-[13px] font-normal text-muted-foreground">
                                per student / year
                            </small>
                        </div>

                        <p className="text-[13px] text-muted-foreground">
                            {plan.description} Minimum{' '}
                            {plan.min_students.toLocaleString('en-IN')}{' '}
                            students.
                        </p>

                        <ul className="grid gap-1.5 text-sm">
                            {modules.map((module) => {
                                const has = plan.module_keys.includes(
                                    module.key,
                                );

                                return (
                                    <li
                                        key={module.key}
                                        className={cn(
                                            'flex items-center gap-2.5',
                                            !has &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {has ? (
                                            <Check
                                                className="size-4 shrink-0 text-slate-700"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <Minus
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {module.label}
                                        <span className="sr-only">
                                            {has
                                                ? ' included'
                                                : ' not included'}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>

                        <div className="mt-1 flex items-center justify-between border-t border-border pt-3 text-[13px]">
                            <span>
                                {plan.schools_count === 1
                                    ? '1 school'
                                    : `${plan.schools_count} schools`}
                            </span>
                            <span
                                className="font-semibold"
                                title="Monthly revenue appears once schools have subscriptions"
                            >
                                MRR —
                            </span>
                        </div>

                        <button
                            type="button"
                            onClick={() => setEditing(plan.ulid)}
                            className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-input bg-card text-sm font-medium hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                            data-test={`edit-plan-${plan.name.toLowerCase()}`}
                        >
                            <Pencil className="size-4" aria-hidden="true" />
                            Edit plan
                        </button>
                    </article>
                ))}
            </section>

            <section
                aria-labelledby="matrix-title"
                className="mt-4 rounded-xl border border-border bg-card shadow-xs"
            >
                <div className="px-5 pt-4">
                    <h2 id="matrix-title" className="text-[15px] font-semibold">
                        Module matrix
                    </h2>
                    <p className="mt-0.5 text-[12.5px] text-muted-foreground">
                        What each plan includes
                    </p>
                </div>
                <div className="mt-3 overflow-x-auto pb-1.5">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted text-left text-xs tracking-wide text-slate-500 uppercase">
                                <th scope="col" className="px-5 py-3">
                                    Module
                                </th>
                                {plans.map((plan) => (
                                    <th
                                        key={plan.ulid}
                                        scope="col"
                                        className="px-5 py-3 text-center"
                                    >
                                        {plan.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {modules.map((module) => (
                                <tr
                                    key={module.key}
                                    className="border-t border-border"
                                >
                                    <td className="px-5 py-3.5">
                                        {module.label}
                                    </td>
                                    {plans.map((plan) => (
                                        <td
                                            key={plan.ulid}
                                            className="px-5 py-3.5 text-center"
                                        >
                                            {plan.module_keys.includes(
                                                module.key,
                                            ) ? (
                                                <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                                    Included
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            <tr className="border-t border-border font-semibold">
                                <td className="px-5 py-3.5">
                                    Price per student / year
                                </td>
                                {plans.map((plan) => (
                                    <td
                                        key={plan.ulid}
                                        className="px-5 py-3.5 text-center"
                                    >
                                        {formatPaise(
                                            plan.price_per_student_year_paise,
                                        )}
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {editingPlan && (
                <EditPlanDialog
                    key={editingPlan.ulid}
                    plan={editingPlan}
                    modules={modules}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

PlatformPlans.layout = {
    title: 'Plans',
};
