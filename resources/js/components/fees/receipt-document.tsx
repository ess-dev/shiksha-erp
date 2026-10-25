import { formatInstantLong, formatPaise } from '@/lib/format';
import type { Receipt } from '@/types';

function Meta({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{children}</dd>
        </div>
    );
}

/**
 * A fee receipt as it is printed. Used in the receipt dialogs and on the
 * print pages, so what is on screen is what comes out of the printer.
 */
export default function ReceiptDocument({
    receipt,
    timezone,
}: {
    receipt: Receipt;
    timezone: string;
}) {
    return (
        <article aria-label={`Receipt ${receipt.receipt_number}`}>
            <header className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">
                        {receipt.school_name}
                    </h2>
                    {receipt.school_address !== null && (
                        <p className="text-sm text-muted-foreground">
                            {receipt.school_address}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <p className="font-semibold">Fee receipt</p>
                    <p className="text-sm text-muted-foreground">
                        {receipt.receipt_number}
                    </p>
                </div>
            </header>

            <hr className="my-4 border-t-2 border-foreground" />

            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                <Meta label="Date">
                    {formatInstantLong(receipt.paid_at, timezone)}
                </Meta>
                <Meta label="Received from">
                    {receipt.received_from ?? '—'}
                </Meta>
                <Meta label="Payment mode">
                    {receipt.mode_label} ·{' '}
                    {receipt.is_counter ? 'Counter' : 'Online'}
                </Meta>
                <Meta label="Reference">{receipt.reference ?? '—'}</Meta>
                <Meta label="Collected by">
                    {receipt.collected_by === null
                        ? '—'
                        : `${receipt.collected_by}${receipt.is_counter ? ' (counter)' : ''}`}
                </Meta>
                <Meta label="Academic year">{receipt.academic_year}</Meta>
            </dl>

            <table className="mt-5 w-full text-sm">
                <thead>
                    <tr className="border-y text-xs text-muted-foreground">
                        <th scope="col" className="py-2 text-left font-medium">
                            Student and installment
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {receipt.lines.map((line, index) => (
                        <tr key={index} className="border-b">
                            <td className="py-2">
                                <span className="font-semibold">
                                    {line.student_name}
                                </span>
                                {' · '}
                                {line.section_label}
                                <span className="block text-muted-foreground">
                                    {line.installment_label} ·{' '}
                                    {line.fully_paid
                                        ? 'fully paid'
                                        : 'part payment'}
                                </span>
                            </td>
                            <td className="py-2 text-right align-top">
                                {formatPaise(line.amount_paise)}
                            </td>
                        </tr>
                    ))}
                    <tr>
                        <th
                            scope="row"
                            className="py-2 text-left font-semibold"
                        >
                            Total received
                        </th>
                        <td className="py-2 text-right text-base font-semibold">
                            {formatPaise(receipt.total_paise)}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p className="mt-3 text-xs text-muted-foreground">
                {receipt.amount_in_words}.{' '}
                {receipt.is_archived
                    ? 'Archived receipt.'
                    : 'Computer-generated receipt; no signature required. Tax treatment to be confirmed by the school.'}
            </p>
        </article>
    );
}
