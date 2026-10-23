import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { ComingSoonBadge } from '@/components/dashboard/dashboard-card';
import { cn } from '@/lib/utils';

const tones = {
    primary: 'bg-secondary text-primary',
    success:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
} as const;

/**
 * A KPI card from the prototype: label and icon, a large value, an optional
 * progress bar (0 to 100) and a foot line. `comingSoon` fades a sample value.
 */
export default function KpiCard({
    label,
    value,
    icon: Icon,
    tone = 'primary',
    bar,
    comingSoon = false,
    children,
}: {
    label: string;
    value: string;
    icon: LucideIcon;
    tone?: keyof typeof tones;
    bar?: number;
    comingSoon?: boolean;
    children: ReactNode;
}) {
    return (
        <article
            aria-label={
                comingSoon ? `${label} (coming soon, sample value)` : label
            }
            className={cn(
                'flex flex-col gap-1.5 rounded-xl border bg-card px-4 py-4 shadow-xs',
                comingSoon && 'border-dashed',
            )}
        >
            <div className="flex items-center justify-between gap-2 text-[13px] font-medium text-muted-foreground">
                <span>{label}</span>
                {comingSoon ? (
                    <ComingSoonBadge />
                ) : (
                    <span
                        className={cn(
                            'grid size-8 place-items-center rounded-[9px]',
                            tones[tone],
                        )}
                    >
                        <Icon className="size-[18px]" aria-hidden="true" />
                    </span>
                )}
            </div>
            <div
                className={cn(
                    'flex flex-col gap-1.5',
                    comingSoon && 'opacity-50 select-none',
                )}
            >
                <div className="text-[26px] leading-tight font-bold tracking-tight tabular-nums">
                    {value}
                </div>
                {bar !== undefined && (
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            className="block h-full rounded-full bg-primary"
                            style={{
                                width: `${Math.min(100, Math.max(0, bar))}%`,
                            }}
                        />
                    </div>
                )}
                <div className="text-[12.5px] text-muted-foreground">
                    {children}
                </div>
            </div>
        </article>
    );
}
