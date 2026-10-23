import { Head } from '@inertiajs/react';
import { useState } from 'react';
import SubjectList from '@/components/school/subject-list';
import SubjectTeachers from '@/components/school/subject-teachers';
import SubjectsByClass from '@/components/school/subjects-by-class';
import { cn } from '@/lib/utils';
import { index } from '@/routes/school/subjects';
import type { SubjectsOverview } from '@/types';

type Tab = 'classes' | 'list' | 'teachers';

const TABS: { value: Tab; label: string }[] = [
    { value: 'classes', label: 'Subjects by class' },
    { value: 'list', label: 'Subject list' },
    { value: 'teachers', label: 'Subject teachers' },
];

export default function Subjects({ overview }: { overview: SubjectsOverview }) {
    const [tab, setTab] = useState<Tab>('classes');

    return (
        <>
            <Head title="Subjects" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Subjects
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            The school&rsquo;s subject list, what each class
                            studies and who teaches it.
                        </p>
                    </div>
                    <div
                        role="group"
                        aria-label="Choose view"
                        className="flex rounded-lg border bg-muted/40 p-1"
                    >
                        {TABS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={tab === item.value}
                                onClick={() => setTab(item.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground',
                                    tab === item.value &&
                                        'bg-background text-primary shadow-xs',
                                )}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                {tab === 'classes' && <SubjectsByClass overview={overview} />}
                {tab === 'list' && <SubjectList overview={overview} />}
                {tab === 'teachers' && <SubjectTeachers overview={overview} />}
            </div>
        </>
    );
}

Subjects.layout = {
    breadcrumbs: [
        {
            title: 'Subjects',
            href: index(),
        },
    ],
};
