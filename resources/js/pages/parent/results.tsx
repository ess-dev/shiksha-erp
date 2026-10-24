import { Head, Link } from '@inertiajs/react';
import { FileBadge, FileText, Hourglass } from 'lucide-react';
import { useState } from 'react';
import GradePill from '@/components/exams/grade-pill';
import { formatTenths } from '@/components/exams/grading';
import { Button } from '@/components/ui/button';
import { formatCalendarDate, initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import { reportCard } from '@/routes/parent/results';
import type {
    ParentShell,
    SchoolReportCard,
    StudentExamResult,
    StudentResults,
    StudentSubjectResult,
} from '@/types/parent';

type Props = {
    shell: ParentShell;
    results: StudentResults | null;
    selected_exam: string | null;
    child_name: string | null;
    child_first_name: string | null;
    admission_number: string | null;
    section_label: string | null;
    session_name: string;
    is_current_session: boolean;
    school_report_cards: SchoolReportCard[];
    report_card_waiting: boolean;
};

/** "Periodic Test 1" → "PT 1", "Half-Yearly Examination" → "Half-Yearly", for the chart. */
function shortName(name: string): string {
    return name.replace(/ Examination$/, '').replace(/^Periodic Test/, 'PT');
}

export default function ParentResultsPage({
    shell,
    results,
    selected_exam: selectedExam,
    child_name: childName,
    child_first_name: firstName,
    admission_number: admissionNumber,
    section_label: section,
    session_name: sessionName,
    is_current_session: isCurrent,
    school_report_cards: schoolCards,
    report_card_waiting: reportCardWaiting,
}: Props) {
    const exams = results?.exams ?? [];
    const [chosen, setChosen] = useState<string | null>(
        exams.some((exam) => exam.ulid === selectedExam)
            ? selectedExam
            : (exams[exams.length - 1]?.ulid ?? null),
    );
    const exam = exams.find((item) => item.ulid === chosen) ?? null;

    if (results === null || childName === null) {
        return (
            <>
                <Head title="Results" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No results for {sessionName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Results are shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Results" />

            <section
                aria-labelledby="uploaded-title"
                className="mb-4 rounded-xl border bg-card p-4"
            >
                <div className="flex items-center justify-between gap-2">
                    <h2 id="uploaded-title" className="font-semibold">
                        Report cards from school
                    </h2>
                    <span className="text-sm text-muted-foreground">
                        {sessionName}
                    </span>
                </div>
                {schoolCards.length === 0 ? (
                    <p className="mt-2 flex items-start gap-2 text-sm text-muted-foreground">
                        <FileBadge
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        No report cards yet. The class teacher sends them after
                        each exam; until then, open the report card for any exam
                        below.
                    </p>
                ) : (
                    <ul className="mt-2 divide-y">
                        {schoolCards.map((card) => (
                            <li
                                key={card.ulid}
                                className="flex items-center gap-3 py-3"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <FileBadge className="size-5" aria-hidden />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold">
                                        {card.exam_name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {section} ·{' '}
                                        {card.kind === 'file'
                                            ? 'uploaded'
                                            : 'prepared'}{' '}
                                        by {card.prepared_by} on{' '}
                                        {formatCalendarDate(card.prepared_on)}
                                        {card.kind === 'file' &&
                                            ' · scanned copy'}
                                    </p>
                                </div>
                                <Button asChild size="sm">
                                    {card.kind === 'file' ? (
                                        <a
                                            href={card.view_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            View
                                            <span className="sr-only">
                                                {' '}
                                                {card.exam_name} report card
                                            </span>
                                        </a>
                                    ) : (
                                        <Link href={card.view_url}>
                                            View
                                            <span className="sr-only">
                                                {' '}
                                                {card.exam_name} report card
                                            </span>
                                        </Link>
                                    )}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
                {reportCardWaiting && (
                    <p className="mt-3 flex items-start gap-2 rounded-lg bg-muted/60 px-3 py-2 text-sm">
                        <Hourglass
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        The class teacher has sent a new report card for
                        approval. It appears here once it is approved.
                    </p>
                )}
            </section>

            {exams.length === 0 ? (
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">No results yet</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {isCurrent
                            ? `Results for ${firstName} appear here once teachers publish marks.`
                            : `No results were published for ${firstName} in ${sessionName}.`}
                    </p>
                </div>
            ) : (
                <>
                    <div
                        role="group"
                        aria-label="Choose an exam"
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        {exams.map((item) => (
                            <button
                                key={item.ulid}
                                type="button"
                                aria-pressed={item.ulid === chosen}
                                onClick={() => setChosen(item.ulid)}
                                className={cn(
                                    'rounded-full border bg-card px-4 py-1.5 text-sm font-medium',
                                    item.ulid === chosen &&
                                        'border-primary bg-primary text-primary-foreground',
                                )}
                            >
                                {item.name}
                            </button>
                        ))}
                    </div>

                    <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        {exam !== null && (
                            <ExamCard
                                exam={exam}
                                childName={childName}
                                admissionNumber={admissionNumber}
                                section={section}
                                sessionName={sessionName}
                                childUlid={shell.selected_child}
                            />
                        )}

                        <div className="grid gap-4">
                            <ProgressChart exams={exams} chosen={chosen} />
                            <section
                                aria-labelledby="grades-title"
                                className="rounded-xl border bg-card p-4"
                            >
                                <h2
                                    id="grades-title"
                                    className="mb-3 font-semibold"
                                >
                                    How grades work
                                </h2>
                                <div className="flex flex-wrap gap-2">
                                    {results.scale.map((band) => (
                                        <span
                                            key={band.grade}
                                            className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
                                        >
                                            <GradePill
                                                grade={band.grade}
                                                low={band.is_lowest}
                                            />
                                            {band.min_percent}–
                                            {band.max_percent}%
                                        </span>
                                    ))}
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Subjects show “Awaiting results” until the
                                    teacher publishes them.
                                </p>
                            </section>
                        </div>
                    </div>
                </>
            )}
        </>
    );
}

function ExamCard({
    exam,
    childName,
    admissionNumber,
    section,
    sessionName,
    childUlid,
}: {
    exam: StudentExamResult;
    childName: string;
    admissionNumber: string | null;
    section: string | null;
    sessionName: string;
    childUlid: string | null;
}) {
    return (
        <section
            aria-labelledby="exam-title"
            className="rounded-xl border bg-card p-4"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 id="exam-title" className="font-semibold">
                        {exam.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {sessionName} · {section} · held{' '}
                        {formatCalendarDate(exam.held_on)}
                    </p>
                </div>
                {exam.pending_count > 0 ? (
                    <span className="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
                        {exam.pending_count} awaiting
                    </span>
                ) : (
                    <span className="shrink-0 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-950/50 dark:text-green-200">
                        Final
                    </span>
                )}
            </div>

            <div className="my-4 flex items-center gap-3">
                <span
                    aria-hidden
                    className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                >
                    {initials(childName)}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold">{childName}</p>
                    {admissionNumber !== null && (
                        <p className="text-sm text-muted-foreground">
                            {admissionNumber}
                        </p>
                    )}
                </div>
                {exam.percent_tenths !== null && exam.grade !== null && (
                    <div className="text-right">
                        <p className="text-2xl font-bold tabular-nums">
                            {formatTenths(exam.percent_tenths)}%
                        </p>
                        <GradePill
                            grade={exam.grade}
                            low={exam.is_lowest_grade}
                        />
                    </div>
                )}
            </div>

            <table className="w-full text-sm">
                <thead className="border-b text-left text-muted-foreground">
                    <tr>
                        <th scope="col" className="py-2 font-medium">
                            Subject
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Marks
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            %
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Grade
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {exam.subjects.map((subject) => (
                        <SubjectRow
                            key={subject.subject_name}
                            subject={subject}
                            maxMarks={exam.max_marks}
                        />
                    ))}
                </tbody>
                {exam.total_marks !== null && exam.percent_tenths !== null && (
                    <tfoot className="border-t font-semibold">
                        <tr>
                            <td className="py-2.5">
                                Total
                                {exam.pending_count > 0 && ' (published)'}
                            </td>
                            <td className="py-2.5 text-right tabular-nums">
                                {exam.total_marks} / {exam.total_max}
                            </td>
                            <td className="py-2.5 text-right tabular-nums">
                                {formatTenths(exam.percent_tenths)}%
                            </td>
                            <td className="py-2.5 text-right">{exam.grade}</td>
                        </tr>
                    </tfoot>
                )}
            </table>

            {exam.total_marks !== null && (
                <Button asChild variant="outline" className="mt-4 w-full">
                    <Link
                        href={reportCard.url(exam.ulid, {
                            query:
                                childUlid === null ? {} : { child: childUlid },
                        })}
                    >
                        <FileText aria-hidden /> Open as report card
                    </Link>
                </Button>
            )}
        </section>
    );
}

function SubjectRow({
    subject,
    maxMarks,
}: {
    subject: StudentSubjectResult;
    maxMarks: number;
}) {
    if (subject.state === 'awaiting' || subject.state === 'not_entered') {
        return (
            <tr>
                <td className="py-2.5">{subject.subject_name}</td>
                <td colSpan={3} className="py-2.5 text-right">
                    <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                        {subject.state === 'awaiting'
                            ? 'Awaiting results'
                            : 'Not entered'}
                    </span>
                </td>
            </tr>
        );
    }

    if (subject.state === 'absent') {
        return (
            <tr>
                <td className="py-2.5">{subject.subject_name}</td>
                <td className="py-2.5 text-right">AB</td>
                <td className="py-2.5 text-right">—</td>
                <td className="py-2.5 text-right">
                    <GradePill grade="AB" low />
                </td>
            </tr>
        );
    }

    return (
        <tr>
            <td className="py-2.5">{subject.subject_name}</td>
            <td className="py-2.5 text-right tabular-nums">
                {subject.marks} / {maxMarks}
            </td>
            <td className="py-2.5 text-right tabular-nums">
                {subject.percent}%
            </td>
            <td className="py-2.5 text-right">
                {subject.grade !== null && (
                    <GradePill
                        grade={subject.grade}
                        low={subject.is_lowest_grade}
                    />
                )}
            </td>
        </tr>
    );
}

/** "Progress across exams": one bar per exam, the chosen one highlighted. */
function ProgressChart({
    exams,
    chosen,
}: {
    exams: StudentExamResult[];
    chosen: string | null;
}) {
    const ticks = [100, 80, 60, 40, 20, 0];

    return (
        <section
            aria-labelledby="progress-title"
            className="rounded-xl border bg-card p-4"
        >
            <h2 id="progress-title" className="mb-3 font-semibold">
                Progress across exams
            </h2>
            <div
                role="img"
                aria-label={exams
                    .map(
                        (exam) =>
                            `${exam.name}: ${exam.percent_tenths === null ? 'no published marks' : `${formatTenths(exam.percent_tenths)}%`}`,
                    )
                    .join('; ')}
                className="flex gap-2"
            >
                <div className="flex h-44 flex-col justify-between text-right text-xs text-muted-foreground tabular-nums">
                    {ticks.map((tick) => (
                        <span key={tick}>{tick}%</span>
                    ))}
                </div>
                <div className="flex-1">
                    <div className="relative flex h-44 items-end gap-3 border-b border-l px-2">
                        {exams.map((exam) => (
                            <div
                                key={exam.ulid}
                                className="flex h-full flex-1 items-end justify-center"
                            >
                                {exam.percent_tenths !== null && (
                                    <span
                                        className={cn(
                                            'w-full max-w-12 rounded-t-md',
                                            exam.ulid === chosen
                                                ? 'bg-primary'
                                                : 'bg-primary/30',
                                        )}
                                        style={{
                                            height: `${Math.min(100, exam.percent_tenths / 10)}%`,
                                        }}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-3 px-2 pt-2">
                        {exams.map((exam) => (
                            <span
                                key={exam.ulid}
                                className="flex-1 truncate text-center text-xs text-muted-foreground"
                                title={exam.name}
                            >
                                {shortName(exam.name)}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
