import { cn } from '@/lib/utils';

/**
 * A grade such as "A1" in a small pill; the lowest (failing) grade and
 * absences are shown in red, with the text itself carrying the meaning.
 */
export default function GradePill({
    grade,
    low = false,
    className,
}: {
    grade: string;
    low?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex min-w-9 items-center justify-center rounded-md px-1.5 py-0.5 text-xs font-semibold',
                low
                    ? 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300'
                    : 'bg-primary/10 text-primary',
                className,
            )}
        >
            {grade}
        </span>
    );
}
