import { Link } from '@inertiajs/react';
import { DashboardCard } from '@/components/dashboard/dashboard-card';
import InitialsBadge from '@/components/students/initials-badge';
import { Badge } from '@/components/ui/badge';
import { useCan } from '@/hooks/use-can';
import { formatCalendarDay, formatClock } from '@/lib/format';
import { show as studentShow } from '@/routes/students';
import type { AttendanceDashboard } from '@/types';

/**
 * Students marked absent today, with the primary guardian and who saved the
 * register. Guardian phones arrive masked unless the viewer may see them.
 */
export default function AbsentToday({
    attendance,
    campusLabel,
    showCampus,
    timezone,
}: {
    attendance: AttendanceDashboard;
    campusLabel: string;
    showCampus: boolean;
    timezone: string;
}) {
    const canOpenStudent = useCan('students.view');
    const toMark = attendance.unmarked_sections.length;
    const rows = attendance.absentees;

    return (
        <DashboardCard
            title="Absent today"
            subtitle={`${formatCalendarDay(attendance.today)} · ${campusLabel}${toMark > 0 ? ` · ${toMark} section${toMark === 1 ? '' : 's'} still to mark` : ''}`}
        >
            {rows.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {attendance.closed_reason !== null
                        ? `No school today: ${attendance.closed_reason}.`
                        : attendance.sections_marked > 0
                          ? 'No absences recorded yet today. Every marked student is present.'
                          : 'Teachers have not marked attendance yet.'}
                </p>
            ) : (
                <div className="-mx-4 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-y bg-muted/40 text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Student
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Class
                                </th>
                                <th
                                    scope="col"
                                    className="hidden px-4 py-2 font-medium md:table-cell"
                                >
                                    Primary guardian
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    className="hidden px-4 py-2 font-medium md:table-cell"
                                >
                                    Marked by
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row) => (
                                <tr
                                    key={row.student_ulid}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-center gap-2.5">
                                            <InitialsBadge
                                                name={row.name}
                                                className="size-8"
                                            />
                                            <div>
                                                {canOpenStudent ? (
                                                    <Link
                                                        href={studentShow(
                                                            row.student_ulid,
                                                        )}
                                                        className="font-semibold text-primary hover:underline"
                                                    >
                                                        {row.name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-semibold">
                                                        {row.name}
                                                    </span>
                                                )}
                                                <div className="text-xs text-muted-foreground">
                                                    {row.roll_number !== null &&
                                                        `Roll ${row.roll_number} · `}
                                                    {row.admission_number}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5 whitespace-nowrap">
                                        {row.section_label}
                                        {showCampus && (
                                            <div className="text-xs text-muted-foreground">
                                                {row.campus_name}
                                            </div>
                                        )}
                                    </td>
                                    <td className="hidden px-4 py-2.5 md:table-cell">
                                        {row.guardian_name ?? '—'}
                                        {row.guardian_name !== null && (
                                            <div className="text-xs text-muted-foreground">
                                                {[
                                                    row.guardian_relation,
                                                    row.guardian_phone,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <Badge variant="destructive">
                                            {row.days_in_a_row > 1
                                                ? `${row.days_in_a_row} days in a row`
                                                : 'Absent'}
                                        </Badge>
                                    </td>
                                    <td className="hidden px-4 py-2.5 text-xs whitespace-nowrap text-muted-foreground md:table-cell">
                                        {row.marked_by ?? '—'} ·{' '}
                                        {formatClock(row.marked_at, timezone)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {attendance.absentees_total > rows.length && (
                        <p className="px-4 pt-3 text-xs text-muted-foreground">
                            Showing {rows.length} of{' '}
                            {attendance.absentees_total}. Open Attendance for
                            each class.
                        </p>
                    )}
                </div>
            )}
        </DashboardCard>
    );
}
