import type { DayState } from '@/types/parent';

/**
 * How one attendance day looks to a parent. Colour is never the only signal:
 * every state also has a word or letter.
 */
export type DayLook = {
    label: string;
    short: string;
    cell: string;
};

export const dayLook: Record<DayState, DayLook> = {
    present: {
        label: 'Present',
        short: 'P',
        cell: 'bg-green-100 text-green-900 dark:bg-green-950/50 dark:text-green-100',
    },
    absent: {
        label: 'Absent',
        short: 'A',
        cell: 'bg-red-100 text-red-900 dark:bg-red-950/50 dark:text-red-100',
    },
    late: {
        label: 'Late',
        short: 'Late',
        cell: 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100',
    },
    holiday: {
        label: 'Holiday',
        short: 'Hol',
        cell: 'bg-muted text-muted-foreground',
    },
    off: {
        label: 'Weekly day off',
        short: '',
        cell: 'text-muted-foreground',
    },
    upcoming: {
        label: 'Upcoming',
        short: '',
        cell: 'border border-dashed text-muted-foreground',
    },
    unmarked: {
        label: 'Not marked yet',
        short: '—',
        cell: 'border border-dashed text-muted-foreground',
    },
};
