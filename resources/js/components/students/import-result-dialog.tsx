import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show } from '@/routes/students';
import type { ImportResult } from '@/types/students';

export default function ImportResultDialog({
    result,
    onClose,
}: {
    result: ImportResult;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {result.created}{' '}
                        {result.created === 1 ? 'student' : 'students'} imported
                    </DialogTitle>
                    <DialogDescription>
                        {result.families_linked > 0
                            ? `${result.families_linked} matched an existing family, so siblings are linked. `
                            : ''}
                        {result.skipped > 0
                            ? `${result.skipped} rows were skipped because they needed fixing.`
                            : 'No rows were skipped.'}
                    </DialogDescription>
                </DialogHeader>

                <ul className="min-h-0 divide-y overflow-y-auto rounded-md border">
                    {result.students.map((student) => (
                        <li
                            key={student.ulid}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div>
                                <div className="text-sm font-medium">
                                    {student.name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {student.class_label} ·{' '}
                                    {student.admission_number}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={show(student.ulid)}>Open</Link>
                            </Button>
                        </li>
                    ))}
                </ul>

                <DialogFooter>
                    <Button onClick={onClose}>Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
