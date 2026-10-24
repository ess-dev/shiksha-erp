import type { ExamFormOptions } from '@/types';

function toUtc(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
}

function fromUtc(date: Date): string {
    return date.toISOString().slice(0, 10);
}

/** Adds days to a calendar date ("2026-10-15") without any time-zone shift. */
export function addDays(value: string, days: number): string {
    const date = toUtc(value);
    date.setUTCDate(date.getUTCDate() + days);

    return fromUtc(date);
}

export function daysBetween(from: string, to: string): number {
    return Math.round(
        (toUtc(to).getTime() - toUtc(from).getTime()) / 86_400_000,
    );
}

/** "in 18 days", "tomorrow", "today" or "started 3 days ago". */
export function relativeDays(today: string, value: string): string {
    const days = daysBetween(today, value);

    if (days === 0) {
        return 'today';
    }

    if (days === 1) {
        return 'tomorrow';
    }

    return days > 0 ? `in ${days} days` : `${-days} days ago`;
}

/** Whether the school is open that day, from its weekly days and holidays. */
export function isSchoolDay(options: ExamFormOptions, value: string): boolean {
    const weekday = toUtc(value).getUTCDay() || 7;

    return (
        options.working_days.includes(weekday) &&
        !options.holidays.some((holiday) => holiday.date === value)
    );
}

/** The first school day on or after the date (gives up after two months). */
export function nextSchoolDay(options: ExamFormOptions, value: string): string {
    let day = value;

    for (let i = 0; i < 60 && !isSchoolDay(options, day); i++) {
        day = addDays(day, 1);
    }

    return day;
}
