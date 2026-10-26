import { formatCalendarDay } from '@/lib/format';

/** The accepted file types, for the file pickers (the server checks the content). */
export const HOMEWORK_ACCEPT =
    'image/*,application/pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt';

/**
 * "Due today", "Due tomorrow", "Due in 4 days", or "Closed Wed, 14 Oct" once
 * the date has passed. `daysLeft` comes from the server, so the school date
 * (not the browser's) decides.
 */
export function dueLabel(daysLeft: number, dueOn: string): string {
    if (daysLeft < 0) {
        return `Closed ${formatCalendarDay(dueOn)}`;
    }

    if (daysLeft === 0) {
        return 'Due today';
    }

    return daysLeft === 1 ? 'Due tomorrow' : `Due in ${daysLeft} days`;
}
