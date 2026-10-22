import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * A small confirm/cancel dialog. `children` sits between the text and the
 * buttons (for example a reason picker).
 */
export default function ConfirmDialog({
    title,
    description,
    confirmLabel,
    destructive = false,
    processing = false,
    confirmDisabled = false,
    onConfirm,
    onClose,
    children,
}: {
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    processing?: boolean;
    confirmDisabled?: boolean;
    onConfirm: () => void;
    onClose: () => void;
    children?: React.ReactNode;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <DialogHeader className="border-b border-border px-6 py-4">
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4 px-6 py-5 text-sm text-slate-600">
                    <DialogDescription className="text-sm text-slate-600">
                        {description}
                    </DialogDescription>
                    {children}
                </div>
                <DialogFooter className="border-t border-border px-6 py-4">
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={processing || confirmDisabled}
                        onClick={onConfirm}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
