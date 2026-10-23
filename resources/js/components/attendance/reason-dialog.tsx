import { useState } from 'react';
import InputError from '@/components/input-error';
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

export const MIN_REASON_LENGTH = 5;

export default function ReasonDialog({
    isCorrection,
    processing,
    error,
    onCancel,
    onSave,
}: {
    isCorrection: boolean;
    processing: boolean;
    error?: string;
    onCancel: () => void;
    onSave: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    const [tooShort, setTooShort] = useState(false);

    const submit = () => {
        const trimmed = reason.trim();

        if (trimmed.length < MIN_REASON_LENGTH) {
            setTooShort(true);

            return;
        }

        onSave(trimmed);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isCorrection
                            ? 'Reason for correction'
                            : 'Reason for late entry'}
                    </DialogTitle>
                    <DialogDescription>
                        Changes to an earlier day’s register are recorded in the
                        audit log with your reason.
                    </DialogDescription>
                </DialogHeader>
                <Field
                    id="attendance-reason"
                    label="Reason"
                    error={
                        tooShort
                            ? 'Add a short reason (at least 5 characters).'
                            : undefined
                    }
                >
                    <Input
                        id="attendance-reason"
                        value={reason}
                        maxLength={200}
                        autoFocus
                        placeholder="Student arrived after the register was taken"
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
                <InputError message={error} />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={submit}
                    >
                        Save with reason
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
