/**
 * Days from `today` to `date` (both "YYYY-MM-DD"), read from the parts so the
 * result cannot shift with the browser's time zone.
 */
export function daysBetween(today: string, date: string): number {
    const parse = (value: string): number => {
        const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

        return match === null
            ? 0
            : Date.UTC(+match[1], +match[2] - 1, +match[3]) / 86_400_000;
    };

    return Math.round(parse(date) - parse(today));
}

/** "92 days ago", "today", "in 5 days". */
export function relativeDays(today: string, date: string): string {
    const days = daysBetween(today, date);

    if (days === 0) {
        return 'today';
    }

    const unit = Math.abs(days) === 1 ? 'day' : 'days';

    return days < 0 ? `${-days} ${unit} ago` : `in ${days} ${unit}`;
}

/** "Term 2 (Jul–Sep)" becomes ["Term 2", "(Jul–Sep)"]. */
export function splitInstallment(label: string): [string, string] {
    const open = label.indexOf('(');

    return open < 0
        ? [label, '']
        : [label.slice(0, open).trim(), label.slice(open)];
}

/** Whole rupees from what a person typed, or null when it is not a whole number. */
export function parseRupees(text: string): number | null {
    return /^\d{1,9}$/.test(text.trim()) ? Number(text.trim()) : null;
}
