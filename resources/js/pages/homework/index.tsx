import { Head, router } from '@inertiajs/react';
import { BookOpen, Check, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import { dueLabel } from '@/components/homework/due-label';
import HomeworkDialog from '@/components/homework/homework-dialog';
import { HomeworkFileLinks } from '@/components/homework/homework-files';
import InputError from '@/components/input-error';
import { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatCalendarDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    checked as markChecked,
    destroy,
    feedback as saveFeedback,
    index,
} from '@/routes/homework';
import type {
    HomeworkDetail,
    HomeworkScreen,
    HomeworkStudentRow,
    StudentHomeworkStatus,
} from '@/types';

type Dialog =
    | { kind: 'form'; editing: HomeworkDetail | null }
    | { kind: 'delete'; assignment: HomeworkDetail }
    | null;

const STATUS: Record<
    StudentHomeworkStatus,
    { label: string; className: string }
> = {
    submitted: {
        label: 'Submitted',
        className:
            'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
    },
    late: {
        label: 'Late',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    },
    missing: {
        label: 'Missing',
        className:
            'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    },
    not_yet: {
        label: 'Not yet',
        className: 'bg-muted text-muted-foreground',
    },
};

function StatusBadge({ status }: { status: StudentHomeworkStatus }) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                STATUS[status].className,
            )}
        >
            {STATUS[status].label}
        </span>
    );
}

function shortDay(value: string): string {
    return formatCalendarDay(value).replace(/^\w+, /, '');
}

