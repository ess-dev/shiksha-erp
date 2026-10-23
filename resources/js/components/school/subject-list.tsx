import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SubjectCatalogController from '@/actions/App/Http/Controllers/School/SubjectCatalogController';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import {
    classRange,
    SUBJECT_TYPES,
    SubjectTypeBadge,
} from '@/components/school/subject-type';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { SubjectKind, SubjectRow, SubjectsOverview } from '@/types';

function SubjectDialog({
    subject,
    overview,
    onClose,
}: {
    subject: SubjectRow | null;
    overview: SubjectsOverview;
    onClose: () => void;
}) {
    const isNew = subject === null;
    const [name, setName] = useState(subject?.name ?? '');
    const [code, setCode] = useState(subject?.code ?? '');
    const [type, setType] = useState<SubjectKind>(subject?.type ?? 'core');
    const [classes, setClasses] = useState<Set<string>>(new Set());
    const [confirmRename, setConfirmRename] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const changeable = overview.classes.filter((item) => item.can_change);
    const renaming = !isNew && name.trim() !== subject.name;
    const taughtNote =
        !isNew && subject.taught_in.length > 0
            ? `Taught in ${classRange(subject.taught_in)}. Making it co-scholastic hides it from marks entry and report-card marks.`
            : null;

    const send = () => {
        const data = { name, code, type, classes: [...classes] };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (bag: Record<string, string>) => {
                setErrors(bag);
                setConfirmRename(false);
            },
        };

        if (isNew) {
            router.post(SubjectCatalogController.store.url(), data, options);
        } else {
            router.patch(
                SubjectCatalogController.update.url(subject.ulid),
                data,
                options,
            );
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (renaming && subject.taught_in.length > 0) {
            setConfirmRename(true);

            return;
        }

        send();
    };

    if (confirmRename && !isNew) {
        return (
            <Dialog open onOpenChange={(open) => !open && onClose()}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            Rename {subject.name} to {name.trim()}?
                        </DialogTitle>
                        <DialogDescription>
                            Everything linked to this subject in{' '}
                            {classRange(subject.taught_in)} moves to the new
                            name.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmRename(false)}
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            onClick={send}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Rename
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {isNew ? 'Add a subject' : `Edit ${subject.name}`}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <Field id="subject-name" label="Name" error={errors.name}>
                        <Input
                            id="subject-name"
                            value={name}
                            maxLength={40}
                            placeholder="Punjabi"
                            autoFocus
                            onChange={(event) => setName(event.target.value)}
                        />
                    </Field>

                    <Field
                        id="subject-code"
                        label="Short code"
                        error={errors.code}
                    >
                        <Input
                            id="subject-code"
                            value={code}
                            maxLength={20}
                            placeholder="PUN"
                            className="uppercase"
                            onChange={(event) => setCode(event.target.value)}
                        />
                    </Field>

                    <Field id="subject-type" label="Type" error={errors.type}>
                        <select
                            id="subject-type"
                            className={selectClassName}
                            value={type}
                            onChange={(event) =>
                                setType(event.target.value as SubjectKind)
                            }
                        >
                            {SUBJECT_TYPES.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                        {taughtNote !== null && (
                            <p className="text-xs text-muted-foreground">
                                {taughtNote}
                            </p>
                        )}
                    </Field>

                    {isNew && changeable.length > 0 && (
                        <fieldset>
                            <legend className="mb-2 text-sm font-medium">
                                Add it to classes (optional)
                            </legend>
                            <div className="flex flex-wrap gap-x-4 gap-y-2">
                                {changeable.map((item) => (
                                    <div
                                        key={item.ulid}
                                        className="flex items-center gap-1.5"
                                    >
                                        <Checkbox
                                            id={`add-to-${item.ulid}`}
                                            checked={classes.has(item.ulid)}
                                            onCheckedChange={(value) => {
                                                const next = new Set(classes);

                                                if (value === true) {
                                                    next.add(item.ulid);
                                                } else {
                                                    next.delete(item.ulid);
                                                }

                                                setClasses(next);
                                            }}
                                        />
                                        <Label
                                            htmlFor={`add-to-${item.ulid}`}
                                            className="font-normal"
                                            aria-label={item.name}
                                        >
                                            {item.name.replace(/^Class /, '')}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </fieldset>
                    )}

                    <InputError message={errors.structure ?? errors.classes} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isNew ? 'Add subject' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({
    subject,
    onClose,
}: {
    subject: SubjectRow;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const remove = () => {
        router.delete(SubjectCatalogController.destroy.url(subject.ulid), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (bag) => setError(bag.structure),
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete {subject.name}?</DialogTitle>
                    <DialogDescription>
                        It isn&rsquo;t taught in any class, so nothing else
                        changes.
                    </DialogDescription>
                </DialogHeader>
                <InputError message={error} />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={remove}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function SubjectList({
    overview,
}: {
    overview: SubjectsOverview;
}) {
    const [dialog, setDialog] = useState<
        | { kind: 'add' }
        | { kind: 'edit'; subject: SubjectRow }
        | { kind: 'delete'; subject: SubjectRow }
        | null
    >(null);

    return (
        <section className="rounded-xl border bg-card">
            <div className="flex flex-wrap items-start justify-between gap-3 p-4 pb-3">
                <div>
                    <h2 className="font-semibold">Subject list</h2>
                    <p className="text-sm text-muted-foreground">
                        One list for the whole school. Renaming a subject
                        carries its marks, teachers, syllabus and homework
                        across.
                    </p>
                </div>
                {overview.can_configure && (
                    <Button onClick={() => setDialog({ kind: 'add' })}>
                        <Plus aria-hidden />
                        Add subject
                    </Button>
                )}
            </div>

            {overview.subjects.length === 0 ? (
                <p className="px-4 pb-4 text-sm text-muted-foreground">
                    No subject has been added yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5">
                                    Subject
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Code
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Type
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Taught in
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {overview.subjects.map((subject) => (
                                <tr key={subject.ulid}>
                                    <td className="px-4 py-3 font-semibold">
                                        {subject.name}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {subject.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        <SubjectTypeBadge type={subject.type} />
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {subject.taught_in.length > 0 ? (
                                            classRange(subject.taught_in)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Not assigned
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1.5">
                                            {subject.can_change ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDialog({
                                                            kind: 'edit',
                                                            subject,
                                                        })
                                                    }
                                                >
                                                    Edit
                                                    <span className="sr-only">
                                                        {' '}
                                                        {subject.name}
                                                    </span>
                                                </Button>
                                            ) : (
                                                overview.can_configure && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Used outside your reach
                                                    </span>
                                                )
                                            )}
                                            {subject.can_delete && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    aria-label={`Delete ${subject.name}`}
                                                    onClick={() =>
                                                        setDialog({
                                                            kind: 'delete',
                                                            subject,
                                                        })
                                                    }
                                                >
                                                    <Trash2 aria-hidden />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {dialog?.kind === 'add' && (
                <SubjectDialog
                    subject={null}
                    overview={overview}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'edit' && (
                <SubjectDialog
                    subject={dialog.subject}
                    overview={overview}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'delete' && (
                <DeleteDialog
                    subject={dialog.subject}
                    onClose={() => setDialog(null)}
                />
            )}
        </section>
    );
}
