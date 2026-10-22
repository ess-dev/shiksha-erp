import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import InitialsBadge from '@/components/students/initials-badge';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/students';
import type { StudentRow } from '@/types/students';

const NOT_AVAILABLE = 'Not available yet';

export default function StudentsTable({ rows }: { rows: StudentRow[] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Student
                        </th>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Class
                        </th>
                        <th
                            scope="col"
                            className="hidden px-4 py-2.5 font-medium md:table-cell"
                        >
                            Guardian
                        </th>
                        <th
                            scope="col"
                            className="hidden px-4 py-2.5 font-medium lg:table-cell"
                        >
                            Attendance
                            <span className="block text-[10px] font-normal normal-case">
                                {NOT_AVAILABLE}
                            </span>
                        </th>
                        <th
                            scope="col"
                            className="hidden px-4 py-2.5 font-medium lg:table-cell"
                        >
                            Fees
                            <span className="block text-[10px] font-normal normal-case">
                                {NOT_AVAILABLE}
                            </span>
                        </th>
                        <th scope="col" className="px-4 py-2.5">
                            <span className="sr-only">Open</span>
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((student) => (
                        <tr key={student.ulid} className="hover:bg-muted/30">
                            <td className="px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <InitialsBadge name={student.name} />
                                    <div>
                                        <Link
                                            href={show(student.ulid)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {student.name}
                                        </Link>
                                        {student.is_new && (
                                            <Badge className="ml-2">New</Badge>
                                        )}
                                        {student.status !== 'enrolled' && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {student.status ===
                                                'transferred'
                                                    ? 'Transferred'
                                                    : 'Left'}
                                            </Badge>
                                        )}
                                        {student.has_sibling && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                                title="Has a sibling in the school"
                                            >
                                                Sibling
                                            </Badge>
                                        )}
                                        <div className="text-xs text-muted-foreground">
                                            {student.admission_number}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {student.class_label ?? '—'}
                                <div className="text-xs text-muted-foreground">
                                    {student.roll_number !== null
                                        ? `Roll ${student.roll_number}`
                                        : 'No roll'}
                                    {student.campus_name
                                        ? ` · ${student.campus_name}`
                                        : ''}
                                </div>
                            </td>
                            <td className="hidden px-4 py-3 md:table-cell">
                                {student.guardian_name ?? '—'}
                                {student.guardian_name && (
                                    <div className="text-xs text-muted-foreground">
                                        {student.guardian_relation} ·{' '}
                                        {student.guardian_phone}
                                    </div>
                                )}
                            </td>
                            <td className="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                                —
                            </td>
                            <td className="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                                —
                            </td>
                            <td className="px-4 py-3 text-right">
                                <Link
                                    href={show(student.ulid)}
                                    aria-label={`Open ${student.name}`}
                                    className="inline-flex text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronRight className="size-4" />
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
