import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Marks a feature whose module is not built yet. The parent screens keep the
 * prototype's shape, so the cards stay visible with sample values, clearly
 * tagged, and nothing in them is a real figure or does anything.
 */
export function NotBuiltBadge({ text = 'Not built yet' }: { text?: string }) {
    return (
        <Badge variant="outline" className="border-dashed text-[11px]">
            {text}
        </Badge>
    );
}

export function PlaceholderCard({
    title,
    children,
    className,
}: {
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            aria-label={`${title} (not built yet, sample values)`}
            className={cn(
                'rounded-xl border border-dashed bg-card p-4 shadow-xs',
                className,
            )}
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-[15px] font-semibold">{title}</h2>
                <NotBuiltBadge text="Not built yet · sample values" />
            </div>
            <div className="opacity-70" aria-hidden={false}>
                {children}
            </div>
        </section>
    );
}
