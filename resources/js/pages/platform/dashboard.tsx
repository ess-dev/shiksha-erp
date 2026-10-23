import { Head } from '@inertiajs/react';
import { Building2, Layers } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import StatusDoughnut from '@/components/platform/status-doughnut';
import { formatCalendarDay } from '@/lib/format';
import type { PlatformOverview } from '@/types';

type Props = {
    overview: PlatformOverview;
};

/** Status colours as in the prototype: green, indigo, red, grey. */
const statuses = [
    { key: 'active', label: 'Active', color: '#16a34a' },
    { key: 'trial', label: 'Trial', color: '#4f46e5' },
    { key: 'suspended', label: 'Suspended', color: '#dc2626' },
    { key: 'expired', label: 'Expired', color: '#94a3b8' },
] as const;

function Kpi({
    label,
    value,
    icon: Icon,
    children,
}: {
    label: string;
    value: number;
    icon: LucideIcon;
    children: React.ReactNode;
}) {
    return (
        <article className="flex flex-col gap-1.5 rounded-xl border border-border bg-card px-[18px] py-4 shadow-xs">
            <div className="flex items-center justify-between text-[13px] font-medium text-slate-600">
                <span>{label}</span>
                <span className="grid size-8 place-items-center rounded-[9px] bg-secondary text-primary">
                    <Icon className="size-[18px]" aria-hidden="true" />
                </span>
            </div>
            <div className="text-[26px] leading-tight font-bold tracking-tight tabular-nums">
                {value}
            </div>
            <div className="text-[12.5px] text-muted-foreground">
                {children}
            </div>
        </article>
    );
}

export default function PlatformDashboard({ overview }: Props) {
    const by = overview.schools_by_status;
    const total = overview.schools_total;

    return (
        <>
            <Head title="Dashboard" />

            <div className="mb-5">
                <h2 className="text-xl font-semibold tracking-tight">
                    Platform overview
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    {formatCalendarDay(overview.today)} ·{' '}
                    {total === 1
                        ? '1 school on the platform'
                        : `${total} schools on the platform`}
                </p>
            </div>

            <section
                aria-label="Platform totals"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <Kpi label="Schools" value={total} icon={Building2}>
                    {statuses.map((status, index) => (
                        <span key={status.key}>
                            {index > 0 && ' · '}
                            <strong className="font-semibold text-slate-600">
                                {by[status.key]}
                            </strong>{' '}
                            {status.label.toLowerCase()}
                        </span>
                    ))}
                </Kpi>
                <Kpi
                    label="Plans"
                    value={overview.plan_names.length}
                    icon={Layers}
                >
                    {overview.plan_names.join(' · ') || 'No plans yet'}
                </Kpi>
            </section>

            <section className="mt-4 grid gap-4 lg:grid-cols-3">
                <article
                    aria-labelledby="status-title"
                    className="rounded-xl border border-border bg-card shadow-xs"
                >
                    <div className="px-5 pt-4">
                        <h2
                            id="status-title"
                            className="text-[15px] font-semibold"
                        >
                            Subscriptions by status
                        </h2>
                    </div>
                    <div className="px-5 pt-3 pb-5">
                        <StatusDoughnut
                            label={`Schools by subscription status: ${statuses
                                .map((s) => `${by[s.key]} ${s.label}`)
                                .join(', ')}`}
                            slices={statuses.map((status) => ({
                                label: status.label,
                                value: by[status.key],
                                color: status.color,
                            }))}
                        />
                    </div>
                </article>
            </section>
        </>
    );
}

PlatformDashboard.layout = {
    title: 'Dashboard',
};
