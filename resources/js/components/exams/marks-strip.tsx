import { CircleCheck, Eye, Lock, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatCalendarDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MarksSheet } from '@/types';

/**
 * The coloured line above the marks table that says what the person can do
 * with this sheet, as in the prototype.
 */
export default function MarksStrip({ sheet }: { sheet: MarksSheet }) {
    let icon: ReactNode;
    let className: string;
    let text: ReactNode;

    switch (sheet.state) {
        case 'published':
            icon = <CircleCheck className="size-4" aria-hidden />;
            className =
                'bg-green-50 text-green-900 dark:bg-green-950/40 dark:text-green-100';
            text = (
                <>
                    Published to parents
                    {sheet.published_on !== null &&
                        ` on ${formatCalendarDate(sheet.published_on)}`}
                    {sheet.published_by !== null && ` by ${sheet.published_by}`}
                    . Report cards stay as published; ask the principal to
                    reopen marks if a correction is needed.
                </>
            );
            break;
        case 'closed':
            icon = <Lock className="size-4" aria-hidden />;
            className = 'bg-muted/60 text-foreground';
            text = 'Marking for this exam is closed.';
            break;
        case 'view_only':
            icon = <Eye className="size-4" aria-hidden />;
            className = 'bg-muted/60 text-foreground';
            text = `View only. ${sheet.teacher_name ?? 'The subject teacher'} enters ${sheet.subject_name} marks for this class.`;
            break;
        default:
            icon = <Pencil className="size-4" aria-hidden />;
            className =
                'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100';
            text = (
                <>
                    {sheet.reopened_by !== null &&
                        `Reopened by ${sheet.reopened_by}${sheet.reopen_reason ? ` (“${sheet.reopen_reason}”)` : ''}. `}
                    Not published yet. Parents see these marks only after you
                    publish. Type <strong className="mx-0.5">AB</strong> for a
                    student who was absent.
                </>
            );
    }

    return (
        <div
            className={cn(
                'flex items-start gap-2 border-y px-4 py-2.5 text-sm',
                className,
            )}
        >
            <span className="mt-0.5">{icon}</span>
            <p>{text}</p>
        </div>
    );
}
