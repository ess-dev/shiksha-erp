import { Head, Link, router } from '@inertiajs/react';
import {
    ClipboardList,
    Eraser,
    ListChecks,
    Save,
    Settings,
    Sun,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import ReasonDialog from '@/components/attendance/reason-dialog';
import RegisterStrip from '@/components/attendance/register-strip';
import StatusToggle from '@/components/attendance/status-toggle';
import SummaryDialog from '@/components/attendance/summary-dialog';
import type { RegisterSummary } from '@/components/attendance/summary-dialog';
import Field, { selectClassName } from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index, settings, store } from '@/routes/attendance';
import type { AttendanceStatus, Register, SectionChoice } from '@/types';

type Draft = Record<string, AttendanceStatus>;

const RELATION_SUFFIX: Record<string, string> = {
    yours: ' · your class',
    cover: ' · covering',
    view: ' · view only',
    correct: '',
};

function Chip({
    dot,
    label,
    value,
}: {
    dot: string;
    label: string;
    value: number;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm">
            <span className={cn('size-2 rounded-full', dot)} aria-hidden />
            {label} <strong>{value}</strong>
        </span>
    );
}

function EmptyState({
    icon,
    title,
    body,
}: {
    icon: React.ReactNode;
    title: string;
    body: string;
}) {
    return (
        <div className="flex flex-col items-center gap-1 px-4 py-12 text-center">
            <span className="mb-1 text-muted-foreground">{icon}</span>
            <p className="font-medium">{title}</p>
            <p className="text-sm text-muted-foreground">{body}</p>
        </div>
    );
}

