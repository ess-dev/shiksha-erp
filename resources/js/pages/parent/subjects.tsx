import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { ParentShell, ParentSubject } from '@/types/parent';

type Props = {
    shell: ParentShell;
    subjects: ParentSubject[];
    child_first_name: string | null;
    section_label: string | null;
    session_name: string;
    is_current_session: boolean;
};

export default function ParentSubjectsPage({
    subjects,
    child_first_name: childName,
    section_label: section,
    session_name: sessionName,
    is_current_session: isCurrent,
}: Props) {
    if (childName === null) {
        return (
            <>
                <Head title="Subjects" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No subjects for {sessionName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Subjects are shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Subjects" />

            <p className="mb-4 text-sm text-muted-foreground">
                {isCurrent
                    ? `What ${childName} is learning in ${section} this session. Teachers update progress as chapters are finished.`
                    : `Subjects ${childName} studied in ${section} (${sessionName}).`}
            </p>

            {subjects.length === 0 ? (
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No subjects to show yet
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {isCurrent
                            ? 'The school has not added subjects for this class yet.'
                            : 'The school saved no syllabus for this session.'}
                    </p>
                </div>
            ) : (
                <div className="grid gap-4">
                    {subjects.map((subject) => (
                        <SubjectCard
                            key={subject.name}
                            subject={subject}
                            childName={childName}
                            showTeacher={isCurrent}
                        />
                    ))}
                </div>
            )}

            <p className="mt-4 text-sm text-muted-foreground">
                Your school sets its own books and syllabus.
            </p>
        </>
    );
}

function SubjectCard({
    subject,
    childName,
    showTeacher,
}: {
    subject: ParentSubject;
    childName: string;
    showTeacher: boolean;
}) {
    const [open, setOpen] = useState(false);
    const listId = `chapters-${subject.name.replace(/\W/g, '')}`;
    const details = [
        subject.book,
        showTeacher ? subject.teacher_name : null,
        subject.periods_per_week === null
            ? null
            : `${subject.periods_per_week} periods a week`,
    ].filter((part): part is string => part !== null && part !== '');

    return (
        <article
            aria-labelledby={`${listId}-title`}
            className="grid gap-2.5 rounded-xl border bg-card p-4"
        >
            <div className="flex items-baseline justify-between gap-3">
                <h2 id={`${listId}-title`} className="font-semibold">
                    {subject.name}
                </h2>
                {subject.percent_done !== null && (
                    <span className="text-sm text-muted-foreground">
                        {subject.percent_done}% done
                    </span>
                )}
            </div>

            {details.length > 0 && (
                <p className="text-sm text-muted-foreground">
                    {details.join(' · ')}
                </p>
            )}

            {subject.percent_done !== null && (
                <div
                    role="progressbar"
                    aria-label={`${subject.percent_done}% of chapters finished`}
                    aria-valuenow={subject.percent_done}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    className="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <span
                        className="block h-full rounded-full bg-primary"
                        style={{ width: `${subject.percent_done}%` }}
                    />
                </div>
            )}

            {subject.current_chapter !== null && (
                <p className="flex items-center gap-2 text-sm">
                    <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                        Now
                    </span>
                    {subject.current_chapter}
                </p>
            )}

            {subject.teacher_note !== null && (
                <p className="rounded-lg bg-muted/60 px-3 py-2.5 text-sm">
                    <strong>From the teacher:</strong> {subject.teacher_note}
                </p>
            )}

            {subject.student_note !== null && (
                <p className="rounded-lg bg-pink-100 px-3 py-2.5 text-sm text-pink-950 dark:bg-pink-950/50 dark:text-pink-100">
                    <strong>For {childName}:</strong> {subject.student_note}
                </p>
            )}

            {subject.chapters.length > 0 && (
                <>
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls={listId}
                        onClick={() => setOpen((value) => !value)}
                        className="mx-auto flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium hover:bg-muted"
                    >
                        {open ? (
                            <ChevronUp className="size-4" aria-hidden />
                        ) : (
                            <ChevronDown className="size-4" aria-hidden />
                        )}
                        {open ? 'Hide' : 'Show'} chapters (
                        {subject.chapters.length})
                    </button>

                    {open && (
                        <>
                            <ol id={listId} className="grid gap-1.5">
                                {subject.chapters.map((chapter) => (
                                    <li
                                        key={chapter.number}
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm',
                                            chapter.state === 'done' &&
                                                'border-green-300 bg-green-50 text-green-950 dark:border-green-800 dark:bg-green-950/40 dark:text-green-100',
                                            chapter.state === 'current' &&
                                                'border-primary/40 bg-primary/5',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                chapter.state === 'done' &&
                                                    'bg-green-600 text-white',
                                                chapter.state === 'current' &&
                                                    'bg-primary text-primary-foreground',
                                                chapter.state === 'upcoming' &&
                                                    'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {chapter.number}
                                        </span>
                                        <span className="min-w-0 flex-1 break-words">
                                            {chapter.title}
                                        </span>
                                        {chapter.state === 'done' && (
                                            <span className="text-xs text-green-700 dark:text-green-300">
                                                Done
                                            </span>
                                        )}
                                        {chapter.state === 'current' && (
                                            <span className="text-xs text-primary">
                                                In class now
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                            {subject.assessment !== null && (
                                <p className="text-xs text-muted-foreground">
                                    Assessment: {subject.assessment}
                                </p>
                            )}
                        </>
                    )}
                </>
            )}
        </article>
    );
}
