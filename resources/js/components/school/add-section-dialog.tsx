import { useForm } from '@inertiajs/react';
import SectionController from '@/actions/App/Http/Controllers/School/SectionController';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export type AddSectionTarget = {
    academicYear: string;
    campusUlid: string;
    campusName: string;
    classUlid: string;
    className: string;
    suggestedName: string;
};

export default function AddSectionDialog({
    target,
    teachers,
    onClose,
}: {
    target: AddSectionTarget;
    teachers: { ulid: string; name: string }[];
    onClose: () => void;
}) {
    const form = useForm({
        academic_year: target.academicYear,
        campus: target.campusUlid,
        class: target.classUlid,
        name: target.suggestedName,
        capacity: '40',
        room: '',
        class_teacher: '',
    });

    const errors = form.errors as Record<string, string>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(SectionController.store.url(), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        Add a section to {target.className}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <Field
                        id="new-section-name"
                        label="Section name"
                        error={errors.name ?? errors.structure}
                    >
                        <Input
                            id="new-section-name"
                            value={form.data.name}
                            maxLength={3}
                            className="uppercase"
                            autoFocus
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </Field>

                    <div className="grid grid-cols-2 gap-3">
                        <Field
                            id="new-section-capacity"
                            label="Seats"
                            error={errors.capacity}
                        >
                            <Input
                                id="new-section-capacity"
                                type="number"
                                min={1}
                                max={500}
                                value={form.data.capacity}
                                onChange={(event) =>
                                    form.setData('capacity', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="new-section-room"
                            label="Room"
                            error={errors.room}
                        >
                            <Input
                                id="new-section-room"
                                maxLength={30}
                                placeholder="Room 215"
                                value={form.data.room}
                                onChange={(event) =>
                                    form.setData('room', event.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <div className="grid gap-2">
                        <Field
                            id="new-section-teacher"
                            label="Class teacher (optional)"
                            error={errors.class_teacher}
                        >
                            <select
                                id="new-section-teacher"
                                className={selectClassName}
                                value={form.data.class_teacher}
                                onChange={(event) =>
                                    form.setData(
                                        'class_teacher',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Assign later</option>
                                {teachers.map((person) => (
                                    <option
                                        key={person.ulid}
                                        value={person.ulid}
                                    >
                                        {person.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <p className="text-xs text-muted-foreground">
                            Teachers who don&rsquo;t lead a class yet.
                        </p>
                    </div>

                    <InputError
                        message={errors.academic_year ?? errors.campus}
                    />

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
                            Add section
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
