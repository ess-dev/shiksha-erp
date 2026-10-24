import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { selectClassName } from '@/components/school/field';
import SyllabusClass from '@/components/school/syllabus-class';
import SyllabusSection from '@/components/school/syllabus-section';
import SyllabusStudents from '@/components/school/syllabus-students';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index } from '@/routes/school/syllabus';
import type { SyllabusScreen } from '@/types';

type Level = 'class' | 'section' | 'student';

const LEVELS: { value: Level; label: string }[] = [
    { value: 'class', label: 'Whole class' },
    { value: 'section', label: 'Section' },
    { value: 'student', label: 'Student' },
];

export default function Syllabus({ screen }: { screen: SyllabusScreen }) {
    const [level, setLevel] = useState<Level>('section');
    const detail = screen.selected;

    const showCampus =
        new Set(screen.choices.map((choice) => choice.campus_name)).size > 1;

    const pick = (value: string) => {
        const [section, subject] = value.split('|');

        router.get(
            index.url(),
            { section, subject },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Subjects" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Subjects
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Textbook, syllabus progress and notes that parents
                            can read, for {screen.year_name}.
                        </p>
                    </div>
                    <div
                        role="group"
                        aria-label="Choose what to update"
                        className="flex rounded-lg border bg-muted/40 p-1"
                    >
                        {LEVELS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={level === item.value}
                                onClick={() => setLevel(item.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground',
                                    level === item.value &&
                                        'bg-background text-primary shadow-xs',
                                )}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                {detail === null ? (
                    <section className="rounded-xl border bg-card p-6 text-sm text-muted-foreground">
                        <p className="font-medium text-foreground">
                            No subjects assigned
                        </p>
                        <p>
                            Ask the coordinator to choose you as the subject
                            teacher, or to make you a class teacher.
                        </p>
                    </section>
                ) : (
                    <section className="rounded-xl border bg-card">
                        <div className="grid gap-2 p-4 sm:max-w-md">
                            <Label htmlFor="syllabus-pick">
                                Class and subject
                            </Label>
                            <select
                                id="syllabus-pick"
                                className={selectClassName}
                                value={`${detail.section_ulid}|${detail.subject_ulid}`}
                                onChange={(event) => pick(event.target.value)}
                            >
                                {screen.choices.map((choice) => (
                                    <option
                                        key={`${choice.section_ulid}|${choice.subject_ulid}`}
                                        value={`${choice.section_ulid}|${choice.subject_ulid}`}
                                    >
                                        {choice.section_name} ·{' '}
                                        {choice.subject_name}
                                        {showCampus
                                            ? ` · ${choice.campus_name}`
                                            : ''}
                                        {choice.via_class_teacher
                                            ? ' (class teacher)'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="border-t">
                            {level === 'class' && (
                                <SyllabusClass
                                    key={`class-${detail.section_ulid}-${detail.subject_ulid}-${detail.class_syllabus.updated_on}`}
                                    detail={detail}
                                />
                            )}
                            {level === 'section' && (
                                <SyllabusSection
                                    key={`section-${detail.section_ulid}-${detail.subject_ulid}-${detail.class_syllabus.updated_on}-${detail.progress.updated_on}`}
                                    detail={detail}
                                />
                            )}
                            {level === 'student' && (
                                <SyllabusStudents
                                    key={`student-${detail.section_ulid}-${detail.subject_ulid}`}
                                    detail={detail}
                                />
                            )}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

Syllabus.layout = {
    breadcrumbs: [
        {
            title: 'Subjects',
            href: index(),
        },
    ],
};
