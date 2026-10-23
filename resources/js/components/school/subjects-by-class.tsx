import { router } from '@inertiajs/react';
import { useState } from 'react';
import ClassSubjectsController from '@/actions/App/Http/Controllers/School/ClassSubjectsController';
import InputError from '@/components/input-error';
import { SUBJECT_TYPES } from '@/components/school/subject-type';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { SubjectClass, SubjectRow, SubjectsOverview } from '@/types';

function ChangeDialog({
    schoolClass,
    subjects,
    onClose,
}: {
    schoolClass: SubjectClass;
    subjects: SubjectRow[];
    onClose: () => void;
}) {
    const [chosen, setChosen] = useState(new Set(schoolClass.subject_ulids));
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const toggle = (ulid: string, on: boolean) => {
        const next = new Set(chosen);

        if (on) {
            next.add(ulid);
        } else {
            next.delete(ulid);
        }

        setChosen(next);
    };

    const save = () => {
        router.put(
            ClassSubjectsController.update.url(schoolClass.ulid),
            { subjects: [...chosen] },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
                onError: setErrors,
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Subjects for {schoolClass.name}</DialogTitle>
                </DialogHeader>

                <div className="grid max-h-[60vh] gap-3 overflow-y-auto">
                    {SUBJECT_TYPES.map((type) => {
                        const group = subjects.filter(
                            (subject) => subject.type === type.value,
                        );

                        if (group.length === 0) {
                            return null;
                        }

                        return (
                            <fieldset
                                key={type.value}
                                className="rounded-xl border px-4 pt-1 pb-3"
                            >
                                <legend className="px-1.5 text-sm font-semibold">
                                    {type.label}
                                </legend>
                                <div className="flex flex-wrap gap-x-5 gap-y-2 pt-2">
                                    {group.map((subject) => (
                                        <div
                                            key={subject.ulid}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`class-subject-${subject.ulid}`}
                                                checked={chosen.has(
                                                    subject.ulid,
                                                )}
                                                onCheckedChange={(value) =>
                                                    toggle(
                                                        subject.ulid,
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`class-subject-${subject.ulid}`}
                                                className="font-normal"
                                            >
                                                {subject.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </fieldset>
                        );
                    })}
                </div>

                <InputError
                    message={
                        errors.subjects ??
                        errors.structure ??
                        errors['subjects.0']
                    }
                />
                <p className="text-sm text-muted-foreground">
                    Missing a subject? Add it on the Subject list tab first.
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={save} disabled={processing}>
                        {processing && <Spinner />}
                        Save for {schoolClass.name}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function SubjectsByClass({
    overview,
}: {
    overview: SubjectsOverview;
}) {
    const [editing, setEditing] = useState<SubjectClass | null>(null);
    const subjectsByUlid = new Map(
        overview.subjects.map((subject) => [subject.ulid, subject]),
    );

    return (
        <section className="rounded-xl border bg-card">
            <div className="p-4 pb-3">
                <h2 className="font-semibold">Subjects by class</h2>
                <p className="text-sm text-muted-foreground">
                    Applies to every section of the class on every campus.
                    Examined subjects appear in marks entry, report cards and
                    the parent app.
                </p>
            </div>

            {overview.classes.length === 0 ? (
                <p className="px-4 pb-4 text-sm text-muted-foreground">
                    No class has been added yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5">
                                    Class
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Subjects
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    <span className="sr-only">Change</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {overview.classes.map((schoolClass) => (
                                <tr key={schoolClass.ulid}>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="font-semibold">
                                            {schoolClass.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {schoolClass.section_count === 1
                                                ? '1 section'
                                                : `${schoolClass.section_count} sections`}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {schoolClass.subject_ulids.length ===
                                        0 ? (
                                            <span className="text-muted-foreground">
                                                No subjects yet
                                            </span>
                                        ) : (
                                            <ul className="flex flex-wrap gap-1.5">
                                                {schoolClass.subject_ulids.map(
                                                    (ulid) => {
                                                        const subject =
                                                            subjectsByUlid.get(
                                                                ulid,
                                                            );

                                                        return (
                                                            <li
                                                                key={ulid}
                                                                className="rounded-full border bg-muted/40 px-3 py-1 text-xs"
                                                            >
                                                                {subject?.name}
                                                                {subject?.type ===
                                                                    'co_scholastic' && (
                                                                    <span className="text-muted-foreground">
                                                                        {' '}
                                                                        · no
                                                                        marks
                                                                    </span>
                                                                )}
                                                            </li>
                                                        );
                                                    },
                                                )}
                                            </ul>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {schoolClass.can_change && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(schoolClass)
                                                }
                                            >
                                                Change
                                                <span className="sr-only">
                                                    {' '}
                                                    subjects of{' '}
                                                    {schoolClass.name}
                                                </span>
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {editing !== null && (
                <ChangeDialog
                    schoolClass={editing}
                    subjects={overview.subjects}
                    onClose={() => setEditing(null)}
                />
            )}
        </section>
    );
}
