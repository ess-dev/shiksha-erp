import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Marks a block whose module is not built yet. The owner chose to keep the
 * prototype's sample values visible, faded, with this badge on them.
 */
export function ComingSoonBadge() {
    return (
        <Badge
            variant="outline"
            className="border-dashed text-[11px] font-medium text-muted-foreground"
        >
            Coming soon
        </Badge>
    );
}

/**
 * A dashboard card: title, muted subtitle, an optional action on the right and
 * the body. `comingSoon` draws a dashed border, the badge and fades the body,
 * whose numbers are then sample values only.
 */
export function DashboardCard({
    title,
    subtitle,
    action,
    comingSoon = false,
    className,
    children,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    comingSoon?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-label={
                comingSoon ? `${title} (coming soon, sample values)` : title
            }
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs',
                comingSoon && 'border-dashed',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-[15px] font-semibold">{title}</h2>
                    {subtitle !== undefined && (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {comingSoon ? <ComingSoonBadge /> : action}
            </div>
            <div
                className={cn(
                    comingSoon && 'pointer-events-none opacity-50 select-none',
                )}
            >
                {children}
            </div>
        </section>
    );
}
