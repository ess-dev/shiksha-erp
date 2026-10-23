import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function MissingDocumentsDialog({
    name,
    count,
    onCancel,
    onConfirm,
}: {
    name: string;
    count: number;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Documents still missing</DialogTitle>
                    <DialogDescription>
                        {count} required document(s) aren’t ticked for {name}.
                        Move to “Documents verified” anyway?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={onConfirm}>
                        Move anyway
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
