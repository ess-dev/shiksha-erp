import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function ConfirmDialog({
    title,
    body,
    confirmLabel,
    destructive = false,
    onCancel,
    onConfirm,
}: {
    title: string;
    body: string;
    confirmLabel: string;
    destructive?: boolean;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{body}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
