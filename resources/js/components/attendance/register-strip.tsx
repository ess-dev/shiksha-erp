import {
    CalendarOff,
    CircleCheck,
    Clock,
    Eye,
    Lock,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { formatCalendarDay, formatClock } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Register } from '@/types';

type Look = { icon: ReactNode; className: string; text: string };

function look(register: Register, sectionLabel: string): Look {
    const neutral = 'bg-muted/60 text-foreground';
    const warning =
        'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100';
    const success =
        'bg-green-50 text-green-900 dark:bg-green-950/40 dark:text-green-100';

    switch (register.state) {
        case 'closed':
            return {
                icon: <CalendarOff className="size-4" aria-hidden />,
                className: neutral,
                text: `No register on ${formatCalendarDay(register.date)}: ${register.closed_reason ?? 'the school is closed'}.`,
            };
        case 'view_only':
            return {
                icon: <Eye className="size-4" aria-hidden />,
                className: neutral,
                text: `View only. ${register.class_teacher_name ?? 'The class teacher'} marks the daily register for ${sectionLabel}.`,
            };
        case 'locked':
            return {
                icon: <Lock className="size-4" aria-hidden />,
                className: neutral,
                text: `Registers older than ${register.correction_window_days} days are locked. Ask someone who can correct attendance.`,
            };
        case 'saved': {
            const saved = register.saved;
            const by = saved?.saved_by ?? 'staff';
            const at = saved
                ? ` at ${formatClock(saved.saved_at, register.timezone)}`
                : '';
            const why = saved?.reason ? ` · reason: “${saved.reason}”` : '';
            const next = register.needs_reason
                ? '. Changes need a reason.'
                : '. You can still make changes today.';

            return {
                icon: <CircleCheck className="size-4" aria-hidden />,
                className: success,
                text: `Saved by ${by}${at}${why}${next}`,
            };
        }
        case 'pending':
            return {
                icon: <Clock className="size-4" aria-hidden />,
                className: warning,
                text: 'Not marked yet for today. The office sees this class as pending.',
            };
        case 'missed':
            return {
                icon: <TriangleAlert className="size-4" aria-hidden />,
                className: warning,
                text: 'This day was never marked. Saving now records it late, with a reason.',
            };
    }
}

/**
 * The one-line status above the register: closed day, view only, locked,
 * saved, pending or missed.
 */
export default function RegisterStrip({
    register,
    sectionLabel,
}: {
    register: Register;
    sectionLabel: string;
}) {
    const { icon, className, text } = look(register, sectionLabel);

    return (
        <div
            role="status"
            className={cn(
                'flex items-center gap-2 border-y px-4 py-2.5 text-sm',
                className,
            )}
        >
            {icon}
            <span>{text}</span>
        </div>
    );
}
