import { router } from '@inertiajs/react';
import SubjectTeacherController from '@/actions/App/Http/Controllers/School/SubjectTeacherController';
import { selectClassName } from '@/components/school/field';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/school/subjects';
import type { SubjectsOverview } from '@/types';

export default function SubjectTeachers({
    overview,
}: {
    overview: SubjectsOverview;
}) {
    const section = overview.selected_section;

    if (section === null) {
        return (
            <section className="rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                No sections to show yet.
            </section>
        );
    }

    const pickSection = (ulid: string) => {
        router.get(
            index.url(),
            { section: ulid },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const choose = (subjectUlid: string, staffUlid: string) => {
        router.put(
            SubjectTeacherController.update.url({
                section: section.ulid,
                subject: subjectUlid,
            }),
            { staff: staffUlid === '' ? null : staffUlid },
            { preserveScroll: true },
        );
    };

    const showCampus =
        new Set(overview.sections.map((item) => item.campus_name)).size > 1;

    return (
        <>
            <section className="rounded-xl border bg-card">
                <div className="flex flex-wrap items-end justify-between gap-3 p-4">
                    <div className="grid w-full max-w-xs gap-2">
                        <Label htmlFor="teachers-section">Section</Label>
                        <select
                            id="teachers-section"
                            className={selectClassName}
                            value={section.ulid}
                            onChange={(event) =>
                                pickSection(event.target.value)
                            }
                        >
                            {overview.sections.map((item) => (
                                <option key={item.ulid} value={item.ulid}>
                                    {item.name}
                                    {showCampus ? ` · ${item.campus_name}` : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Class teacher:{' '}
                        {section.class_teacher_name ?? 'not assigned'}
                    </p>
                </div>

                {section.rows.length === 0 ? (
                    <p className="px-4 pb-4 text-sm text-muted-foreground">
                        This class has no subjects yet. Add them on the Subjects
                        by class tab.
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
                                        Teacher
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-2.5 sm:table-cell"
                                    >
                                        How it&rsquo;s set
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {section.rows.map((row) => {
                                    const id = `teacher-${row.subject_ulid}`;

                                    return (
                                        <tr key={row.subject_ulid}>
                                            <td className="px-4 py-3 font-semibold">
                                                {row.subject_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                <label
                                                    htmlFor={id}
                                                    className="sr-only"
                                                >
                                                    Teacher for{' '}
                                                    {row.subject_name}
                                                </label>
                                                <select
                                                    id={id}
                                                    className={`${selectClassName} max-w-xs`}
                                                    value={
                                                        row.teacher_ulid ?? ''
                                                    }
                                                    disabled={
                                                        !section.can_change
                                                    }
                                                    onChange={(event) =>
                                                        choose(
                                                            row.subject_ulid,
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        {row.teacher_ulid ===
                                                            null &&
                                                        row.automatic_name !==
                                                            null
                                                            ? `Automatic (${row.automatic_name})`
                                                            : 'Automatic'}
                                                    </option>
                                                    {overview.staff.map(
                                                        (person) => (
                                                            <option
                                                                key={
                                                                    person.ulid
                                                                }
                                                                value={
                                                                    person.ulid
                                                                }
                                                            >
                                                                {person.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </td>
                                            <td className="hidden px-4 py-3 text-xs text-muted-foreground sm:table-cell">
                                                {row.teacher_ulid !== null
                                                    ? 'Chosen here'
                                                    : 'The class teacher until someone is chosen'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
            <p className="text-sm text-muted-foreground">
                The subject teacher enters marks, sets homework and updates
                syllabus progress for that section. Changes apply straight away;
                marks already entered stay.
            </p>
        </>
    );
}
