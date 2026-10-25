import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import ReceiptDocument from '@/components/fees/receipt-document';
import { Button } from '@/components/ui/button';
import type { Receipt } from '@/types';

type Props = { receipt: Receipt; timezone: string; back_url: string };

/**
 * A fee receipt for a parent to print or save as a PDF.
 */
export default function ParentFeeReceiptPage({
    receipt,
    timezone,
    back_url: backUrl,
}: Props) {
    return (
        <>
            <Head title={`Receipt ${receipt.receipt_number}`} />

            <div className="mx-auto max-w-2xl p-6">
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

                <div className="rounded-md border p-8 print:border-0 print:p-0">
                    <ReceiptDocument receipt={receipt} timezone={timezone} />
                </div>
            </div>
        </>
    );
}
