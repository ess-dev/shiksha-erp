import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useRef, useState } from 'react';
import BounceChequeDialog from '@/components/fees/bounce-cheque-dialog';
import GatewayCheckDialog from '@/components/fees/gateway-check-dialog';
import PaymentDetailsDialog from '@/components/fees/payment-details-dialog';
import ReceiptDialog from '@/components/fees/receipt-dialog';
import { PaymentStatusBadge } from '@/components/fees/status-badges';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import { selectClassName } from '@/components/school/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    formatInstantDate,
    formatClock,
    formatPaise,
    formatPaiseCompact,
} from '@/lib/format';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { index as dues } from '@/routes/fees/dues';
import { index as refundsIndex } from '@/routes/fees/refunds';
import {
    check,
    cheque,
    exportMethod,
    index,
    link,
    recheck,
} from '@/routes/fees/payments';
import { show as receiptPage } from '@/routes/fees/receipts';
import type {
    GatewayReport,
    PaymentFilters,
    PaymentRow,
    PaymentsScreen,
} from '@/types';

type Props = {
    screen: PaymentsScreen;
    filters: PaymentFilters;
    timezone: string;
};

type Dialog = {
    kind:
        | 'receipt'
        | 'details'
        | 'check'
        | 'recheck'
        | 'clear'
        | 'bounce'
        | 'link';
    row: PaymentRow;
} | null;

type Visit = Partial<PaymentFilters> & { page?: number };

