const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/**
 * Shows a calendar date ("2026-04-01") as "1 Apr 2026" by reading its parts.
 * It never goes through `Date`, so the day cannot shift with the time zone.
 */
export function formatCalendarDate(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (match === null) {
        return value;
    }

    const month = MONTHS[Number(match[2]) - 1];

    return month === undefined
        ? value
        : `${Number(match[3])} ${month} ${match[1]}`;
}

const rupees = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

/**
 * Shows integer paise as rupees with Indian digit grouping ("₹1,46,850").
 * Display only: money arithmetic stays on the server.
 */
export function formatPaise(paise: number): string {
    return rupees.format(paise / 100);
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * Shows a calendar date ("2026-10-15") as "Thu, 15 Oct". The weekday is read
 * from a UTC date built from the parts, so it cannot shift with the time zone.
 */
export function formatCalendarDay(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (match === null) {
        return value;
    }

    const weekday =
        WEEKDAYS[
            new Date(Date.UTC(+match[1], +match[2] - 1, +match[3])).getUTCDay()
        ];

    return `${weekday}, ${Number(match[3])} ${MONTHS[Number(match[2]) - 1]}`;
}
