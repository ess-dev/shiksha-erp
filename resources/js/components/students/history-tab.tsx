import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { formatCalendarDate } from '@/lib/format';
import type { StudentProfile } from '@/types/students';

const RESULT_LABELS: Record<string, string> = {
    in_progress: 'In progress',
    promoted: 'Promoted',
    moved: 'Moved section',
    left: 'Left (TC issued)',
};

export default function HistoryTab({ student }: { student: StudentProfile }) {
    return (
        <Card className="gap-0 overflow-hidden p-0">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">
                                Academic year
                            </th>
                            <th className="px-4 py-2.5 font-medium">Class</th>
                            <th className="px-4 py-2.5 font-medium">Campus</th>
                            <th className="px-4 py-2.5 font-medium">From</th>
                            <th className="px-4 py-2.5 font-medium">Result</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {student.enrollments.map((row) => (
                            <tr
                                key={`${row.academic_year}-${row.started_on}-${row.class_label}`}
                            >
                                <td className="px-4 py-3 font-medium">
                                    {row.academic_year}
                                    {row.is_current && (
                                        <Badge className="ml-2">Current</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {row.class_label}
                                    {row.note && (
                                        <div className="text-xs text-muted-foreground">
                                            {row.note}
                                        </div>
                                    )}
                                </td>
                                <td className="px-4 py-3">{row.campus_name}</td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {formatCalendarDate(row.started_on)}
                                    {row.ended_on && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            – {formatCalendarDate(row.ended_on)}
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant="secondary">
                                        {RESULT_LABELS[row.result] ??
                                            row.result}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}
