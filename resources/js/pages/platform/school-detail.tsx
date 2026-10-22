import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Lock, Unlock } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import ModuleToggle from '@/components/platform/module-toggle';
import { Button } from '@/components/ui/button';
import {
    formatCalendarDate,
    formatCalendarMonth,
    formatClock,
    formatPaise,
    initials,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    modules as modulesRoute,
    index as schoolsIndex,
    plan as planRoute,
    reactivate,
    retryDeletion,
    suspend,
} from '@/routes/platform/schools';
import type { ModuleBadge, PlatformSchoolDetail, SchoolStatus } from '@/types';

type Props = {
    school: PlatformSchoolDetail;
};

const statusStyles: Record<SchoolStatus, string> = {
    active: 'bg-green-100 text-green-800',
    trial: 'bg-indigo-100 text-indigo-800',
    suspended: 'bg-red-100 text-red-800',
    expired: 'bg-slate-100 text-slate-700',
    deleting: 'bg-red-100 text-red-800',
};

const badgeText: Record<ModuleBadge, string> = {
    in_plan: 'In plan',
    add_on: 'Add-on',
    turned_off: 'Turned off',
};

function Card({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle?: string;
    children: React.ReactNode;
}) {
    return (
        <article className="rounded-xl border border-border bg-card shadow-xs">
            <div className="px-5 pt-4">
                <h2 className="text-[15px] font-semibold">{title}</h2>
                {subtitle && (
                    <p className="mt-0.5 text-[13px] text-muted-foreground">
                        {subtitle}
                    </p>
                )}
            </div>
            <div className="px-5 pt-3 pb-5">{children}</div>
        </article>
    );
}

function Fact({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-[13px] text-muted-foreground">{label}</dt>
            <dd className="font-semibold">{children}</dd>
        </div>
    );
}

type Dialogs = 'suspend' | 'reactivate' | { plan: string } | null;