export default function Payments({ screen, filters, timezone }: Props) {
    const canReviewRefunds = useCan('fees.refund');
    const [search, setSearch] = useState(filters.q);
    const [dialog, setDialog] = useState<Dialog>(null);
    const [processing, setProcessing] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const query = (changes: Visit) => {
        const next = { ...filters, ...changes };

        return {
            q: next.q === '' ? undefined : next.q,
            status: next.status === 'all' ? undefined : next.status,
            mode: next.mode === 'all' ? undefined : next.mode,
            from: next.from ?? undefined,
            to: next.to ?? undefined,
            page: changes.page,
        };
    };

    const visit = (changes: Visit) =>
        router.get(index.url(), query(changes), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });

    const onSearch = (value: string) => {
        setSearch(value);

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => visit({ q: value }), 300);
    };

    const post = (url: string, data: Record<string, unknown> = {}) => {
        setProcessing(true);

        router.post(url, data as Record<string, string | boolean>, {
            preserveScroll: true,
            onSuccess: () => setDialog(null),
            onFinish: () => setProcessing(false),
        });
    };

    const summary = screen.summary;

    return (
        <>
            <Head title="Payments" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Payments and reconciliation
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Confirm pending online payments and cheques, follow
                            up failed ones, and reprint receipts.
                        </p>
                    </div>
                    {screen.can_export && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportMethod.url({ query: query({}) })}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Successful this year
                        </span>
                        <strong className="text-xl font-semibold">
                            {formatPaiseCompact(summary.successful_paise)}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {summary.successful_count.toLocaleString('en-IN')}{' '}
                            {summary.successful_count === 1
                                ? 'payment'
                                : 'payments'}
                        </span>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Pending confirmation
                        </span>
                        <strong
                            className={cn(
                                'text-xl font-semibold',
                                summary.pending_count > 0 &&
                                    'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {summary.pending_count}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {formatPaise(summary.pending_paise)}
                        </span>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Failed, not yet paid
                        </span>
                        <strong
                            className={cn(
                                'text-xl font-semibold',
                                summary.failed_count > 0 &&
                                    'text-red-700 dark:text-red-400',
                            )}
                        >
                            {summary.failed_count}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {formatPaise(summary.failed_paise)}
                        </span>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Refund requests open
                        </span>
                        <strong className="text-xl font-semibold">
                            {summary.open_refunds_count}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {canReviewRefunds ? (
                                <Link
                                    href={refundsIndex()}
                                    className="underline"
                                >
                                    Review refunds
                                </Link>
                            ) : (
                                'Decided by the accountant'
                            )}
                        </span>
                    </Card>
                </div>

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="grid gap-1.5 lg:col-span-1">
                            <label
                                htmlFor="pay-search"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Search
                            </label>
                            <Input
                                id="pay-search"
                                value={search}
                                onChange={(event) =>
                                    onSearch(event.target.value)
                                }
                                placeholder="Student, receipt no. or reference"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="pay-status"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Status
                            </label>
                            <select
                                id="pay-status"
                                className={selectClassName}
                                value={filters.status}
                                onChange={(event) =>
                                    visit({
                                        status: event.target
                                            .value as PaymentFilters['status'],
                                    })
                                }
                            >
                                <option value="all">All</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                                <option value="success">Success</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="pay-mode"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Mode
                            </label>
                            <select
                                id="pay-mode"
                                className={selectClassName}
                                value={filters.mode}
                                onChange={(event) =>
                                    visit({ mode: event.target.value })
                                }
                            >
                                <option value="all">All modes</option>
                                {screen.modes.map((mode) => (
                                    <option key={mode.value} value={mode.value}>
                                        {mode.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="pay-from"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                From
                            </label>
                            <Input
                                id="pay-from"
                                type="date"
                                max={screen.today}
                                value={filters.from ?? ''}
                                onChange={(event) =>
                                    visit({ from: event.target.value || null })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="pay-to"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                To
                            </label>
                            <Input
                                id="pay-to"
                                type="date"
                                max={screen.today}
                                value={filters.to ?? ''}
                                onChange={(event) =>
                                    visit({ to: event.target.value || null })
                                }
                            />
                        </div>
                    </div>

                    {screen.rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="font-medium">
                                No payments match these filters
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Payments appear here as soon as fees are
                                collected.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            When
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Student
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 font-medium md:table-cell"
                                        >
                                            Mode and reference
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 text-right font-medium"
                                        >
                                            Amount
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Status
                                        </th>
                                        <th scope="col" className="px-4 py-2.5">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {screen.rows.map((row) => (
                                        <tr
                                            key={row.ulid}
                                            className="align-top hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatInstantDate(
                                                    row.paid_at,
                                                    timezone,
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatClock(
                                                        row.paid_at,
                                                        timezone,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {row.student_name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.section_label}
                                                    {row.receipt_number !==
                                                        null &&
                                                        ` · ${row.receipt_number}`}
                                                </span>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {row.mode_label}{' '}
                                                {row.is_counter && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-1 rounded-full"
                                                    >
                                                        Counter
                                                    </Badge>
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.reference ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold whitespace-nowrap">
                                                {formatPaise(row.amount_paise)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <PaymentStatusBadge
                                                    status={row.status}
                                                />
                                                {(row.status_note !== '' ||
                                                    row.link_sent) && (
                                                    <span className="mt-1 block max-w-56 text-xs text-muted-foreground">
                                                        {row.status_note}
                                                        {row.link_sent &&
                                                            ' · pay link sent'}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    {row.receipt !== null && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'receipt',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            Receipt
                                                        </Button>
                                                    )}
                                                    {row.can_clear_cheque && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'clear',
                                                                        row,
                                                                    })
                                                                }
                                                            >
                                                                Mark cleared
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'bounce',
                                                                        row,
                                                                    })
                                                                }
                                                            >
                                                                Bounced
                                                            </Button>
                                                        </>
                                                    )}
                                                    {row.can_check && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'check',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            Check status
                                                        </Button>
                                                    )}
                                                    {row.can_recheck && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'recheck',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            Retry check
                                                        </Button>
                                                    )}
                                                    {row.can_send_link && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'link',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            {row.link_sent
                                                                ? 'Resend link'
                                                                : 'Send pay link'}
                                                        </Button>
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setDialog({
                                                                kind: 'details',
                                                                row,
                                                            })
                                                        }
                                                    >
                                                        Details
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            Showing {screen.from}–{screen.to} of {screen.total}
                        </span>
                        <span className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={screen.current_page <= 1}
                                onClick={() =>
                                    visit({ page: screen.current_page - 1 })
                                }
                            >
                                Previous
                            </Button>
                            <span>
                                Page {screen.current_page} of {screen.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    screen.current_page >= screen.last_page
                                }
                                onClick={() =>
                                    visit({ page: screen.current_page + 1 })
                                }
                            >
                                Next
                            </Button>
                        </span>
                    </div>
                </Card>

                <p className="text-sm text-muted-foreground">
                    Looking for unpaid installments instead? Open{' '}
                    <a
                        href={dues.url()}
                        className="text-primary hover:underline"
                    >
                        Fee dues
                    </a>
                    .
                </p>
            </div>

            {dialog?.kind === 'receipt' && dialog.row.receipt !== null && (
                <ReceiptDialog
                    receipt={dialog.row.receipt}
                    timezone={timezone}
                    printUrl={receiptPage.url(dialog.row.ulid)}
                    onClose={() => setDialog(null)}
                />
            )}

            {dialog?.kind === 'details' && (
                <PaymentDetailsDialog
                    detail={dialog.row.detail}
                    timezone={timezone}
                    onClose={() => setDialog(null)}
                />
            )}

            {(dialog?.kind === 'check' || dialog?.kind === 'recheck') && (
                <GatewayCheckDialog
                    recheck={dialog.kind === 'recheck'}
                    gateway={screen.gateway}
                    description={
                        dialog.kind === 'check'
                            ? `${dialog.row.mode_label} payment of ${formatPaise(dialog.row.amount_paise)} from ${dialog.row.student_name} (ref ${dialog.row.reference ?? '—'}) hasn’t been confirmed yet. This asks the payment gateway directly.`
                            : `Sometimes a bank takes the money even though the app showed a failure. Re-checking asks the gateway for the final status of this ${formatPaise(dialog.row.amount_paise)} ${dialog.row.mode_label} payment.`
                    }
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={(report: GatewayReport | null) =>
                        post(
                            (dialog.kind === 'check' ? check : recheck).url(
                                dialog.row.ulid,
                            ),
                            report === null ? {} : { report },
                        )
                    }
                />
            )}

            {dialog?.kind === 'clear' && (
                <ConfirmDialog
                    title="Mark cheque cleared?"
                    description={`The cheque for ${formatPaise(dialog.row.amount_paise)} from ${dialog.row.student_name}${dialog.row.reference === null ? '' : ` (no. ${dialog.row.reference})`} is credited in the bank statement. A receipt will be issued.`}
                    confirmLabel="Mark cleared"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        post(cheque.url(dialog.row.ulid), { cleared: true })
                    }
                />
            )}

            {dialog?.kind === 'bounce' && (
                <BounceChequeDialog
                    reasons={screen.bounce_reasons}
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={(reason) =>
                        post(cheque.url(dialog.row.ulid), {
                            cleared: false,
                            reason,
                        })
                    }
                />
            )}

            {dialog?.kind === 'link' && (
                <ConfirmDialog
                    title="Send payment link?"
                    description={`The guardian of ${dialog.row.student_name} gets a secure link to pay the outstanding amount. No SMS provider is set up yet, so for now this is only recorded.`}
                    confirmLabel="Send link"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() => post(link.url(dialog.row.ulid))}
                />
            )}
        </>
    );
}

Payments.layout = {
    breadcrumbs: [{ title: 'Payments', href: index() }],
};
