import { useState } from 'react';
import Field from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/** Asks why published marks are being reopened; the reason is audited. */
export default function ReopenDialog({
    subject,
    section,
    processing,
    error,
    onCancel,
    onReopen,
}: {
    subject: string;
    section: string;
    processing: boolean;
    error?: string;
    onCancel: () => void;
    onReopen: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    const [tooShort, setTooShort] = useState(false);

    const submit = () => {
        const trimmed = reason.trim();

        if (trimmed.length < 3) {
            setTooShort(true);

            return;
        }

        onReopen(trimmed);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Reopen {subject} for {section}?
                    </DialogTitle>
                    <DialogDescription>
                        Parents stop seeing these marks until they are corrected
                        and published again. The reason is kept in the audit
                        log.
                    </DialogDescription>
                </DialogHeader>
                <Field
                    id="reopen-reason"
                    label="Reason"
                    error={
                        tooShort
                            ? 'Add a short reason (at least 3 characters).'
                            : error
                    }
                >
                    <Input
                        id="reopen-reason"
                        value={reason}
                        maxLength={200}
                        autoFocus
                        placeholder="Roll 7 marks were entered for the wrong student"
                        onChange={(event) => {
                            setReason(event.target.value);
                            setTooShort(false);
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                submit();
                            }
                        }}
                    />
                </Field>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={processing}
                    >
                        Reopen marks
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
