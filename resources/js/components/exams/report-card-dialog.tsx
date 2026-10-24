import { Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import ReportCardDocument from '@/components/exams/report-card-document';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ReportCard } from '@/types/parent';

/**
 * A report card in a large dialog, as in the prototype: the document, then
 * Close, Print or save as PDF, and any extra actions (Send for approval).
 * `children` sits between the document and the buttons (the remark editor).
 */
export default function ReportCardDialog({
    title,
    card,
    file,
    children,
    actions,
    onClose,
}: {
    title: string;
    card: ReportCard | null;
    file?: { url: string; name: string | null; isImage: boolean } | null;
    children?: ReactNode;
    actions?: ReactNode;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[92svh] gap-0 overflow-y-auto p-0 sm:max-w-3xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <div className="p-4 sm:p-6">
                    {card !== null && (
                        <div className="print-area">
                            <ReportCardDocument card={card} />
                        </div>
                    )}
                    {file !== null && file !== undefined && (
                        <div className="flex flex-col gap-2">
                            {file.isImage ? (
                                <img
                                    src={file.url}
                                    alt="Report card scan"
                                    className="w-full rounded-lg border"
                                />
                            ) : (
                                <iframe
                                    title="Report card PDF"
                                    src={file.url}
                                    className="h-[70svh] w-full rounded-lg border"
                                />
                            )}
                            <a
                                href={file.url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                Open {file.name ?? 'the file'} in a new tab
                            </a>
                        </div>
                    )}
                    {children}
                </div>
                <DialogFooter className="border-t px-6 py-4">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                    {card !== null && (
                        <Button type="button" onClick={() => window.print()}>
                            <Printer aria-hidden /> Print or save as PDF
                        </Button>
                    )}
                    {actions}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
