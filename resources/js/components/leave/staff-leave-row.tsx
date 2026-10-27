import { Paperclip } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatCalendarDay, initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LeaveStatus, StaffLeaveRequest } from '@/types';

const STATUS: Record<LeaveStatus, { label: string; tone: string }> = {
    pending: {
        label: 'Pending',
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    },
    recommended: { label: 'Recommended', tone: 'bg-primary/10 text-primary' },
    approved: {
        label: 'Approved',
        tone: 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
    },
    rejected: {
        label: 'Rejected',
        tone: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    },
    cancelled: { label: 'Cancelled', tone: 'bg-muted text-muted-foreground' },
};

/** Half-days (2 per day) as "1 day", "2 days" or "1.5 days". */
export function formatHalfDays(halfDays: number): string {
    const days = halfDays / 2;

    return `${days} ${days === 1 ? 'day' : 'days'}`;
}

export function formatLeaveRange(from: string, to: string): string {
    return from === to
        ? formatCalendarDay(from)
        : `${formatCalendarDay(from)} – ${formatCalendarDay(to)}`;
}

export function StatusBadge({ status }: { status: LeaveStatus }) {
    const known = STATUS[status];

    return (
        <span
            className={cn(
                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                known.tone,
            )}
        >
            {known.label}
        </span>
    );
}

/** One staff leave request: who, what, why and how it was decided. `actions` sits on the right. */
export default function StaffLeaveRow({
    item,
    showStaff,
    actions,
}: {
    item: StaffLeaveRequest;
    showStaff: boolean;
    actions?: ReactNode;
}) {
    return (
        <li className="flex flex-wrap items-start gap-3 px-4 py-4">
            {showStaff && (
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                    {initials(item.staff_name)}
                </span>
            )}
            <div className="min-w-56 grow">
                <div className="flex flex-wrap items-center gap-2">
                    {showStaff ? (
                        <>
                            <strong>{item.staff_name}</strong>
                            <span className="text-sm text-muted-foreground">
                                {item.staff_note}
                            </span>
                        </>
                    ) : (
                        <strong>{item.type_label}</strong>
                    )}
                    <StatusBadge status={item.status} />
                </div>
                <p className="text-sm">
                    {showStaff && <>{item.type_label} · </>}
                    {formatLeaveRange(item.from_on, item.to_on)} ·{' '}
                    <strong>
                        {item.half_days === 1
                            ? 'Half day'
                            : formatHalfDays(item.half_days)}
                    </strong>
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    “{item.reason}” · applied{' '}
                    {formatCalendarDay(item.applied_on)}
                    {item.attachment !== null && (
                        <>
                            {' · '}
                            <a
                                href={item.attachment.url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
                            >
                                <Paperclip className="size-3.5" aria-hidden />
                                {item.attachment.name}
                            </a>
                        </>
                    )}
                </p>
                {item.suggested_cover !== null && (
                    <p className="text-sm text-muted-foreground">
                        Suggested cover: {item.suggested_cover}
                    </p>
                )}
                {item.decided_by !== null && (
                    <p className="text-sm text-muted-foreground">
                        {item.status === 'approved' ? 'Approved' : 'Rejected'}{' '}
                        by {item.decided_by}
                        {item.cover_name !== null &&
                            ` · ${item.section_label ?? 'Register'} marked by ${item.cover_name}`}
                        {item.decision_note !== null &&
                            ` — “${item.decision_note}”`}
                    </p>
                )}
            </div>
            {actions}
        </li>
    );
}
