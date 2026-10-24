import { formatTenths } from '@/components/exams/grading';
import { formatCalendarDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ReportCard } from '@/types/parent';

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

/**
 * The school's report card for one student and one exam, as parents, the
 * class teacher and approvers see it. Wrap it in `print-area` to print only
 * the card from a dialog.
 */
export default function ReportCardDocument({
    card,
    className,
}: {
    card: ReportCard;
    className?: string;
}) {
    const result = card.result;
    const attendance = card.attendance;

    return (
        <article
            className={cn(
                'rounded-md border bg-background p-8 text-foreground print:border-0 print:p-0',
                className,
            )}
        >
            <header className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b pb-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {card.school_name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {card.campus_name}
                        {card.campus_address !== null &&
                            ` · ${card.campus_address}`}
                        {card.board !== null && ` · ${card.board}`}
                    </p>
                </div>
                <div className="text-right">
                    <p className="font-semibold">Report card</p>
                    <p>{result.name}</p>
                    <p className="text-sm text-muted-foreground">
                        Session {card.session_name}
                    </p>
                </div>
            </header>

            <dl className="mb-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <Detail label="Student" value={card.student_name} />
                <Detail label="Class" value={card.class_label} />
                <Detail label="Admission no." value={card.admission_number} />
                <Detail
                    label="Date of birth"
                    value={formatCalendarDate(card.born_on)}
                />
                <Detail
                    label="Attendance"
                    value={
                        attendance === null ||
                        attendance.percent_tenths === null
                            ? '—'
                            : `${attendance.present + attendance.late} / ${attendance.school_days} days (${formatTenths(attendance.percent_tenths)}%)`
                    }
                />
                <Detail
                    label="Class teacher"
                    value={card.class_teacher_name ?? '—'}
                />
            </dl>

            <table className="w-full border text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th scope="col" className="border px-3 py-2">
                            Subject
                        </th>
                        <th scope="col" className="border px-3 py-2">
                            Marks (out of {result.max_marks})
                        </th>
                        <th scope="col" className="border px-3 py-2">
                            %
                        </th>
                        <th scope="col" className="border px-3 py-2">
                            Grade
                        </th>
                        <th scope="col" className="border px-3 py-2">
                            Grade point
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {result.subjects.map((subject) => (
                        <tr key={subject.subject_name}>
                            <td className="border px-3 py-2">
                                {subject.subject_name}
                            </td>
                            {subject.state === 'published' ? (
                                <>
                                    <td className="border px-3 py-2 tabular-nums">
                                        {subject.marks}
                                    </td>
                                    <td className="border px-3 py-2 tabular-nums">
                                        {subject.percent}%
                                    </td>
                                    <td className="border px-3 py-2 font-semibold">
                                        {subject.grade}
                                    </td>
                                    <td className="border px-3 py-2 tabular-nums">
                                        {subject.grade_point}
                                    </td>
                                </>
                            ) : subject.state === 'absent' ? (
                                <>
                                    <td className="border px-3 py-2">AB</td>
                                    <td className="border px-3 py-2">—</td>
                                    <td className="border px-3 py-2">AB</td>
                                    <td className="border px-3 py-2">—</td>
                                </>
                            ) : (
                                <td
                                    colSpan={4}
                                    className="border px-3 py-2 text-muted-foreground"
                                >
                                    {subject.state === 'awaiting'
                                        ? 'Result awaited'
                                        : 'Not entered'}
                                </td>
                            )}
                        </tr>
                    ))}
                    {result.total_marks !== null &&
                        result.percent_tenths !== null && (
                            <tr className="font-semibold">
                                <td className="border px-3 py-2">Overall</td>
                                <td className="border px-3 py-2 tabular-nums">
                                    {result.total_marks} / {result.total_max}
                                </td>
                                <td className="border px-3 py-2 tabular-nums">
                                    {formatTenths(result.percent_tenths)}%
                                </td>
                                <td className="border px-3 py-2">
                                    {result.grade}
                                </td>
                                <td className="border px-3 py-2" />
                            </tr>
                        )}
                </tbody>
            </table>

            <p className="mt-4 text-sm">
                <strong>Class teacher’s remarks:</strong>{' '}
                {card.remark !== null && card.remark !== undefined ? (
                    card.remark
                ) : (
                    <span className="inline-block w-2/3 border-b border-dotted align-bottom">
                        &nbsp;
                    </span>
                )}
            </p>

            <div className="mt-14 grid grid-cols-3 gap-6 text-center text-sm">
                {['Class teacher', 'Coordinator', 'Principal'].map((role) => (
                    <div key={role} className="border-t pt-2">
                        {role}
                    </div>
                ))}
            </div>

            <p className="mt-6 text-xs text-muted-foreground">
                Grades use the school’s grading scale. Only marks published by
                the school are included
                {result.pending_count > 0 &&
                    `; ${result.pending_count} ${result.pending_count === 1 ? 'subject is' : 'subjects are'} still awaited`}
                .
            </p>
        </article>
    );
}