export default function Homework({ screen }: { screen: HomeworkScreen }) {
    const [dialog, setDialog] = useState<Dialog>(null);
    const selected = screen.selected;

    const go = (next: { section?: string | null; assignment?: string }) =>
        router.get(
            index.url(),
            {
                section:
                    next.section === undefined
                        ? (screen.section_ulid ?? undefined)
                        : (next.section ?? undefined),
                assignment: next.assignment,
            },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Homework" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Homework
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {screen.can_assign
                                ? 'Set assignments, see who has submitted, and leave feedback parents can read.'
                                : 'Assignments teachers set, and who has submitted.'}
                        </p>
                    </div>
                    {screen.can_assign && screen.form !== null && (
                        <Button
                            type="button"
                            onClick={() =>
                                setDialog({ kind: 'form', editing: null })
                            }
                        >
                            <Plus aria-hidden /> New assignment
                        </Button>
                    )}
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                    <Card className="gap-0 p-0">
                        <div className="border-b p-4">
                            <h2 className="font-semibold">Your assignments</h2>
                            <p className="text-sm text-muted-foreground">
                                {screen.assignments.length === 1
                                    ? '1 assignment'
                                    : `${screen.assignments.length} assignments`}
                            </p>
                            {screen.sections.length > 1 && (
                                <select
                                    aria-label="Class"
                                    className={cn(selectClassName, 'mt-3')}
                                    value={screen.section_ulid ?? ''}
                                    onChange={(event) =>
                                        go({
                                            section:
                                                event.target.value === ''
                                                    ? null
                                                    : event.target.value,
                                        })
                                    }
                                >
                                    <option value="">All classes</option>
                                    {screen.sections.map((s) => (
                                        <option key={s.ulid} value={s.ulid}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {screen.assignments.length === 0 ? (
                            <div className="flex flex-col items-center gap-1 px-4 py-12 text-center">
                                <BookOpen
                                    className="mb-1 size-6 text-muted-foreground"
                                    aria-hidden
                                />
                                <p className="font-medium">
                                    No assignments yet
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {screen.can_assign
                                        ? 'Create one to share with students and parents.'
                                        : 'Nothing has been set for your classes.'}
                                </p>
                            </div>
                        ) : (
                            <ul>
                                {screen.assignments.map((item) => (
                                    <li
                                        key={item.ulid}
                                        className="border-b last:border-b-0"
                                    >
                                        <button
                                            type="button"
                                            aria-current={
                                                item.ulid ===
                                                screen.selected_ulid
                                                    ? 'true'
                                                    : undefined
                                            }
                                            onClick={() =>
                                                go({ assignment: item.ulid })
                                            }
                                            className={cn(
                                                'flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-muted/50',
                                                item.ulid ===
                                                    screen.selected_ulid &&
                                                    'bg-primary/5',
                                            )}
                                        >
                                            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <BookOpen
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <strong className="block truncate">
                                                    {item.title}
                                                </strong>
                                                <span className="block text-sm text-muted-foreground">
                                                    {item.section_label} ·{' '}
                                                    {item.subject} ·{' '}
                                                    {dueLabel(
                                                        item.days_left,
                                                        item.due_on,
                                                    )}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {item.submitted}/
                                                    {item.total} submitted
                                                    {item.to_check > 0 &&
                                                        ` · ${item.to_check} to check`}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {selected === null ? (
                        <Card className="items-center gap-1 px-4 py-12 text-center">
                            <BookOpen
                                className="mb-1 size-6 text-muted-foreground"
                                aria-hidden
                            />
                            <p className="font-medium">Pick an assignment</p>
                        </Card>
                    ) : (
                        <AssignmentDetail
                            key={selected.ulid}
                            detail={selected}
                            maxFeedback={screen.form?.feedback_max ?? 160}
                            onEdit={() =>
                                setDialog({ kind: 'form', editing: selected })
                            }
                            onDelete={() =>
                                setDialog({
                                    kind: 'delete',
                                    assignment: selected,
                                })
                            }
                        />
                    )}
                </div>
            </div>

            {dialog?.kind === 'form' && screen.form !== null && (
                <HomeworkDialog
                    options={screen.form}
                    editing={dialog.editing}
                    initialSection={screen.section_ulid}
                    onClose={() => setDialog(null)}
                />
            )}

            {dialog?.kind === 'delete' && (
                <ConfirmDialog
                    title="Delete this assignment?"
                    body={`“${dialog.assignment.title}” disappears for parents and for you. What students already handed in is kept in the audit trail.`}
                    confirmLabel="Delete assignment"
                    destructive
                    onCancel={() => setDialog(null)}
                    onConfirm={() => {
                        router.delete(destroy.url(dialog.assignment.ulid), {
                            preserveScroll: true,
                            onSuccess: () => setDialog(null),
                        });
                    }}
                />
            )}
        </>
    );
}

function AssignmentDetail({
    detail,
    maxFeedback,
    onEdit,
    onDelete,
}: {
    detail: HomeworkDetail;
    maxFeedback: number;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const [typed, setTyped] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const isNotebook = detail.submission_type === 'notebook';

    const current = (row: HomeworkStudentRow) =>
        typed[row.student_ulid] ?? row.feedback ?? '';
    const changed = Object.fromEntries(
        detail.rows
            .filter(
                (row) =>
                    row.student_ulid in typed &&
                    (typed[row.student_ulid] ?? '').trim() !==
                        (row.feedback ?? ''),
            )
            .map((row) => [row.student_ulid, typed[row.student_ulid] ?? '']),
    );

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => setError(undefined),
        onError: (errors: Record<string, string>) =>
            setError(errors.homework ?? Object.values(errors)[0]),
    };

    const check = (students: string[] | null, checked: boolean) =>
        router.post(
            markChecked.url(detail.ulid),
            students === null ? { checked } : { checked, students },
            options,
        );

    const save = () =>
        router.put(
            saveFeedback.url(detail.ulid),
            { feedback: changed },
            {
                ...options,
                onSuccess: () => {
                    setError(undefined);
                    setTyped({});
                },
            },
        );

    return (
        <Card className="gap-0 p-0">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
                <div className="min-w-0">
                    <h2 className="font-semibold">{detail.title}</h2>
                    <p className="text-sm text-muted-foreground">
                        {detail.section_label} · {detail.subject} · set{' '}
                        {shortDay(detail.assigned_on)} by {detail.assigned_by}
                    </p>
                </div>
                <span
                    className={cn(
                        'rounded-full px-3 py-1 text-xs font-semibold',
                        detail.days_left < 0
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-primary/10 text-primary',
                    )}
                >
                    Due {formatCalendarDay(detail.due_on)}
                </span>
            </div>

            <div className="grid gap-3 border-b p-4">
                {detail.instructions !== null && (
                    <p className="text-sm text-muted-foreground">
                        {detail.instructions}
                    </p>
                )}
                <HomeworkFileLinks files={detail.attachments} />
                <div className="flex flex-wrap gap-2 text-sm">
                    <span className="rounded-full border px-3 py-1">
                        Submitted{' '}
                        <strong>
                            {detail.submitted}/{detail.total}
                        </strong>
                    </span>
                    <span className="rounded-full border px-3 py-1">
                        {isNotebook ? 'In notebook' : 'Upload in app'}
                    </span>
                    {detail.to_check > 0 && (
                        <span className="rounded-full border px-3 py-1">
                            To check <strong>{detail.to_check}</strong>
                        </span>
                    )}
                </div>
                {detail.can_manage && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onEdit}
                        >
                            <Pencil aria-hidden /> Edit
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onDelete}
                        >
                            <Trash2 aria-hidden /> Delete
                        </Button>
                    </div>
                )}
            </div>

            {detail.rows.length === 0 ? (
                <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                    No students are enrolled in this class yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="w-14 px-4 py-2.5">
                                    Roll
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Student
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2.5">
                                    Feedback
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {detail.rows.map((row) => {
                                const handedIn =
                                    row.status === 'submitted' ||
                                    row.status === 'late';

                                return (
                                    <tr
                                        key={row.student_ulid}
                                        className="border-t align-top"
                                    >
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {row.roll_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            <strong>{row.name}</strong>
                                            {row.submitted_on !== null && (
                                                <div className="text-xs text-muted-foreground">
                                                    {shortDay(row.submitted_on)}
                                                </div>
                                            )}
                                            {row.files.length > 0 && (
                                                <div className="mt-1.5">
                                                    <HomeworkFileLinks
                                                        files={row.files}
                                                    />
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col items-start gap-1.5">
                                                <StatusBadge
                                                    status={row.status}
                                                />
                                                {row.is_checked && (
                                                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                        <Check
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                        Checked
                                                    </span>
                                                )}
                                                {detail.can_manage &&
                                                    (isNotebook ||
                                                        row.files.length >
                                                            0) && (
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                processing
                                                            }
                                                            className="text-xs text-primary hover:underline disabled:opacity-50"
                                                            onClick={() =>
                                                                check(
                                                                    [
                                                                        row.student_ulid,
                                                                    ],
                                                                    !row.is_checked,
                                                                )
                                                            }
                                                        >
                                                            {row.is_checked
                                                                ? 'Undo checked'
                                                                : 'Mark checked'}
                                                        </button>
                                                    )}
                                            </div>
                                        </td>
                                        <td className="min-w-56 px-4 py-3">
                                            {detail.can_manage && handedIn ? (
                                                <>
                                                    <label
                                                        className="sr-only"
                                                        htmlFor={`fb-${row.student_ulid}`}
                                                    >
                                                        Feedback for {row.name}
                                                    </label>
                                                    <Input
                                                        id={`fb-${row.student_ulid}`}
                                                        value={current(row)}
                                                        maxLength={maxFeedback}
                                                        placeholder="Feedback (optional)"
                                                        onChange={(event) =>
                                                            setTyped({
                                                                ...typed,
                                                                [row.student_ulid]:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {row.feedback}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {detail.can_manage ? (
                <div className="flex flex-wrap items-center gap-3 border-t p-4">
                    <span className="grow text-sm text-muted-foreground">
                        {isNotebook
                            ? 'For notebook work, mark students as checked from class.'
                            : 'Feedback appears in the parent app.'}
                    </span>
                    <InputError message={error} />
                    {isNotebook && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => check(null, true)}
                        >
                            Mark all checked in class
                        </Button>
                    )}
                    <Button
                        type="button"
                        disabled={
                            processing || Object.keys(changed).length === 0
                        }
                        onClick={save}
                    >
                        Save feedback
                    </Button>
                </div>
            ) : (
                <div className="flex items-center gap-2 border-t p-4 text-sm text-muted-foreground">
                    <Eye className="size-4 shrink-0" aria-hidden />
                    Set by {detail.assigned_by}. You can view it here.
                </div>
            )}
        </Card>
    );
}
