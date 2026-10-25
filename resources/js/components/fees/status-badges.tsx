import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { InvoiceStatus, PaymentStatus } from '@/types';

const INVOICE_LABEL: Record<InvoiceStatus, string> = {
    paid: 'Paid',
    overdue: 'Overdue',
    partial_overdue: 'Partly paid · overdue',
    partial: 'Partly paid',
    due: 'Due',
    upcoming: 'Upcoming',
};

const INVOICE_STYLE: Record<InvoiceStatus, string> = {
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    overdue: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    partial_overdue:
        'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    partial:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    due: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    upcoming: 'bg-muted text-foreground',
};

const PAYMENT_LABEL: Record<PaymentStatus, string> = {
    pending: 'Pending',
    success: 'Success',
    failed: 'Failed',
};

const PAYMENT_STYLE: Record<PaymentStatus, string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    success:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    failed: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

/** The status of one installment. The words carry the meaning, not the colour alone. */
export function InvoiceStatusBadge({ status }: { status: InvoiceStatus }) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'rounded-full border-transparent',
                INVOICE_STYLE[status],
            )}
        >
            {INVOICE_LABEL[status]}
        </Badge>
    );
}

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'rounded-full border-transparent',
                PAYMENT_STYLE[status],
            )}
        >
            {PAYMENT_LABEL[status]}
        </Badge>
    );
}
