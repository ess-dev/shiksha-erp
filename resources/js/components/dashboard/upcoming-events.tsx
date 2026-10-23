import { Badge } from '@/components/ui/badge';
import {
    ComingSoonBadge,
    DashboardCard,
} from '@/components/dashboard/dashboard-card';
import { formatCalendarDate, formatCalendarDay } from '@/lib/format';
import type { UpcomingHoliday } from '@/types';

function when(days: number): string {
    if (days === 0) {
        return 'today';
    }

    return days === 1 ? 'tomorrow' : `in ${days} days`;
}

/**
 * The next 30 days. Only holidays exist so far; meetings, exams and events
 * arrive with their modules.
 */
export default function UpcomingEvents({
    holidays,
}: {
    holidays: UpcomingHoliday[];
}) {
    return (
        <DashboardCard title="Upcoming events" subtitle="Next 30 days">
            <ul>
                {holidays.length === 0 && (
                    <li className="border-b py-3 text-sm text-muted-foreground">
                        No holidays in the next 30 days.
                    </li>
                )}
                {holidays.map((holiday) => {
                    const [day, month] = formatCalendarDate(
                        holiday.starts_on,
                    ).split(' ');

                    return (
                        <li
                            key={holiday.starts_on}
                            className="flex items-center gap-3 border-b py-3"
                        >
                            <span
                                aria-hidden="true"
                                className="flex w-10 shrink-0 flex-col items-center rounded-lg border py-1"
                            >
                                <span className="text-[10px] font-semibold text-primary uppercase">
                                    {month}
                                </span>
                                <span className="text-base leading-tight font-bold">
                                    {day}
                                </span>
                            </span>
                            <div className="min-w-0 grow">
                                <div className="text-sm font-semibold">
                                    {holiday.name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatCalendarDay(holiday.starts_on)}
                                    {holiday.ends_on !== null &&
                                        ` – ${formatCalendarDate(holiday.ends_on).replace(/ \d{4}$/, '')}`}
                                    {' · '}
                                    {when(holiday.days_away)}
                                </div>
                            </div>
                            <Badge variant="secondary">Holiday</Badge>
                        </li>
                    );
                })}
                <li className="flex items-center gap-3 py-3">
                    <div className="grow text-xs text-muted-foreground">
                        Meetings, exams and school events
                    </div>
                    <ComingSoonBadge />
                </li>
            </ul>
        </DashboardCard>
    );
}
