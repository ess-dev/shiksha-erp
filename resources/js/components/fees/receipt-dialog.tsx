import { Printer } from 'lucide-react';
import ReceiptDocument from '@/components/fees/receipt-document';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Receipt } from '@/types';

/** A receipt in a dialog; the print button opens the print page in a new tab. */
export default function ReceiptDialog({
    receipt,
    timezone,
    printUrl,
    onClose,
}: {
    receipt: Receipt;
    timezone: string;
    printUrl: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] gap-0 overflow-y-auto p-0 sm:max-w-xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Receipt {receipt.receipt_number}</DialogTitle>
                    <DialogDescription className="sr-only">
                        The fee receipt, ready to print or save as a PDF.
                    </DialogDescription>
                </DialogHeader>
                <div className="px-6 py-5">
                    <ReceiptDocument receipt={receipt} timezone={timezone} />
                </div>
                <DialogFooter className="border-t px-6 py-4">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                    <Button asChild>
                        <a href={printUrl} target="_blank" rel="noreferrer">
                            <Printer /> Print or save as PDF
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
