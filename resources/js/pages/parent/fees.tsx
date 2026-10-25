import { Head } from '@inertiajs/react';
import {
    Check,
    CreditCard,
    FileText,
    History,
    IndianRupee,
} from 'lucide-react';
import { useState } from 'react';
import ReceiptDialog from '@/components/fees/receipt-dialog';
import {
    InvoiceStatusBadge,
    PaymentStatusBadge,
} from '@/components/fees/status-badges';
import { Button } from '@/components/ui/button';
import { relativeDays } from '@/lib/fees';
import {
    formatCalendarDate,
    formatInstantLong,
    formatPaise,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { receipt as receiptPage } from '@/routes/parent/fees';
import type {
    ParentFees,
    ParentInstallment,
    ParentPayment,
    ParentShell,
} from '@/types';

type Props = {
    shell: ParentShell;
    fees: ParentFees | null;
    statement_url: string | null;
};

export default function ParentFeesPage({
    shell,
    fees,
    statement_url: statementUrl,
}: Props) {
    const [receipt, setReceipt] = useState<ParentPayment | null>(null);

    if (fees === null) {
        return (
            <>
                <Head title="Fees" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">No fees to show</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Fees are shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    if (!fees.has_fees) {
        return (
            <>
                <Head title="Fees" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No fees billed for {fees.child_first_name} in{' '}
                        {fees.session_name}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {fees.is_current_session
                            ? 'The school has not billed this session yet. Check back later or ask the accounts office.'
                            : 'The school has no fee records for this session.'}
                    </p>
                </div>
            </>
        );
    }

    const timezone = shell.timezone;
    const allPaid = fees.total_due_paise === 0;

    return (
        <>
            <Head title="Fees" />

            {!fees.is_current_session && (
                <p
                    role="status"
                    className="mb-4 flex items-center gap-2 rounded-lg bg-secondary px-4 py-3 text-sm"
                >
                    <History className="size-4 shrink-0" aria-hidden />
                    Viewing {fees.session_name} · {fees.child_first_name} was in{' '}
                    {fees.section_label}. Past sessions are read only.
                </p>
            )}

            {fees.failed_alerts.map((payment) => (
                <div
                    key={payment.ulid}
                    role="status"
                    className="mb-4 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <CreditCard
                        className="mt-0.5 size-5 shrink-0"
                        aria-hidden
                    />
                    <div>
                        <p className="font-semibold">
                            {formatPaise(payment.amount_paise)} for{' '}
                            {payment.for_label.split(' · ')[0]} didn’t go
                            through
                        </p>
                        <p className="text-sm">
                            {payment.mode_label} payment on{' '}
                            {formatInstantLong(payment.paid_at, timezone)}{' '}
                            failed
                            {payment.note !== null &&
                                `: ${payment.note.toLowerCase()}`}
                            . No money was taken. Please pay at the school
                            counter or ask the accounts office for a payment
                            link.
                        </p>
                    </div>
                </div>
            ))}

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="grid content-start gap-4 lg:col-span-2">
                    <section
                        aria-labelledby="dues-title"
                        className="rounded-xl border bg-card"
                    >
                        <h2
                            id="dues-title"
                            className="border-b px-4 py-3 font-semibold"
                        >
                            Dues
                        </h2>
                        <ul className="divide-y">
                            {fees.installments.map((item) => (
                                <InstallmentRow
                                    key={item.ulid}
                                    item={item}
                                    today={fees.today}
                                />
                            ))}
                        </ul>
                    </section>

                    <section
                        aria-labelledby="payments-title"
                        className="rounded-xl border bg-card"
                    >
                        <h2
                            id="payments-title"
                            className="border-b px-4 py-3 font-semibold"
                        >
                            Payments and receipts
                        </h2>
                        {fees.payments.length === 0 ? (
                            <p className="px-4 py-6 text-sm text-muted-foreground">
                                No payments yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {fees.payments.map((payment) => (
                                    <li
                                        key={payment.ulid}
                                        className="flex items-start gap-3 px-4 py-3"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <IndianRupee
                                                className="size-5"
                                                aria-hidden
                                            />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold">
                                                {formatPaise(
                                                    payment.amount_paise,
                                                )}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatInstantLong(
                                                    payment.paid_at,
                                                    timezone,
                                                )}{' '}
                                                · {payment.mode_label}
                                                {payment.receipt_number !==
                                                    null &&
                                                    ` · ${payment.receipt_number}`}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {payment.for_label}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <PaymentStatusBadge
                                                status={payment.status}
                                            />
                                            {payment.receipt !== null && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setReceipt(payment)
                                                    }
                                                >
                                                    Receipt
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <aside className="grid content-start gap-4">
                    <section
                        aria-labelledby="session-title"
                        className="rounded-xl border bg-card p-4"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h2 id="session-title" className="font-semibold">
                                Session {fees.session_name}
                            </h2>
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    allPaid
                                        ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                        : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
                                )}
                            >
                                {allPaid ? 'All paid' : 'Amount due'}
                            </span>
                        </div>
                        <dl className="mt-4 grid gap-3">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Total paid
                                </dt>
                                <dd className="text-3xl font-bold">
                                    {formatPaise(fees.total_paid_paise)}
                                </dd>
                            </div>
                            {!allPaid && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Still to pay
                                    </dt>
                                    <dd className="text-xl font-semibold">
                                        {formatPaise(fees.total_due_paise)}
                                    </dd>
                                </div>
                            )}
                        </dl>
                        <p className="mt-3 text-sm text-muted-foreground">
                            {fees.child_first_name}{' '}
                            {fees.is_current_session ? 'is' : 'was'} in{' '}
                            {fees.section_label}. Fee certificates for
                            income-tax purposes can be requested from the
                            accounts office.
                        </p>
                        {statementUrl !== null && (
                            <Button
                                variant="outline"
                                className="mt-3 w-full"
                                asChild
                            >
                                <a
                                    href={statementUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText /> Fee statement for{' '}
                                    {fees.session_name}
                                </a>
                            </Button>
                        )}
                    </section>

                    {fees.is_current_session && !allPaid && (
                        <section className="rounded-xl border bg-card p-4 text-sm">
                            <h2 className="font-semibold">How to pay</h2>
                            <p className="mt-1 text-muted-foreground">
                                Online payment is not available yet. Please pay
                                at the school’s accounts counter; you will get a
                                receipt here as soon as it is recorded.
                            </p>
                        </section>
                    )}
                </aside>
            </div>

            {receipt?.receipt != null && (
                <ReceiptDialog
                    receipt={receipt.receipt}
                    timezone={timezone}
                    printUrl={receiptPage.url(receipt.ulid, {
                        query: { child: shell.selected_child ?? undefined },
                    })}
                    onClose={() => setReceipt(null)}
                />
            )}
        </>
    );
}

function InstallmentRow({
    item,
    today,
}: {
    item: ParentInstallment;
    today: string;
}) {
    const paid = item.balance_paise === 0;

    return (
        <li className="grid gap-1 px-4 py-3">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-full',
                        paid
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground',
                    )}
                    aria-hidden
                >
                    {paid ? (
                        <Check className="size-4" />
                    ) : (
                        <IndianRupee className="size-4" />
                    )}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold">{item.label}</p>
                    <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        {paid
                            ? item.paid_on !== null &&
                              `Paid ${formatCalendarDate(item.paid_on)}`
                            : `Due ${formatCalendarDate(item.due_on)} (${relativeDays(today, item.due_on)})`}
                        <InvoiceStatusBadge status={item.status} />
                    </p>
                    {item.payment_pending && (
                        <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                            A payment is awaiting bank confirmation.
                        </p>
                    )}
                </div>
                <div className="text-right font-semibold">
                    {paid
                        ? formatPaise(item.net_paise)
                        : formatPaise(item.balance_paise)}
                    {!paid && item.paid_paise > 0 && (
                        <span className="block text-xs font-normal text-muted-foreground">
                            of {formatPaise(item.net_paise)}
                        </span>
                    )}
                </div>
            </div>
            <details className="ml-11 text-sm">
                <summary className="cursor-pointer text-muted-foreground">
                    Fee breakdown
                </summary>
                <table className="mt-1 w-full max-w-sm">
                    <tbody>
                        {item.heads.map((head) => (
                            <tr key={head.name}>
                                <td className="py-0.5">{head.name}</td>
                                <td className="py-0.5 text-right">
                                    {formatPaise(head.amount_paise)}
                                </td>
                            </tr>
                        ))}
                        {item.concession_paise > 0 && (
                            <tr className="text-emerald-700 dark:text-emerald-400">
                                <td className="py-0.5">
                                    {item.concession_note}
                                </td>
                                <td className="py-0.5 text-right">
                                    −{formatPaise(item.concession_paise)}
                                </td>
                            </tr>
                        )}
                        <tr className="border-t font-semibold">
                            <td className="py-0.5">Installment total</td>
                            <td className="py-0.5 text-right">
                                {formatPaise(item.net_paise)}
                            </td>
                        </tr>
                        {item.paid_paise > 0 && !paid && (
                            <tr>
                                <td className="py-0.5">Already paid</td>
                                <td className="py-0.5 text-right">
                                    −{formatPaise(item.paid_paise)}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </details>
        </li>
    );
}
