import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { InvoiceStatusBadge } from '@/components/fees/status-badges';
import { Button } from '@/components/ui/button';
import {
    formatCalendarDate,
    formatInstantLong,
    formatPaise,
} from '@/lib/format';
import type { ParentFees } from '@/types';

type Props = {
    fees: ParentFees | null;
    school_name: string;
    timezone: string;
    back_url: string;
};

/**
 * A printable statement of one child's fees for one session: every
 * installment with what was billed and paid, and every confirmed payment.
 */
export default function ParentFeeStatementPage({
    fees,
    school_name: schoolName,
    timezone,
    back_url: backUrl,
}: Props) {
    return (
        <>
            <Head title="Fee statement" />

            <div className="mx-auto max-w-3xl p-6">
                <div className="mb-4 flex items-center justify-between print:hidden">
                    <Link
                        href={backUrl}
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        <ArrowLeft className="size-4" /> Back to fees
                    </Link>
                    <Button onClick={() => window.print()}>
                        <Printer /> Print or save as PDF
                    </Button>
                </div>

                <article className="rounded-md border p-8 print:border-0 print:p-0">
                    <header className="mb-6 text-center">
                        <h1 className="text-xl font-semibold">{schoolName}</h1>
                        <p className="mt-1 font-medium tracking-wide uppercase">
                            Fee statement
                        </p>
                        {fees !== null && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                {fees.child_name} · {fees.section_label} ·{' '}
                                {fees.session_name}
                            </p>
                        )}
                    </header>

                    {fees === null || !fees.has_fees ? (
                        <p className="text-sm text-muted-foreground">
                            No fees were billed for this session.
                        </p>
                    ) : (
                        <>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-y text-xs text-muted-foreground">
                                        <th
                                            scope="col"
                                            className="py-2 text-left font-medium"
                                        >
                                            Installment
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-left font-medium"
                                        >
                                            Due
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right font-medium"
                                        >
                                            Billed
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right font-medium"
                                        >
                                            Paid
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right font-medium"
                                        >
                                            Balance
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pl-3 text-left font-medium"
                                        >
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {fees.installments.map((item) => (
                                        <tr
                                            key={item.ulid}
                                            className="border-b"
                                        >
                                            <td className="py-2">
                                                {item.label}
                                            </td>
                                            <td className="py-2">
                                                {formatCalendarDate(
                                                    item.due_on,
                                                )}
                                            </td>
                                            <td className="py-2 text-right">
                                                {formatPaise(item.net_paise)}
                                            </td>
                                            <td className="py-2 text-right">
                                                {formatPaise(item.paid_paise)}
                                            </td>
                                            <td className="py-2 text-right">
                                                {formatPaise(
                                                    item.balance_paise,
                                                )}
                                            </td>
                                            <td className="py-2 pl-3">
                                                <InvoiceStatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="font-semibold">
                                        <th
                                            scope="row"
                                            colSpan={2}
                                            className="py-2 text-left"
                                        >
                                            Total
                                        </th>
                                        <td className="py-2 text-right">
                                            {formatPaise(
                                                fees.total_billed_paise,
                                            )}
                                        </td>
                                        <td className="py-2 text-right">
                                            {formatPaise(fees.total_paid_paise)}
                                        </td>
                                        <td className="py-2 text-right">
                                            {formatPaise(fees.total_due_paise)}
                                        </td>
                                        <td />
                                    </tr>
                                </tbody>
                            </table>

                            <h2 className="mt-8 mb-2 font-semibold">
                                Payments
                            </h2>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-y text-xs text-muted-foreground">
                                        <th
                                            scope="col"
                                            className="py-2 text-left font-medium"
                                        >
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-left font-medium"
                                        >
                                            Mode
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-left font-medium"
                                        >
                                            Receipt
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
                                    {fees.payments
                                        .filter(
                                            (payment) =>
                                                payment.status === 'success',
                                        )
                                        .map((payment) => (
                                            <tr
                                                key={payment.ulid}
                                                className="border-b"
                                            >
                                                <td className="py-2">
                                                    {formatInstantLong(
                                                        payment.paid_at,
                                                        timezone,
                                                    )}
                                                </td>
                                                <td className="py-2">
                                                    {payment.mode_label}
                                                </td>
                                                <td className="py-2">
                                                    {payment.receipt_number ??
                                                        '—'}
                                                </td>
                                                <td className="py-2 text-right">
                                                    {formatPaise(
                                                        payment.amount_paise,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                </tbody>
                            </table>

                            <p className="mt-4 text-xs text-muted-foreground">
                                Computer-generated statement; no signature
                                required. Tax treatment to be confirmed by the
                                school.
                            </p>
                        </>
                    )}
                </article>
            </div>
        </>
    );
}
