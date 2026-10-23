import { Link } from '@inertiajs/react';
import {
    CalendarClock,
    CircleCheck,
    ClipboardX,
    CreditCard,
    Inbox,
    Network,
    Receipt,
    TrendingDown,
    UserX,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    ComingSoonBadge,
    DashboardCard,
} from '@/components/dashboard/dashboard-card';
import { cn } from '@/lib/utils';
import { index as attendanceIndex } from '@/routes/attendance';
import type { AttendanceDashboard } from '@/types';

const tones = {
    info: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    neutral: 'bg-muted text-muted-foreground',
} as const;

type Item = {
    icon: LucideIcon;
    tone: keyof typeof tones;
    title: string;
    meta: string;
    href?: string;
};

/** What the prototype lists here from modules that are not built yet. */
const comingSoon: { icon: LucideIcon; title: string; meta: string }[] = [
    {
        icon: Network,
        title: 'Notes from coordinators',
        meta: 'Weekly updates sent to you',
    },
    {
        icon: Inbox,
        title: 'Approvals waiting',
        meta: 'Profile changes, long leave, report cards',
    },
    {
        icon: Receipt,
        title: 'Overdue fees',
        meta: 'Students with unpaid past-due installments',
    },
    {
        icon: CreditCard,
        title: 'Failed online payments',
        meta: 'Parents can retry from the app',
    },
    {
        icon: CalendarClock,
        title: 'Staff leave requests',
        meta: 'Leave awaiting your approval',
    },
];

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function Row({ item, faded = false }: { item: Item; faded?: boolean }) {
    const body = (
        <>
            <span
                className={cn(
                    'grid size-8 shrink-0 place-items-center rounded-lg',
                    tones[item.tone],
                )}
            >
                <item.icon className="size-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 grow">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span
                        className={cn(
                            'text-sm font-semibold',
                            faded && 'opacity-60',
                        )}
                    >
                        {item.title}
                    </span>
                    {faded && <ComingSoonBadge />}
                </div>
                <div
                    className={cn(
                        'text-xs text-muted-foreground',
                        faded && 'opacity-60',
                    )}
                >
                    {item.meta}
                </div>
            </div>
        </>
    );

    return (
        <li className="border-b py-3 last:border-b-0">
            {item.href === undefined ? (
                <div className="flex items-start gap-3">{body}</div>
            ) : (
                <Link
                    href={item.href}
                    className="flex items-start gap-3 hover:opacity-80"
                >
                    {body}
                </Link>
            )}
        </li>
    );
}

/**
 * "Needs attention": real attendance alerts first, then one row per alert
 * whose module is not built yet, tagged "Coming soon" and without numbers.
 */
export default function NeedsAttention({
    attendance,
}: {
    attendance: AttendanceDashboard | null;
}) {
    const items: Item[] = [];

    if (attendance !== null && attendance.unmarked_sections.length > 0) {
        const names = attendance.unmarked_sections;

        items.push({
            icon: ClipboardX,
            tone: 'warning',
            title: `${plural(names.length, 'section')} not marked yet`,
            meta:
                names.slice(0, 4).join(', ') +
                (names.length > 4 ? ` and ${names.length - 4} more` : ''),
            href: attendanceIndex().url,
        });
    }

    if (attendance !== null && attendance.absent > 0) {
        items.push({
            icon: UserX,
            tone: 'danger',
            title: `${plural(attendance.absent, 'student')} absent today`,
            meta: 'Parent alerts are not sent yet (messaging is not built)',
        });
    }

    if (attendance !== null && attendance.frequent_absent_count > 0) {
        const more =
            attendance.frequent_absent_count -
            attendance.frequent_absentees.length;

        items.push({
            icon: TrendingDown,
            tone: 'warning',
            title: `${plural(attendance.frequent_absent_count, 'student')} with frequent absences`,
            meta: `${attendance.frequent_absentees.join(', ')}${more > 0 ? ` and ${more} more` : ''} · ${attendance.frequent_absence_threshold}+ absences in ${attendance.frequent_absence_days} school days`,
        });
    }

    return (
        <DashboardCard
            title="Needs attention"
            subtitle="Updated as staff save data"
        >
            <ul>
                {items.length === 0 && (
                    <li className="flex items-center gap-3 border-b py-3 text-sm text-muted-foreground">
                        <CircleCheck
                            className="size-4 text-emerald-600"
                            aria-hidden="true"
                        />
                        All clear for attendance right now.
                    </li>
                )}
                {items.map((item) => (
                    <Row key={item.title} item={item} />
                ))}
                {comingSoon.map((item) => (
                    <Row
                        key={item.title}
                        item={{ ...item, tone: 'neutral' }}
                        faded
                    />
                ))}
            </ul>
        </DashboardCard>
    );
}
