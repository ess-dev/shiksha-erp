import { Link } from '@inertiajs/react';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { formatClock } from '@/lib/format';
import { index as students } from '@/routes/students';
import type { ClassSection } from '@/types';

export default function ClassSectionCard({
    section,
    campusName,
    showCampus,
    hasAttendance,
    timezone,
}: {
    section: ClassSection;
    campusName: string;
    showCampus: boolean;
    hasAttendance: boolean;
    timezone: string;
}) {
    const percent =
        section.capacity !== null && section.capacity > 0
            ? Math.min(100, (section.students / section.capacity) * 100)
            : 0;

    return (
        <article className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex items-start justify-between gap-2">
                <strong>
                    {section.class_name}-{section.name}
                </strong>
                <span className="text-xs text-muted-foreground">
                    {[showCampus ? campusName : null, section.room]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            </div>

            <div className="flex items-center gap-2">
                {section.class_teacher_name !== null && (
                    <InitialsBadge
                        name={section.class_teacher_name}
                        className="size-8"
                    />
                )}
                <div>
                    <div className="text-xs text-muted-foreground">
                        Class teacher
                    </div>
                    <div className="text-sm font-semibold">
                        {section.class_teacher_name ?? 'Not assigned'}
                    </div>
                </div>
            </div>

            <div>
                <div className="flex justify-between text-xs">
                    <span>
                        {section.students}
                        {section.capacity !== null
                            ? ` / ${section.capacity}`
                            : ''}{' '}
                        students
                    </span>
                    <span className="text-muted-foreground">
                        {section.girls} girls ·{' '}
                        {section.students - section.girls} boys
                    </span>
                </div>
                <div
                    className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                    aria-hidden
                >
                    <span
                        className="block h-full rounded-full bg-primary"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>

            {hasAttendance && (
                <div className="flex justify-between text-xs">
                    {section.register_saved_at !== null ? (
                        <>
                            <span className="text-green-700 dark:text-green-400">
                                Register saved{' '}
                                {formatClock(
                                    section.register_saved_at,
                                    timezone,
                                )}
                            </span>
                            <span className="text-muted-foreground">
                                {section.register_absent} absent
                            </span>
                        </>
                    ) : (
                        <span className="text-amber-700 dark:text-amber-400">
                            Register not marked yet
                        </span>
                    )}
                </div>
            )}

            <Button asChild variant="outline" size="sm" className="w-full">
                <Link href={students({ query: { class: section.ulid } })}>
                    View students
                </Link>
            </Button>
        </article>
    );
}
