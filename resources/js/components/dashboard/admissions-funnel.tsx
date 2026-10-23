import { Link } from '@inertiajs/react';
import { DashboardCard } from '@/components/dashboard/dashboard-card';
import { cn } from '@/lib/utils';
import { index as admissionsIndex } from '@/routes/admissions';
import type { AdmissionFunnel as Funnel } from '@/types';

/**
 * The admissions funnel: everyone who reached at least each stage, and the
 * enquiry-to-enrolled conversion.
 */
export default function AdmissionsFunnel({ funnel }: { funnel: Funnel }) {
    const max = Math.max(1, funnel.stages[0]?.count ?? 0);
    const last = funnel.stages.length - 1;

    return (
        <DashboardCard
            title="Admissions funnel"
            subtitle="Current admission cycle"
            action={
                <Link
                    href={admissionsIndex()}
                    className="text-sm font-medium text-primary hover:underline"
                >
                    Open board
                </Link>
            }
        >
            <div className="grid gap-3">
                {funnel.stages.map((stage, index) => (
                    <div
                        key={stage.stage}
                        className="grid grid-cols-[7.5rem_minmax(0,1fr)_1.75rem] items-center gap-3"
                    >
                        <span className="text-xs text-muted-foreground">
                            {stage.label}
                        </span>
                        <div
                            className="h-5 overflow-hidden rounded-md bg-muted"
                            role="img"
                            aria-label={`${stage.label}: ${stage.count}`}
                        >
                            <span
                                className={cn(
                                    'block h-full rounded-md',
                                    index === last
                                        ? 'bg-primary'
                                        : 'bg-primary/40',
                                )}
                                style={{
                                    width: `${(stage.count / max) * 100}%`,
                                }}
                            />
                        </div>
                        <span className="text-right text-sm font-semibold tabular-nums">
                            {stage.count}
                        </span>
                    </div>
                ))}
                <p className="text-xs text-muted-foreground">
                    Conversion enquiry → enrolled:{' '}
                    <strong className="text-primary">
                        {funnel.conversion_percent}%
                    </strong>
                </p>
            </div>
        </DashboardCard>
    );
}
