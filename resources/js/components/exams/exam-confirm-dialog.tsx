import { router } from '@inertiajs/react';
import { useState } from 'react';
import ExamController from '@/actions/App/Http/Controllers/Exams/ExamController';
import ExamStatusController from '@/actions/App/Http/Controllers/Exams/ExamStatusController';
import InputError from '@/components/input-error';
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
import type { ExamRow, ExamStatus } from '@/types';

export type ExamAction = ExamStatus | 'delete';

type Copy = { title: string; body: string; confirm: string; danger?: boolean };

function copyFor(exam: ExamRow, action: ExamAction): Copy {
    switch (action) {
        case 'marking':
            return {
                title: `Open marking for ${exam.name}?`,
                body: 'Subject teachers can enter marks. Parents see nothing until marks are published.',
                confirm: 'Open marking',
            };
        case 'published':
            return {
                title: `Publish every subject of ${exam.name}?`,
                body: 'Parents see all entered marks straight away and get a notification. Missing marks show as “Awaiting results”.',
                confirm: 'Publish all',
            };
        case 'scheduled':
            return exam.status === 'cancelled'
                ? {
                      title: `Restore ${exam.name}?`,
                      body: 'It goes back to scheduled.',
                      confirm: 'Restore',
                  }
                : {
                      title: `Close marking for ${exam.name}?`,
                      body: 'Teachers can’t enter marks until you open it again. Entered marks are kept.',
                      confirm: 'Close marking',
                  };
        case 'cancelled':
            return {
                title: `Cancel ${exam.name}?`,
                body: 'It’s hidden from marks entry and parents. Entered marks are kept, and you can restore it later.',
                confirm: 'Cancel exam',
                danger: true,
            };
        case 'delete':
            return {
                title: `Delete ${exam.name}?`,
                body: 'No marks are entered, so nothing else is lost. Its date sheet is removed too.',
                confirm: 'Delete',
                danger: true,
            };
    }
}

export default function ExamConfirmDialog({
    exam,
    action,
    onClose,
}: {
    exam: ExamRow;
    action: ExamAction;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const copy = copyFor(exam, action);

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: onClose,
        onError: (bag: Record<string, string>) => setError(bag.exam),
    };

    const confirm = () => {
        if (action === 'delete') {
            router.delete(ExamController.destroy.url(exam.ulid), visit);
        } else {
            router.put(
                ExamStatusController.update.url(exam.ulid),
                { status: action },
                visit,
            );
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{copy.title}</DialogTitle>
                    <DialogDescription>{copy.body}</DialogDescription>
                </DialogHeader>
                <InputError message={error} />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={
                            copy.danger === true ? 'destructive' : 'default'
                        }
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        {copy.confirm}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
