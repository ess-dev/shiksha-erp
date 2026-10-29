import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatCalendarDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/plan';
import type { PlanAndModules } from '@/types';

const STATUS_LABELS: Record<string, string> = {
    trial: 'Trial',
    active: 'Active',
    suspended: 'Suspended',
    expired: 'Expired',
};

const MODULE_STATE_LABELS: Record<string, string> = {
    in_plan: 'In plan',
    add_on: 'Add-on',
    turned_off: 'Turned off',
    not_in_plan: 'Not in your plan',
};

export default function Plan({ plan }: { plan: PlanAndModules }) {
    const endsOn =
        plan.status === 'trial' ? plan.trial_ends_on : plan.renews_on;
    const endsLabel = plan.status === 'trial' ? 'Trial ends on' : 'Renews on';

    return (
        <>
            <Head title="Plan and modules" />

            <h1 className="sr-only">Plan and modules</h1>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Your plan</CardTitle>
                                <CardDescription>
                                    Managed by Shiksha ERP. Contact support to
                                    change it.
                                </CardDescription>
                            </div>
                            <Badge
                                variant={
                                    plan.status === 'active' ||
                                    plan.status === 'trial'
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {STATUS_LABELS[plan.status] ?? plan.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <Fact label="Plan" value={plan.plan_name ?? '—'} />
                            <Fact
                                label={endsLabel}
                                value={
                                    endsOn ? formatCalendarDate(endsOn) : '—'
                                }
                            />
                            <Fact
                                label="Students"
                                value={String(plan.students)}
                            />
                        </dl>
                        {plan.plan_min_students !== null && (
                            <p className="mt-3 text-xs text-muted-foreground">
                                Billed for at least {plan.plan_min_students}{' '}
                                students.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Modules</CardTitle>
                        <CardDescription>
                            What {plan.school_name} can use today. Need
                            something that isn't listed as in plan? Ask Shiksha
                            ERP support to turn it on.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {plan.modules.map((module) => {
                                const available =
                                    module.state === 'in_plan' ||
                                    module.state === 'add_on';

                                return (
                                    <li
                                        key={module.key}
                                        className="flex items-center justify-between gap-3 py-2.5 text-sm"
                                    >
                                        <span
                                            className={cn(
                                                !available &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {module.label}
                                        </span>
                                        <Badge
                                            variant={
                                                available
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {MODULE_STATE_LABELS[
                                                module.state
                                            ] ?? module.state}
                                        </Badge>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md bg-muted px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-base font-medium">{value}</dd>
        </div>
    );
}

Plan.layout = {
    breadcrumbs: [
        {
            title: 'Plan and modules',
            href: show(),
        },
    ],
};
