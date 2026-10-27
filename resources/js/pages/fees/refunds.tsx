import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import {
    ApproveRefundDialog,
    NewRefundDialog,
    RejectRefundDialog,
} from '@/components/fees/refund-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/fees/refunds';
import type { RefundRow, RefundSource, RefundsScreen } from '@/types';

type Props = { screen: RefundsScreen };

type Dialog =
    | { kind: 'approve' | 'reject'; row: RefundRow }
    | { kind: 'new' }
    | null;

const TABS = [
    ['pending', 'Pending'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
    ['all', 'All'],
] as const;

const SOURCE_LABEL: Record<RefundSource, string> = {
    parent: 'Parent',
    accounts: 'Accounts',
    system: 'System',
};

const STATUS_STYLE = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    approved:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    rejected: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
} as const;

const STATUS_LABEL = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
} as const;

function outcome(row: RefundRow): string {
    if (row.status === 'rejected') {
        return row.reject_reason ?? '';
    }

    if (row.status === 'approved') {
        const via =
            row.method === 'original'
                ? row.original_label
                : row.method === 'bank_transfer'
                  ? 'Bank transfer'
                  : row.method === 'cheque'
                    ? 'Cheque'
                    : 'Adjusted against installments';

        return [
            `Paid via ${via}`,
            row.reference,
            row.decided_on !== null ? formatCalendarDate(row.decided_on) : null,
        ]
            .filter((part) => part !== null && part !== '')
            .join(' · ');
    }

    return '';
}

export default function Refunds({ screen }: Props) {
    const [dialog, setDialog] = useState<Dialog>(null);

    const total =
        screen.pending_count + screen.approved_count + screen.rejected_count;

    const counts: Record<string, number> = {
        pending: screen.pending_count,
        approved: screen.approved_count,
        rejected: screen.rejected_count,
        all: total,
    };

    const visit = (tab: string) =>
        router.get(
            index.url(),
            { tab: tab === 'pending' ? undefined : tab },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Refunds" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Refunds
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review refund requests from parents, the office and
                            the system (duplicate payments).
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div
                            role="group"
                            aria-label="Filter refunds"
                            className="flex rounded-md border p-0.5"
                        >
                            {TABS.map(([key, label]) => (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={screen.tab === key}
                                    onClick={() => visit(key)}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-sm font-medium text-muted-foreground',
                                        screen.tab === key &&
                                            'bg-primary/10 text-primary',
                                    )}
                                >
                                    {label}
                                    {key !== 'all' && counts[key] > 0
                                        ? ` (${counts[key]})`
                                        : ''}
                                </button>
                            ))}
                        </div>
                        <Button
                            size="sm"
                            onClick={() => setDialog({ kind: 'new' })}
                        >
                            <Plus /> New refund request
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Awaiting decision
                        </span>
                        <strong
                            className={cn(
                                'text-xl font-semibold',
                                screen.pending_count > 0 &&
                                    'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {screen.pending_count}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {formatPaise(screen.pending_paise)}
                        </span>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Approved
                        </span>
                        <strong className="text-xl font-semibold">
                            {formatPaise(screen.approved_paise)}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {screen.approved_count}{' '}
                            {screen.approved_count === 1 ? 'refund' : 'refunds'}
                        </span>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Rejected
                        </span>
                        <strong className="text-xl font-semibold">
                            {screen.rejected_count}
                        </strong>
                    </Card>
                    <Card className="gap-0.5 px-4 py-3">
                        <span className="text-xs text-muted-foreground">
                            Principal sign-off
                        </span>
                        <strong className="text-xl font-semibold">
                            Above {formatPaise(screen.limit_paise)}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            School policy (example)
                        </span>
                    </Card>
                </div>

                <Card className="gap-0 overflow-hidden p-0">
                    {screen.rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="font-medium">
                                No{' '}
                                {screen.tab === 'all' ? '' : `${screen.tab} `}
                                refund requests
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-2.5 font-medium">
                                            Requested
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            Student
                                        </th>
                                        <th className="hidden px-4 py-2.5 font-medium md:table-cell">
                                            Reason
                                        </th>
                                        <th className="px-4 py-2.5 text-right font-medium">
                                            Amount
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-2.5">
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
                                                {formatCalendarDate(
                                                    row.requested_on,
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    by{' '}
                                                    {SOURCE_LABEL[row.source]}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {row.student_name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    Class {row.section_label} ·{' '}
                                                    {row.admission_number}
                                                </span>
                                            </td>
                                            <td className="hidden max-w-xs px-4 py-3 md:table-cell">
                                                {row.reason}
                                                {row.receipt_number !==
                                                    null && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        Linked payment{' '}
                                                        {row.receipt_number}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold whitespace-nowrap">
                                                {formatPaise(row.amount_paise)}
                                                {row.needs_principal &&
                                                    row.status ===
                                                        'pending' && (
                                                        <span className="block text-xs font-normal text-amber-700 dark:text-amber-400">
                                                            Needs principal
                                                        </span>
                                                    )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="secondary"
                                                    className={cn(
                                                        'rounded-full border-transparent',
                                                        STATUS_STYLE[
                                                            row.status
                                                        ],
                                                    )}
                                                >
                                                    {STATUS_LABEL[row.status]}
                                                </Badge>
                                                {outcome(row) !== '' && (
                                                    <span className="mt-1 block max-w-56 text-xs text-muted-foreground">
                                                        {outcome(row)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.status === 'pending' && (
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'reject',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'approve',
                                                                    row,
                                                                })
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            {dialog?.kind === 'approve' && (
                <ApproveRefundDialog
                    row={dialog.row}
                    methods={screen.methods}
                    limitPaise={screen.limit_paise}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'reject' && (
                <RejectRefundDialog
                    row={dialog.row}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'new' && (
                <NewRefundDialog
                    screen={screen}
                    onClose={() => setDialog(null)}
                />
            )}
        </>
    );
}

Refunds.layout = {
    breadcrumbs: [{ title: 'Refunds', href: index() }],
};