function RegisterView({
    register,
    onSaved,
}: {
    register: Register;
    onSaved: (summary: RegisterSummary) => void;
}) {
    const initial: Draft = {};

    register.students.forEach((student) => {
        if (student.status !== null) {
            initial[student.ulid] = student.status;
        }
    });

    const [draft, setDraft] = useState<Draft>(initial);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [asking, setAsking] = useState(false);
    const [confirmAll, setConfirmAll] = useState(false);
    const [leaving, setLeaving] = useState<{
        section?: string;
        date?: string;
    } | null>(null);

    const section: SectionChoice | undefined = register.sections.find(
        (choice) => choice.ulid === register.section,
    );
    const students = register.students;
    const total = students.length;
    const counts = { present: 0, absent: 0, late: 0, unmarked: 0 };

    students.forEach((student) => {
        const status = draft[student.ulid];

        if (status === undefined) {
            counts.unmarked += 1;
        } else {
            counts[status] += 1;
        }
    });

    const dirty = students.some(
        (student) => (student.status ?? null) !== (draft[student.ulid] ?? null),
    );
    const marked = total - counts.unmarked;
    const percent =
        marked === 0 ? 0 : ((counts.present + counts.late) / marked) * 100;
    const showRoster = register.state !== 'closed' && students.length > 0;

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const go = (next: { section?: string; date?: string }) =>
        router.get(
            index.url(),
            {
                section: next.section ?? register.section ?? undefined,
                date: next.date ?? register.date,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['register'],
            },
        );

    const navigate = (next: { section?: string; date?: string }) => {
        if (dirty) {
            setLeaving(next);

            return;
        }

        go(next);
    };

    const setMark = (ulid: string, status: AttendanceStatus) => {
        setDraft((current) => ({ ...current, [ulid]: status }));
        setError(undefined);
    };

    const applyAllPresent = () => {
        setDraft(
            Object.fromEntries(
                students.map((student) => [
                    student.ulid,
                    (student.leave_reason === null
                        ? 'present'
                        : 'absent') as AttendanceStatus,
                ]),
            ),
        );
        setConfirmAll(false);
        setError(undefined);

        const onLeave = students.filter(
            (student) => student.leave_reason !== null,
        ).length;

        toast.success(
            onLeave > 0
                ? `Everyone marked present except ${onLeave} on approved leave.`
                : 'Everyone marked present. Now change any exceptions.',
        );
    };

    const commit = (reason: string | null) => {
        const summary: RegisterSummary = {
            sectionLabel: section?.label ?? '',
            date: register.date,
            isCorrection: register.saved !== null,
            present: counts.present,
            absent: counts.absent,
            late: counts.late,
            absentees: students
                .filter((student) => draft[student.ulid] === 'absent')
                .map((student) => ({
                    ulid: student.ulid,
                    name: student.name,
                    roll: student.roll_number,
                })),
        };

        router.post(
            store.url(),
            {
                section: register.section,
                date: register.date,
                marks: draft,
                reason,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setAsking(false);
                    onSaved(summary);
                },
                onError: (errors) => {
                    setAsking(false);
                    setError(
                        errors.register ??
                            errors.reason ??
                            errors.marks ??
                            Object.values(errors)[0],
                    );
                },
            },
        );
    };

    const save = () => {
        if (counts.unmarked > 0) {
            setError(
                `Mark ${counts.unmarked} more ${counts.unmarked === 1 ? 'student' : 'students'} before saving.`,
            );
            const first = students.find(
                (student) => draft[student.ulid] === undefined,
            );

            document
                .getElementById(`m-${first?.ulid}-present`)
                ?.closest('li')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });

            return;
        }

        if (register.saved !== null && !dirty) {
            toast.info('No changes to save.');

            return;
        }

        if (register.needs_reason) {
            setAsking(true);

            return;
        }

        commit(null);
    };

    return (
        <>
            <Card className="gap-0 overflow-hidden p-0">
                <div className="flex flex-wrap items-end justify-between gap-4 p-4">
                    <div className="flex flex-wrap gap-3">
                        <Field id="attendance-class" label="Class">
                            <select
                                id="attendance-class"
                                value={register.section ?? ''}
                                onChange={(event) =>
                                    navigate({ section: event.target.value })
                                }
                                className={cn(selectClassName, 'min-w-48')}
                            >
                                {register.sections.map((choice) => (
                                    <option
                                        key={choice.ulid}
                                        value={choice.ulid}
                                    >
                                        {choice.label}
                                        {RELATION_SUFFIX[choice.relation] ?? ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field id="attendance-date" label="Date">
                            <Input
                                id="attendance-date"
                                type="date"
                                value={register.date}
                                min={register.min_date}
                                max={register.today}
                                onChange={(event) => {
                                    const value = event.target.value;

                                    if (
                                        value === '' ||
                                        value > register.today ||
                                        value < register.min_date
                                    ) {
                                        toast.warning(
                                            'Pick a date from the start of the academic year up to today.',
                                        );

                                        return;
                                    }

                                    navigate({ date: value });
                                }}
                            />
                        </Field>
                    </div>
                    {showRoster && (
                        <div className="flex flex-wrap gap-2">
                            <Chip
                                dot="bg-green-600"
                                label="Present"
                                value={counts.present}
                            />
                            <Chip
                                dot="bg-destructive"
                                label="Absent"
                                value={counts.absent}
                            />
                            <Chip
                                dot="bg-amber-500"
                                label="Late"
                                value={counts.late}
                            />
                            {counts.unmarked > 0 && (
                                <Chip
                                    dot="bg-muted-foreground/40"
                                    label="Unmarked"
                                    value={counts.unmarked}
                                />
                            )}
                            <Chip dot="hidden" label="Total" value={total} />
                        </div>
                    )}
                </div>

                <RegisterStrip
                    register={register}
                    sectionLabel={section?.label ?? 'this class'}
                />

                {register.state === 'closed' ? (
                    <EmptyState
                        icon={<Sun className="size-6" aria-hidden />}
                        title="No school on this day"
                        body="Pick another date to see its register."
                    />
                ) : !showRoster ? (
                    <EmptyState
                        icon={<ClipboardList className="size-6" aria-hidden />}
                        title={
                            register.saved === null && !register.can_edit
                                ? 'Not marked yet'
                                : 'No students on this register'
                        }
                        body={
                            register.saved === null && !register.can_edit
                                ? 'The class teacher hasn’t saved this register.'
                                : 'Nobody is enrolled in this class on this day.'
                        }
                    />
                ) : (
                    <>
                        {register.can_edit && (
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                                <span className="text-sm text-muted-foreground">
                                    Tap a status for each student, or start with
                                    everyone present.
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setDraft({})}
                                    >
                                        <Eraser aria-hidden /> Clear
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            counts.absent + counts.late > 0
                                                ? setConfirmAll(true)
                                                : applyAllPresent()
                                        }
                                    >
                                        <ListChecks aria-hidden /> Mark all
                                        present
                                    </Button>
                                </div>
                            </div>
                        )}
                        <ul className="divide-y">
                            {students.map((student) => {
                                const status = draft[student.ulid] ?? null;

                                return (
                                    <li
                                        key={student.ulid}
                                        className={cn(
                                            'flex flex-wrap items-center gap-3 px-4 py-3',
                                            status === 'absent' &&
                                                'bg-destructive/5',
                                            status === 'late' &&
                                                'bg-amber-50 dark:bg-amber-950/20',
                                        )}
                                    >
                                        <span
                                            className="w-6 text-sm text-muted-foreground"
                                            aria-label="Roll number"
                                        >
                                            {student.roll_number ?? '—'}
                                        </span>
                                        <InitialsBadge name={student.name} />
                                        <div className="min-w-0 grow">
                                            <div className="truncate font-medium">
                                                {student.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {student.admission_number}
                                                {student.leave_reason !==
                                                    null && (
                                                    <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                                        On approved leave ·{' '}
                                                        {student.leave_reason} ·
                                                        no alert
                                                    </span>
                                                )}
                                                {student.recent_absences >=
                                                    2 && (
                                                    <span className="ml-2 font-medium text-destructive">
                                                        Absent{' '}
                                                        {
                                                            student.recent_absences
                                                        }{' '}
                                                        of last 10 days
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <StatusToggle
                                            studentUlid={student.ulid}
                                            studentName={student.name}
                                            value={status}
                                            disabled={!register.can_edit}
                                            onChange={(next) =>
                                                setMark(student.ulid, next)
                                            }
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                        {register.can_edit && (
                            <div className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t bg-background px-4 py-3">
                                <div className="text-sm">
                                    <span className="font-medium">
                                        {counts.unmarked > 0
                                            ? `${marked} of ${total} marked`
                                            : `All ${total} marked · ${percent.toFixed(1)}% attendance`}
                                    </span>
                                    {error !== undefined && (
                                        <span
                                            role="alert"
                                            className="ml-3 text-destructive"
                                        >
                                            {error}
                                        </span>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    onClick={save}
                                    disabled={processing}
                                >
                                    <Save aria-hidden />{' '}
                                    {register.saved === null
                                        ? 'Save attendance'
                                        : 'Save changes'}
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </Card>

            {asking && (
                <ReasonDialog
                    isCorrection={register.saved !== null}
                    processing={processing}
                    error={error}
                    onCancel={() => setAsking(false)}
                    onSave={commit}
                />
            )}
            {confirmAll && (
                <ConfirmDialog
                    title="Mark everyone present?"
                    body={`This replaces ${counts.absent + counts.late} absent or late ${counts.absent + counts.late === 1 ? 'mark' : 'marks'} already on the register.`}
                    confirmLabel="Mark all present"
                    onCancel={() => setConfirmAll(false)}
                    onConfirm={applyAllPresent}
                />
            )}
            {leaving !== null && (
                <ConfirmDialog
                    title="Discard unsaved marks?"
                    body="You have changes on this register that aren’t saved yet."
                    confirmLabel="Discard changes"
                    destructive
                    onCancel={() => setLeaving(null)}
                    onConfirm={() => {
                        const next = leaving;

                        setLeaving(null);
                        go(next);
                    }}
                />
            )}
        </>
    );
}

export default function AttendanceIndex({ register }: { register: Register }) {
    const [summary, setSummary] = useState<RegisterSummary | null>(null);

    // A new class, day or saved version starts the page's marks again from the server's copy.
    const resetKey = `${register.section}|${register.date}|${register.saved?.saved_at ?? ''}`;

    return (
        <>
            <Head title="Attendance" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Daily attendance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Mark the register once each morning. Changes to an
                            earlier day are saved with a reason.
                        </p>
                    </div>
                    {register.can_manage_settings && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={settings()}>
                                <Settings aria-hidden /> Attendance settings
                            </Link>
                        </Button>
                    )}
                </div>

                {register.section === null ? (
                    <Card>
                        <EmptyState
                            icon={
                                <ClipboardList className="size-6" aria-hidden />
                            }
                            title="No classes to show"
                            body="Ask the school to make you a class teacher or to give you attendance access."
                        />
                    </Card>
                ) : (
                    <RegisterView
                        key={resetKey}
                        register={register}
                        onSaved={setSummary}
                    />
                )}
            </div>

            {summary !== null && (
                <SummaryDialog
                    summary={summary}
                    onClose={() => setSummary(null)}
                />
            )}
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [
        {
            title: 'Attendance',
            href: index(),
        },
    ],
};
