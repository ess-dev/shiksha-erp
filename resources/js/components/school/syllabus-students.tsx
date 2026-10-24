import { router } from '@inertiajs/react';
import { useState } from 'react';
import SyllabusController from '@/actions/App/Http/Controllers/School/SyllabusController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { initials } from '@/lib/format';
import type { SyllabusDetail } from '@/types';

/**
 * One private note per student, shown only to that student's parents under
 * this subject.
 */
export default function SyllabusStudents({
    detail,
}: {
    detail: SyllabusDetail;
}) {
    const [notes, setNotes] = useState<Record<string, string>>(() =>
        Object.fromEntries(detail.students.map((s) => [s.ulid, s.note])),
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const save = () => {
        const changed = Object.fromEntries(
            detail.students
                .filter(
                    (student) => (notes[student.ulid] ?? '') !== student.note,
                )
                .map((student) => [student.ulid, notes[student.ulid] ?? '']),
        );

        if (Object.keys(changed).length === 0) {
            return;
        }

        router.put(
            SyllabusController.updateStudents.url({
                section: detail.section_ulid,
                subject: detail.subject_ulid,
            }),
            { notes: changed },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setErrors({}),
                onError: setErrors,
            },
        );
    };

    return (
        <div className="grid gap-4 p-4">
            <p className="text-sm text-muted-foreground">
                A private note only this student&rsquo;s parents see, under{' '}
                {detail.subject_name}.
            </p>

            {detail.students.length === 0 ? (
                <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    No students are enrolled in {detail.section_name} yet.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5">
                                    Roll
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Student
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    {detail.subject_name} note for parents
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {detail.students.map((student) => (
                                <tr key={student.ulid}>
                                    <td className="px-4 py-3 align-top tabular-nums">
                                        {student.roll_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <div className="flex items-center gap-2">
                                            <span
                                                aria-hidden="true"
                                                className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                                            >
                                                {initials(student.name)}
                                            </span>
                                            <strong>{student.name}</strong>
                                        </div>
                                    </td>
                                    <td className="min-w-64 px-4 py-3">
                                        <textarea
                                            aria-label={`Note for ${student.name}'s parents`}
                                            rows={2}
                                            maxLength={240}
                                            value={notes[student.ulid] ?? ''}
                                            placeholder="Optional"
                                            onChange={(event) =>
                                                setNotes((previous) => ({
                                                    ...previous,
                                                    [student.ulid]:
                                                        event.target.value,
                                                }))
                                            }
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <InputError message={errors.syllabus ?? errors.notes} />

            {detail.students.length > 0 && (
                <div className="flex justify-end">
                    <Button type="button" disabled={processing} onClick={save}>
                        {processing && <Spinner />}
                        Save notes
                    </Button>
                </div>
            )}
        </div>
    );
}
