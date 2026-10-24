import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ExamStatus } from '@/types';

const LABEL: Record<ExamStatus, string> = {
    scheduled: 'Scheduled',
    marking: 'Marking open',
    published: 'Results published',
    cancelled: 'Cancelled',
};

const STYLE: Record<ExamStatus, string> = {
    scheduled: 'bg-muted text-foreground',
    marking:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    published:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

export default function ExamStatusBadge({ status }: { status: ExamStatus }) {
    return (
        <Badge
            variant="secondary"
            className={cn('rounded-full border-transparent', STYLE[status])}
        >
            {LABEL[status]}
        </Badge>
    );
}
