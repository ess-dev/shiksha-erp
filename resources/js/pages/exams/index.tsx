import { Head, Link } from '@inertiajs/react';
import { Plus, Scale, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DateSheetDialog from '@/components/exams/date-sheet-dialog';
import type { ExamAction } from '@/components/exams/exam-confirm-dialog';
import ExamConfirmDialog from '@/components/exams/exam-confirm-dialog';
import ExamDialog from '@/components/exams/exam-dialog';
import ExamStatusBadge from '@/components/exams/exam-status-badge';
import { relativeDays } from '@/components/exams/exam-calendar';
import { Button } from '@/components/ui/button';
import { formatCalendarDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useCan } from '@/hooks/use-can';
import { grading, index } from '@/routes/exams';
import type { ExamRow, ExamsOverview } from '@/types';

type Tab = 'open' | 'published' | 'cancelled';

type Dialog =
    | { kind: 'form'; exam: ExamRow | null }
    | { kind: 'confirm'; exam: ExamRow; action: ExamAction }
    | { kind: 'sheet'; exam: ExamRow }
    | null;

function datesLabel(exam: ExamRow): string {
    return exam.ends_on === exam.starts_on
        ? formatCalendarDay(exam.starts_on)
        : `${formatCalendarDay(exam.starts_on)} – ${formatCalendarDay(exam.ends_on)}`;
}

function Counter({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border bg-card px-4 py-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}

export default function Exams({ overview }: { overview: ExamsOverview }) {
    const [tab, setTab] = useState<Tab>('open');
    const [dialog, setDialog] = useState<Dialog>(null);
    const canChangeScale = useCan('settings.manage');

    const groups: Record<Tab, ExamRow[]> = {
        open: overview.exams.filter(
            (exam) => exam.status === 'scheduled' || exam.status === 'marking',
        ),
        published: overview.exams.filter((exam) => exam.status === 'published'),
        cancelled: overview.exams.filter((exam) => exam.status === 'cancelled'),
    };

    const rows = tab === 'open' ? groups.open : [...groups[tab]].reverse();

    const tabs: { value: Tab; label: string }[] = [
        { value: 'open', label: 'Upcoming and open' },
        { value: 'published', label: 'Published' },
        { value: 'cancelled', label: 'Cancelled' },
    ];

    const close = () => setDialog(null);

    return (
        <>
            <Head title="Exams and tests" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Exams and unit tests
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create tests and exams, set the classes and date
                            sheet, open marking and publish results.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canChangeScale && (
                            <Button asChild variant="outline">
                                <Link href={grading()}>
                                    <Scale aria-hidden /> Grading scale
                                </Link>
                            </Button>
                        )}
                        <Button
                            onClick={() =>
                                setDialog({ kind: 'form', exam: null })
                            }
                        >
                            <Plus aria-hidden />
                            New test or exam
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Counter
                        label="Scheduled"
                        value={overview.scheduled_count}
                    />
                    <Counter
                        label="Marking open"
                        value={overview.marking_count}
                    />
                    <Counter
                        label="Results published"
                        value={overview.published_count}
                    />
                    <Counter
                        label="Cancelled"
                        value={overview.cancelled_count}
                    />
                </div>

                <div
                    role="group"
                    aria-label="Choose list"
                    className="flex w-fit max-w-full overflow-x-auto rounded-lg border bg-muted/40 p-1"
                >
                    {tabs.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={tab === item.value}
                            onClick={() => setTab(item.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap text-muted-foreground',
                                tab === item.value &&
                                    'bg-background text-primary shadow-xs',
                            )}
                        >
                            {item.label} · {groups[item.value].length}
                        </button>
                    ))}
                </div>

                <section className="rounded-xl border bg-card">
                    {rows.length === 0 ? (
                        <div className="p-8 text-center">
                            <p className="font-medium">Nothing here</p>
                            <p className="text-sm text-muted-foreground">
                                {tab === 'open'
                                    ? 'Create a unit test or exam to get started.'
                                    : 'Exams move here as you publish or cancel them.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th scope="col" className="px-4 py-2.5">
                                            Exam
                                        </th>
                                        <th scope="col" className="px-4 py-2.5">
                                            Dates
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 md:table-cell"
                                        >
                                            Classes
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 text-right md:table-cell"
                                        >
                                            Max
                                        </th>
                                        <th scope="col" className="px-4 py-2.5">
                                            Status
                                        </th>
                                        <th scope="col" className="px-4 py-2.5">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.map((exam) => (
                                        <tr
                                            key={exam.ulid}
                                            className="align-top"
                                        >
                                            <td className="px-4 py-3">
                                                <p className="font-semibold">
                                                    {exam.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {exam.type_label}
                                                    {exam.created_by !== null &&
                                                        ` · by ${exam.created_by}`}
                                                    {!exam.can_change &&
                                                        ' · set outside your reach'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {datesLabel(exam)}
                                                {exam.status ===
                                                    'scheduled' && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {relativeDays(
                                                            overview.form.today,
                                                            exam.starts_on,
                                                        )}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 text-xs md:table-cell">
                                                {exam.classes_label}
                                            </td>
                                            <td className="hidden px-4 py-3 text-right md:table-cell">
                                                {exam.max_marks}
                                            </td>
                                            <td className="px-4 py-3">
                                                <ExamStatusBadge
                                                    status={exam.status}
                                                />
                                                {exam.marks_progress !==
                                                    null && (
                                                    <p className="mt-1 text-xs whitespace-nowrap text-muted-foreground">
                                                        {
                                                            exam.marks_progress
                                                                .entered
                                                        }
                                                        /
                                                        {
                                                            exam.marks_progress
                                                                .sheets
                                                        }{' '}
                                                        entered ·{' '}
                                                        {
                                                            exam.marks_progress
                                                                .published
                                                        }{' '}
                                                        published
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                    {exam.can_change &&
                                                        (exam.status ===
                                                            'scheduled' ||
                                                            exam.status ===
                                                                'marking') && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'form',
                                                                        exam,
                                                                    })
                                                                }
                                                            >
                                                                Edit
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    {exam.name}
                                                                </span>
                                                            </Button>
                                                        )}
                                                    {exam.can_change &&
                                                        exam.status ===
                                                            'scheduled' && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'confirm',
                                                                        exam,
                                                                        action: 'marking',
                                                                    })
                                                                }
                                                            >
                                                                Open marking
                                                            </Button>
                                                        )}
                                                    {exam.can_publish &&
                                                        exam.status ===
                                                            'marking' && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'confirm',
                                                                        exam,
                                                                        action: 'published',
                                                                    })
                                                                }
                                                            >
                                                                Publish all
                                                            </Button>
                                                        )}
                                                    {exam.can_change &&
                                                        exam.status ===
                                                            'marking' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'confirm',
                                                                        exam,
                                                                        action: 'scheduled',
                                                                    })
                                                                }
                                                            >
                                                                Close marking
                                                            </Button>
                                                        )}
                                                    {exam.can_change &&
                                                        (exam.status ===
                                                            'scheduled' ||
                                                            exam.status ===
                                                                'marking') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'confirm',
                                                                        exam,
                                                                        action: 'cancelled',
                                                                    })
                                                                }
                                                            >
                                                                Cancel
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    {exam.name}
                                                                </span>
                                                            </Button>
                                                        )}
                                                    {exam.can_change &&
                                                        exam.status ===
                                                            'cancelled' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDialog({
                                                                        kind: 'confirm',
                                                                        exam,
                                                                        action: 'scheduled',
                                                                    })
                                                                }
                                                            >
                                                                Restore
                                                            </Button>
                                                        )}
                                                    {exam.can_delete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Delete ${exam.name}`}
                                                            onClick={() =>
                                                                setDialog({
                                                                    kind: 'confirm',
                                                                    exam,
                                                                    action: 'delete',
                                                                })
                                                            }
                                                        >
                                                            <Trash2
                                                                aria-hidden
                                                            />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setDialog({
                                                                kind: 'sheet',
                                                                exam,
                                                            })
                                                        }
                                                    >
                                                        Date sheet
                                                        <span className="sr-only">
                                                            {' '}
                                                            of {exam.name}
                                                        </span>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <p className="text-sm text-muted-foreground">
                    Teachers enter marks once marking is open. “Publish all”
                    shows every subject to parents; subjects can still be
                    published one by one from Marks.
                </p>
            </div>

            {dialog?.kind === 'form' && (
                <ExamDialog
                    key={dialog.exam?.ulid ?? 'new'}
                    exam={dialog.exam}
                    options={overview.form}
                    onClose={close}
                />
            )}
            {dialog?.kind === 'confirm' && (
                <ExamConfirmDialog
                    exam={dialog.exam}
                    action={dialog.action}
                    onClose={close}
                />
            )}
            {dialog?.kind === 'sheet' && (
                <DateSheetDialog exam={dialog.exam} onClose={close} />
            )}
        </>
    );
}

Exams.layout = {
    breadcrumbs: [
        {
            title: 'Exams and tests',
            href: index(),
        },
    ],
};
