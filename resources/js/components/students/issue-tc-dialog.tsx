import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { selectClassName } from '@/components/school/field';
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
import tc from '@/routes/students/tc';
import type { StudentOptions, StudentProfile } from '@/types/students';

export default function IssueTcDialog({
    student,
    options,
    today,
    onClose,
}: {
    student: StudentProfile;
    options: StudentOptions;
    today: string;
    onClose: () => void;
}) {
    const form = useForm({
        last_day: today,
        reason: options.transfer_reasons[0]?.value ?? 'other',
    });
    const errors = form.errors as Record<string, string>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(tc.store.url(student.ulid), { onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Issue transfer certificate</DialogTitle>
                    <DialogDescription>
                        {student.name} moves to &ldquo;Left the school&rdquo;.
                        Their records are kept for the retention period. Future
                        fee installments will be cancelled once the Fees module
                        is available.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <div className="grid gap-1.5">
                        <Label htmlFor="tc-last-day">Last day</Label>
                        <Input
                            id="tc-last-day"
                            type="date"
                            value={form.data.last_day}
                            onChange={(event) =>
                                form.setData('last_day', event.target.value)
                            }
                        />
                        <InputError message={form.errors.last_day} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="tc-reason">Reason</Label>
                        <select
                            id="tc-reason"
                            className={selectClassName}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        >
                            {options.transfer_reasons.map((reason) => (
                                <option key={reason.value} value={reason.value}>
                                    {reason.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.reason} />
                    </div>
                    <InputError message={errors.student} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Issue TC
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
