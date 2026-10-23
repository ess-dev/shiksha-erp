import { useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rollNumber } from '@/routes/students';
import type { StudentProfile } from '@/types/students';

export default function RollNumberDialog({
    student,
    onClose,
}: {
    student: StudentProfile;
    onClose: () => void;
}) {
    const form = useForm({ roll_number: String(student.roll_number ?? '') });
    const errors = form.errors as Record<string, string>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch(rollNumber.url(student.ulid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Change roll number</DialogTitle>
                    <DialogDescription>
                        A roll number is unique within {student.class_label}.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <div className="grid gap-1.5">
                        <Label htmlFor="roll-number">Roll number</Label>
                        <Input
                            id="roll-number"
                            type="number"
                            min={1}
                            max={9999}
                            value={form.data.roll_number}
                            onChange={(event) =>
                                form.setData('roll_number', event.target.value)
                            }
                            autoFocus
                        />
                        <InputError message={form.errors.roll_number} />
                        <InputError message={errors.student} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