export default function PlatformSchoolDetail({ school }: Props) {
    const [dialog, setDialog] = useState<Dialogs>(null);
    const [reason, setReason] = useState(
        school.suspension_reasons[0]?.key ?? '',
    );
    const [processing, setProcessing] = useState(false);

    const canSuspend = school.status === 'active' || school.status === 'trial';
    const pendingPlan =
        dialog !== null && typeof dialog === 'object'
            ? school.plans.find((plan) => plan.ulid === dialog.plan)
            : undefined;

    const send = (
        method: 'post' | 'patch',
        url: string,
        data: Record<string, string | boolean>,
    ) => {
        setProcessing(true);
        router[method](url, data, {
            preserveScroll: true,
            onSuccess: () => setDialog(null),
            onFinish: () => setProcessing(false),
        });
    };

    const place = [school.city, school.state].filter(Boolean).join(', ');
    const subtitle = [
        place,
        school.board,
        `${school.campuses} ${school.campuses === 1 ? 'campus' : 'campuses'}`,
        school.since_on
            ? `customer since ${formatCalendarMonth(school.since_on)}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    const minimum = school.plan_min_students;
    const usagePct =
        minimum === null || minimum === 0
            ? 0
            : Math.min(100, (school.students / minimum) * 100);

    return (
        <>
            <Head title={school.name} />

            <p className="mb-4 text-sm">
                <Link
                    href={schoolsIndex()}
                    className="inline-flex items-center gap-1 text-primary hover:underline"
                >
                    <ArrowLeft className="size-3.5" aria-hidden="true" />
                    All schools
                </Link>
            </p>

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-center gap-4">
                    <span
                        aria-hidden="true"
                        className="grid size-14 shrink-0 place-items-center rounded-2xl bg-secondary text-xl font-bold text-indigo-800"
                    >
                        {initials(school.name)}
                    </span>
                    <div>
                        <h2 className="flex flex-wrap items-center gap-2 text-xl font-semibold tracking-tight">
                            {school.name}
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize',
                                    statusStyles[school.status],
                                )}
                            >
                                {school.status}
                            </span>
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            {subtitle}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canSuspend && (
                        <Button
                            variant="outline"
                            onClick={() => setDialog('suspend')}
                        >
                            <Lock aria-hidden="true" />
                            Suspend
                        </Button>
                    )}
                    {school.status === 'suspended' && (
                        <Button onClick={() => setDialog('reactivate')}>
                            <Unlock aria-hidden="true" />
                            Reactivate
                        </Button>
                    )}
                </div>
            </div>

            {school.status === 'suspended' && (
                <div
                    role="status"
                    className="mb-4 flex items-center gap-2 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800"
                >
                    <Lock className="size-4 shrink-0" aria-hidden="true" />
                    Suspended: {school.suspended_reason}. Staff and parents see
                    a “Subscription suspended” screen; data is kept.
                </div>
            )}

            {school.status === 'deleting' && (
                <div
                    role="status"
                    className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800"
                >
                    <span>
                        This school is being deleted and nobody can open it. If
                        it stays here, run the removal again.
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.post(
                                retryDeletion.url(school.ulid),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Retry delete
                    </Button>
                </div>
            )}

            {school.status === 'trial' && school.trial_ends_on && (
                <div
                    role="status"
                    className="mb-4 rounded-xl bg-secondary px-4 py-3 text-sm text-indigo-800"
                >
                    Trial ends {formatCalendarDate(school.trial_ends_on)}.
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-3">
                <Card title="Plan">
                    <div className="grid gap-1.5">
                        <label htmlFor="school-plan" className="text-sm">
                            Current plan
                        </label>
                        <select
                            id="school-plan"
                            value={school.plan_ulid ?? ''}
                            onChange={(event) =>
                                event.target.value &&
                                event.target.value !== school.plan_ulid &&
                                setDialog({ plan: event.target.value })
                            }
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {school.plan_ulid === null && (
                                <option value="">No plan</option>
                            )}
                            {school.plans.map((plan) => (
                                <option key={plan.ulid} value={plan.ulid}>
                                    {plan.name} ·{' '}
                                    {formatPaise(
                                        plan.price_per_student_year_paise,
                                    )}{' '}
                                    per student / year
                                </option>
                            ))}
                        </select>
                    </div>
                    <dl className="mt-4 grid gap-3">
                        <Fact label="Billable students">
                            {school.billable_students.toLocaleString('en-IN')}
                            {minimum !== null && school.students < minimum
                                ? ' (plan minimum)'
                                : ''}
                        </Fact>
                        <Fact label="Annual value">
                            {school.annual_paise === null
                                ? '—'
                                : formatPaise(school.annual_paise)}
                        </Fact>
                        <Fact label="MRR">
                            {school.mrr_paise > 0
                                ? formatPaise(school.mrr_paise)
                                : '— (not active)'}
                        </Fact>
                        <Fact label="Renews">
                            {school.renews_on
                                ? formatCalendarDate(school.renews_on)
                                : '—'}
                        </Fact>
                    </dl>
                </Card>

                <Card
                    title="Modules"
                    subtitle="Off modules disappear for staff and parents"
                >
                    <div>
                        {school.modules.map((module) => (
                            <ModuleToggle
                                key={module.key}
                                label={module.label}
                                checked={module.enabled}
                                onChange={(enabled) =>
                                    router.patch(
                                        modulesRoute.url(school.ulid),
                                        { module: module.key, enabled },
                                        { preserveScroll: true },
                                    )
                                }
                                badge={
                                    module.badge && (
                                        <span
                                            className={cn(
                                                'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                module.badge === 'in_plan'
                                                    ? 'bg-muted text-slate-700'
                                                    : 'bg-amber-100 text-amber-800',
                                            )}
                                        >
                                            {badgeText[module.badge]}
                                        </span>
                                    )
                                }
                            />
                        ))}
                    </div>
                </Card>

                <div className="self-start">
                    <Card title="Usage">
                        <div className="flex justify-between text-sm">
                            <span>Students</span>
                            <span className="font-semibold">
                                {school.students.toLocaleString('en-IN')}
                                {minimum !== null &&
                                    ` / ${minimum.toLocaleString('en-IN')} minimum`}
                            </span>
                        </div>
                        <div
                            className="mt-1.5 h-2 overflow-hidden rounded-full bg-secondary"
                            role="progressbar"
                            aria-label="Students against plan minimum"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(usagePct)}
                        >
                            <span
                                className="block h-full rounded-full bg-primary"
                                style={{ width: `${usagePct}%` }}
                            />
                        </div>
                        <p className="mt-4 text-[13px] text-muted-foreground">
                            Active users, SMS, file storage and last-active
                            appear here once usage tracking is built.
                        </p>
                    </Card>
                </div>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card title="Billing" subtitle="Invoices">
                    <p className="text-sm text-muted-foreground">
                        No invoices yet. Billing is not built.
                    </p>
                </Card>

                <Card title="Contact and activity">
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Fact label="Primary contact">
                            {school.contact_name ?? '—'}
                        </Fact>
                        <Fact label="Email">{school.contact_email ?? '—'}</Fact>
                    </dl>
                    <h3 className="mt-5 mb-2 text-sm font-semibold">
                        Recent platform actions
                    </h3>
                    {school.activity.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No platform actions yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border text-sm">
                            {school.activity.map((entry, index) => (
                                <li
                                    key={index}
                                    className="flex justify-between gap-3 py-1.5"
                                >
                                    <span>{entry.summary}</span>
                                    <span className="shrink-0 text-muted-foreground">
                                        {formatClock(entry.at, school.timezone)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            {dialog === 'suspend' && (
                <ConfirmDialog
                    title={`Suspend ${school.name}?`}
                    description="Every staff member, parent and student of this school will see a “Subscription suspended” screen until you reactivate. No data is deleted."
                    confirmLabel="Suspend school"
                    destructive
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        send('post', suspend.url(school.ulid), { reason })
                    }
                >
                    <div className="grid gap-1.5">
                        <label htmlFor="suspend-reason" className="text-sm">
                            Reason (shown to the school)
                        </label>
                        <select
                            id="suspend-reason"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm text-foreground shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {school.suspension_reasons.map((option) => (
                                <option key={option.key} value={option.key}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </ConfirmDialog>
            )}

            {dialog === 'reactivate' && (
                <ConfirmDialog
                    title={`Reactivate ${school.name}?`}
                    description="Staff and parents get access again straight away."
                    confirmLabel="Reactivate"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        send('post', reactivate.url(school.ulid), {})
                    }
                />
            )}

            {pendingPlan && (
                <ConfirmDialog
                    title={`Move to ${pendingPlan.name}?`}
                    description="Modules change immediately and any add-ons or switched-off modules are reset to the plan. Billing adjustments are not built yet."
                    confirmLabel="Change plan"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        send('patch', planRoute.url(school.ulid), {
                            plan: pendingPlan.ulid,
                        })
                    }
                />
            )}
        </>
    );
}

PlatformSchoolDetail.layout = {
    title: 'School details',
};
