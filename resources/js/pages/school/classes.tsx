import { Head } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import ClassSectionCard from '@/components/school/class-section-card';
import ClassesEditor from '@/components/school/classes-editor';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/school/classes';
import type { ClassesOverview, ClassSection } from '@/types';

function Summary({
    label,
    value,
    note,
}: {
    label: string;
    value: string;
    note?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-2xl font-semibold">{value}</div>
            {note !== undefined && (
                <div className="text-xs text-muted-foreground">{note}</div>
            )}
        </div>
    );
}

export default function Classes({ overview }: { overview: ClassesOverview }) {
    const [editing, setEditing] = useState(false);

    const campusNames = new Map(
        overview.campuses.map((campus) => [campus.ulid, campus.name]),
    );
    const sections = overview.sections;
    const students = sections.reduce((sum, item) => sum + item.students, 0);
    const seats = sections.reduce((sum, item) => sum + (item.capacity ?? 0), 0);
    const marked = sections.filter(
        (item) => item.register_saved_at !== null,
    ).length;
    const withoutTeacher = sections.filter(
        (item) => item.class_teacher_name === null,
    ).length;

    const groups = new Map<number, ClassSection[]>();

    for (const section of sections) {
        groups.set(section.class_level, [
            ...(groups.get(section.class_level) ?? []),
            section,
        ]);
    }

    const showCampus =
        new Set(sections.map((item) => item.campus_ulid)).size > 1;

    return (
        <>
            <Head title="Classes and sections" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Classes and sections
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {editing
                                ? 'Editing classes and sections'
                                : `${overview.campus_name ?? 'All campuses'}${
                                      overview.academic_year_name === null
                                          ? ''
                                          : ` · academic year ${overview.academic_year_name}`
                                  }`}
                        </p>
                    </div>
                    {overview.can_manage && !editing && (
                        <Button
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <SlidersHorizontal aria-hidden />
                            Edit classes and sections
                        </Button>
                    )}
                </div>

                {overview.academic_year_ulid === null ? (
                    <p className="text-sm text-muted-foreground">
                        There is no current academic year yet. Set one up in
                        School structure first.
                    </p>
                ) : editing ? (
                    <ClassesEditor
                        overview={overview}
                        onDone={() => setEditing(false)}
                    />
                ) : (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Summary
                                label="Sections"
                                value={String(sections.length)}
                            />
                            <Summary
                                label="Students"
                                value={String(students)}
                                note={`${seats > 0 ? Math.round((students / seats) * 100) : 0}% of seats filled`}
                            />
                            {overview.has_attendance && (
                                <Summary
                                    label="Register marked today"
                                    value={`${marked}/${sections.length}`}
                                />
                            )}
                            <Summary
                                label="Without class teacher"
                                value={String(withoutTeacher)}
                            />
                        </div>

                        {sections.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No classes here yet.
                                {overview.can_manage &&
                                    ' Use Edit classes and sections to add one.'}
                            </p>
                        )}

                        {[...groups.entries()]
                            .sort((a, b) => a[0] - b[0])
                            .map(([level, items]) => (
                                <section key={level} className="grid gap-2">
                                    <h2 className="text-sm font-semibold">
                                        {items[0]?.class_name}
                                    </h2>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {items.map((section) => (
                                            <ClassSectionCard
                                                key={section.ulid}
                                                section={section}
                                                campusName={
                                                    campusNames.get(
                                                        section.campus_ulid,
                                                    ) ?? ''
                                                }
                                                showCampus={showCampus}
                                                hasAttendance={
                                                    overview.has_attendance
                                                }
                                                timezone={overview.timezone}
                                            />
                                        ))}
                                    </div>
                                </section>
                            ))}
                    </>
                )}
            </div>
        </>
    );
}

Classes.layout = {
    breadcrumbs: [
        {
            title: 'Classes and sections',
            href: index(),
        },
    ],
};
