import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';

export default function InitialsBadge({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary',
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}
