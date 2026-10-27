import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatInstantLong, formatPaise } from '@/lib/format';
import type { PaymentDetail } from '@/types';

function Item({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{children}</dd>
        </div>
    );
}

/** Everything stored about one payment, and which installments it went to. */
export default function PaymentDetailsDialog({
    detail,
    timezone,
    onClose,
}: {
    detail: PaymentDetail;
    timezone: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] gap-0 overflow-y-auto p-0 sm:max-w-xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Payment details</DialogTitle>
                    <DialogDescription className="sr-only">
                        How and when this payment was made and what it paid for.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-5 px-6 py-5">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                        <Item label="Payment ID">
                            <span className="font-mono text-xs">
                                {detail.payment_id}
                            </span>
                        </Item>
                        <Item label="Status">
                            <span className="capitalize">{detail.status}</span>
                        </Item>
                        <Item label="Mode">
                            {detail.mode_label} (
                            {detail.is_counter ? 'counter' : 'online'})
                        </Item>
                        <Item label="Gateway / channel">
                            {detail.gateway ?? '—'}
                        </Item>
                        <Item label="Reference">{detail.reference ?? '—'}</Item>
                        <Item label="Receipt">
                            {detail.receipt_number ?? '—'}
                        </Item>
                        <Item label="Recorded">
                            {formatInstantLong(detail.paid_at, timezone)}
                        </Item>
                        <Item label="Collected by">
                            {detail.collected_by ?? '—'}
                        </Item>
                        <Item label="Note">{detail.note ?? '—'}</Item>
                        {detail.failure_reason !== null && (
                            <Item label="Failure reason">
                                {detail.failure_reason}
                            </Item>
                        )}
                        {detail.cheque_details !== null && (
                            <Item label="Cheque">{detail.cheque_details}</Item>
                        )}
                    </dl>

                    {detail.excess_paise > 0 && (
                        <p
                            role="status"
                            className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            {formatPaise(detail.excess_paise)} of this payment
                            could not be applied because the installment was
                            already paid. It is due back to the family and was
                            added to Refunds as a request.
                        </p>
                    )}

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-y text-xs text-muted-foreground">
                                <th
                                    scope="col"
                                    className="py-2 text-left font-medium"
                                >
                                    Applied to
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {detail.lines.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={2}
                                        className="py-2 text-muted-foreground"
                                    >
                                        Not applied to any installment
                                    </td>
                                </tr>
                            ) : (
                                detail.lines.map((line, index) => (
                                    <tr key={index} className="border-b">
                                        <td className="py-2">
                                            {line.student_name} ·{' '}
                                            {line.installment_label}
                                        </td>
                                        <td className="py-2 text-right">
                                            {formatPaise(line.amount_paise)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <DialogFooter className="border-t px-6 py-4">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
