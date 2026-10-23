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
import { section } from '@/routes/students';
import type { StudentProfile } from '@/types/students';

export default function ChangeSectionDialog({
    student,
    onClose,
}: {
    student: StudentProfile;
    onClose: () => void;
}) {
    const firstOpen = student.section_options.find((option) => !option.is_full);
    const form = useForm({
        section: firstOpen?.ulid ?? '',
        reason: '',
    });

    const errors = form.errors as Record<string, string>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(section.url(student.ulid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Change section</DialogTitle>
                    <DialogDescription>
                        Moves {student.name} from {student.class_label} (
                        {student.campus_name}). Past attendance and marks stay
                        with the old section; the new class teacher marks from
                        tomorrow.
                    </DialogDescription>
                </DialogHeader>

                {student.section_options.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        There is no other section of this class that you can
                        move the student to.
                    </p>
                ) : (
                    <form onSubmit={submit} className="grid gap-4" noValidate>
                        <div className="grid gap-1.5">
                            <Label htmlFor="move-section">New section</Label>
                            <select
                                id="move-section"
                                className={selectClassName}
                                value={form.data.section}
                                onChange={(event) =>
                                    form.setData('section', event.target.value)
                                }
                            >
                                {student.section_options.map((option) => (
                                    <option
                                        key={option.ulid}
                                        value={option.ulid}
                                        disabled={option.is_full}
                                    >
                                        {option.label} · {option.campus_name} ·{' '}
                                        {option.enrolled}
                                        {option.capacity !== null
                                            ? `/${option.capacity}`
                                            : ''}
                                        {option.is_full ? ' (full)' : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.section} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="move-reason">Reason</Label>
                            <Input
                                id="move-reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                placeholder="Parent request: shifted closer to the new campus"
                            />
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
                                disabled={
                                    form.processing || form.data.section === ''
                                }
                            >
                                {form.processing && <Spinner />}
                                Move student
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
