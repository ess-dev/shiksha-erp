import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, RotateCcw, Save, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import GradePill from '@/components/exams/grade-pill';
import GradeScaleCard from '@/components/exams/grade-scale-card';
import type { ParsedMark } from '@/components/exams/grading';
import { gradeFor, parseMark, wholePercent } from '@/components/exams/grading';
import MarksStrip from '@/components/exams/marks-strip';
import MarksSummary from '@/components/exams/marks-summary';
import ReopenDialog from '@/components/exams/reopen-dialog';
import Field, { selectClassName } from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index, reopen, update } from '@/routes/marks';
import type { GradeBand, MarksScreen, MarksSheet } from '@/types';

type Draft = Record<string, string>;
type Choice = { exam?: string; section?: string; subject?: string };

function plural(count: number, word: string): string {
    return `${count} ${count === 1 ? word : `${word}s`}`;
}

function SheetView({
    screen,
    sheet,
}: {
    screen: MarksScreen;
    sheet: MarksSheet;
}) {
    const initial: Draft = Object.fromEntries(
        sheet.rows.map((row) => [row.student_ulid, row.value]),
    );
    const [draft, setDraft] = useState<Draft>(initial);
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const [confirmPublish, setConfirmPublish] = useState(false);
    const [reopening, setReopening] = useState(false);
    const [leaving, setLeaving] = useState<Choice | null>(null);
    const inputs = useRef<(HTMLInputElement | null)[]>([]);

    const editable = sheet.state === 'editable';
    const parsed: ParsedMark[] = sheet.rows.map((row) =>
        parseMark(draft[row.student_ulid] ?? '', sheet.max_marks),
    );
    const invalid = parsed.filter((mark) => mark.kind === 'invalid').length;
    const blank = parsed.filter((mark) => mark.kind === 'blank').length;
    const total = sheet.rows.length;
    const dirty = sheet.rows.some(
        (row) => (draft[row.student_ulid] ?? '').trim() !== row.value.trim(),
    );

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

    const go = (next: Choice) =>
        router.get(
            index.url(),
            {
                exam: next.exam ?? sheet.exam_ulid,
                section: next.exam
                    ? undefined
                    : (next.section ?? sheet.section_ulid),
                subject:
                    next.exam || next.section
                        ? undefined
                        : (next.subject ?? sheet.subject_ulid),
            },
            { preserveScroll: true, replace: true },
        );

    const navigate = (next: Choice) => {
        if (dirty) {
            setLeaving(next);

            return;
        }

        go(next);
    };

    const focusFirst = (kind: ParsedMark['kind']) => {
        const position = parsed.findIndex((mark) => mark.kind === kind);

        inputs.current[position]?.focus();
    };

    const send = (publish: boolean) => {
        router.put(
            update.url({
                exam: sheet.exam_ulid,
                section: sheet.section_ulid,
                subject: sheet.subject_ulid,
            }),
            { marks: draft, publish },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setConfirmPublish(false),
                onError: (errors) => {
                    setConfirmPublish(false);
                    setError(errors.marks ?? Object.values(errors)[0]);
                },
            },
        );
    };

    const saveDraft = () => {
        if (invalid > 0) {
            setError(
                `Fix ${plural(invalid, 'mark')} first: use 0–${sheet.max_marks} (halves allowed) or AB.`,
            );
            focusFirst('invalid');

            return;
        }

        send(false);
    };

    const publish = () => {
        if (invalid > 0 || blank > 0) {
            setError(
                invalid > 0
                    ? `Fix ${plural(invalid, 'mark')} before publishing.`
                    : `Enter marks (or AB) for ${plural(blank, 'more student')} before publishing.`,
            );
            focusFirst(invalid > 0 ? 'invalid' : 'blank');

            return;
        }

        setConfirmPublish(true);
    };

    const sendReopen = (reason: string) => {
        router.post(
            reopen.url({
                exam: sheet.exam_ulid,
                section: sheet.section_ulid,
                subject: sheet.subject_ulid,
            }),
            { reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setReopening(false),
                onError: (errors) =>
                    setError(
                        errors.reason ??
                            errors.marks ??
                            Object.values(errors)[0],
                    ),
            },
        );
    };

    const move = (position: number, step: number) => {
        const next = inputs.current[position + step];

        if (next) {
            next.focus();
            next.select();
        }
    };

    const summaryLine =
        invalid > 0
            ? `${plural(invalid, 'mark')} ${invalid === 1 ? 'needs' : 'need'} fixing`
            : blank > 0
              ? `${total - blank} of ${total} entered`
              : `All ${total} entered`;

    return (
        <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <Card className="gap-0 overflow-hidden p-0">
                <div className="grid gap-3 p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <Field id="marks-exam" label="Exam">
                        <select
                            id="marks-exam"
                            className={selectClassName}
                            value={sheet.exam_ulid}
                            onChange={(event) =>
                                navigate({ exam: event.target.value })
                            }
                        >
                            {screen.exams.map((exam) => (
                                <option key={exam.ulid} value={exam.ulid}>
                                    {exam.name} · out of {exam.max_marks}
                                    {exam.status === 'marking'
                                        ? ' (marking open)'
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="marks-class" label="Class">
                        <select
                            id="marks-class"
                            className={selectClassName}
                            value={sheet.section_ulid}
                            onChange={(event) =>
                                navigate({ section: event.target.value })
                            }
                        >
                            {screen.sections.map((section) => (
                                <option key={section.ulid} value={section.ulid}>
                                    {section.label}
                                    {new Set(
                                        screen.sections.map(
                                            (s) => s.campus_name,
                                        ),
                                    ).size > 1
                                        ? ` · ${section.campus_name}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="marks-subject" label="Subject">
                        <select
                            id="marks-subject"
                            className={selectClassName}
                            value={sheet.subject_ulid}
                            onChange={(event) =>
                                navigate({ subject: event.target.value })
                            }
                        >
                            {screen.subjects.map((subject) => (
                                <option key={subject.ulid} value={subject.ulid}>
                                    {subject.name}
                                    {subject.is_mine
                                        ? ' · yours'
                                        : subject.may_enter
                                          ? ''
                                          : ' · view only'}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <MarksStrip sheet={sheet} />

                {total === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                        No students are enrolled in {sheet.section_label} yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th
                                        scope="col"
                                        className="w-14 px-4 py-2.5"
                                    >
                                        Roll
                                    </th>
                                    <th scope="col" className="px-2 py-2.5">
                                        Student
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-2 py-2.5 text-right"
                                    >
                                        Marks / {sheet.max_marks}
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-2 py-2.5 text-right sm:table-cell"
                                    >
                                        %
                                    </th>
                                    <th scope="col" className="px-4 py-2.5">
                                        Grade
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {sheet.rows.map((row, position) => {
                                    const mark = parsed[position];
                                    const value = draft[row.student_ulid] ?? '';

                                    return (
                                        <MarkRow
                                            key={row.student_ulid}
                                            position={position}
                                            roll={row.roll_number}
                                            name={row.name}
                                            value={value}
                                            mark={mark}
                                            maxMarks={sheet.max_marks}
                                            scale={screen.scale}
                                            editable={editable}
                                            inputRef={(element) => {
                                                inputs.current[position] =
                                                    element;
                                            }}
                                            onChange={(text) => {
                                                setDraft((current) => ({
                                                    ...current,
                                                    [row.student_ulid]: text,
                                                }));
                                                setError(undefined);
                                            }}
                                            onMove={(step) =>
                                                move(position, step)
                                            }
                                        />
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {editable && total > 0 && (
                    <div className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t bg-background px-4 py-3">
                        <div className="text-sm">
                            <p className="font-medium">{summaryLine}</p>
                            {error !== undefined && (
                                <p role="alert" className="text-destructive">
                                    {error}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={saveDraft}
                                disabled={processing}
                            >
                                <Save aria-hidden /> Save draft
                            </Button>
                            <Button
                                type="button"
                                onClick={publish}
                                disabled={processing}
                            >
                                <Send aria-hidden /> Publish to parents
                            </Button>
                        </div>
                    </div>
                )}

                {sheet.can_reopen && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            Found a mistake? Reopening hides these marks from
                            parents until they are published again.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setReopening(true)}
                        >
                            <RotateCcw aria-hidden /> Reopen marks
                        </Button>
                    </div>
                )}
            </Card>

            <div className="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-1">
                <MarksSummary
                    subtitle={`${sheet.section_label} · ${sheet.subject_name}`}
                    parsed={parsed}
                    maxMarks={sheet.max_marks}
                    scale={screen.scale}
                    passPercent={screen.pass_percent}
                />
                <GradeScaleCard
                    scale={screen.scale}
                    canChange={screen.can_change_scale}
                />
            </div>

            {confirmPublish && (
                <ConfirmDialog
                    title={`Publish ${sheet.subject_name} marks?`}
                    body={`Parents of ${plural(total, 'student')} in ${sheet.section_label} will see these marks on the report card and get a notification. After publishing, only the principal can reopen them.`}
                    confirmLabel="Publish"
                    onCancel={() => setConfirmPublish(false)}
                    onConfirm={() => send(true)}
                />
            )}
            {reopening && (
                <ReopenDialog
                    subject={sheet.subject_name}
                    section={sheet.section_label}
                    processing={processing}
                    error={error}
                    onCancel={() => setReopening(false)}
                    onReopen={sendReopen}
                />
            )}
            {leaving !== null && (
                <ConfirmDialog
                    title="Discard unsaved marks?"
                    body="You have marks on this sheet that aren’t saved."
                    confirmLabel="Discard"
                    destructive
                    onCancel={() => setLeaving(null)}
                    onConfirm={() => {
                        const next = leaving;

                        setLeaving(null);
                        go(next);
                    }}
                />
            )}
        </div>
    );
}

function MarkRow({
    position,
    roll,
    name,
    value,
    mark,
    maxMarks,
    scale,
    editable,
    inputRef,
    onChange,
    onMove,
}: {
    position: number;
    roll: number | null;
    name: string;
    value: string;
    mark: ParsedMark;
    maxMarks: number;
    scale: GradeBand[];
    editable: boolean;
    inputRef: (element: HTMLInputElement | null) => void;
    onChange: (text: string) => void;
    onMove: (step: number) => void;
}) {
    const inputId = `mark-${position}`;
    const band =
        mark.kind === 'marks'
            ? gradeFor(scale, mark.halves, 2 * maxMarks)
            : undefined;

    return (
        <tr>
            <td className="px-4 py-2 font-medium text-muted-foreground tabular-nums">
                {roll ?? '—'}
            </td>
            <td className="px-2 py-2">
                <div className="flex items-center gap-2.5">
                    <InitialsBadge name={name} className="size-8" />
                    <span className="font-medium">{name}</span>
                </div>
            </td>
            <td className="px-2 py-2 text-right">
                {editable ? (
                    <>
                        <label htmlFor={inputId} className="sr-only">
                            Marks for {name}
                        </label>
                        <input
                            id={inputId}
                            ref={inputRef}
                            value={value}
                            inputMode="decimal"
                            autoComplete="off"
                            maxLength={6}
                            aria-invalid={mark.kind === 'invalid' || undefined}
                            onChange={(event) => onChange(event.target.value)}
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Enter' ||
                                    event.key === 'ArrowDown'
                                ) {
                                    event.preventDefault();
                                    onMove(1);
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    onMove(-1);
                                }
                            }}
                            className={cn(
                                'h-9 w-20 rounded-md border border-input bg-transparent px-3 text-right text-sm tabular-nums shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                mark.kind === 'invalid' &&
                                    'border-destructive ring-destructive/20 focus-visible:border-destructive',
                            )}
                        />
                    </>
                ) : (
                    <span className="font-semibold tabular-nums">
                        {value === '' ? '—' : value}
                    </span>
                )}
            </td>
            <td className="hidden px-2 py-2 text-right tabular-nums sm:table-cell">
                {mark.kind === 'marks'
                    ? `${wholePercent(mark.halves, 2 * maxMarks)}%`
                    : '—'}
            </td>
            <td className="px-4 py-2">
                {mark.kind === 'absent' && <GradePill grade="AB" low />}
                {band !== undefined && (
                    <GradePill grade={band.grade} low={band.is_lowest} />
                )}
            </td>
        </tr>
    );
}

export default function Marks({ screen }: { screen: MarksScreen }) {
    const sheet = screen.sheet;
    // A new sheet or a saved version starts the draft again from the server's copy.
    const resetKey =
        sheet === null
            ? 'none'
            : `${sheet.exam_ulid}|${sheet.section_ulid}|${sheet.subject_ulid}|${sheet.state}|${sheet.rows.map((row) => row.value).join(',')}`;

    return (
        <>
            <Head title="Marks entry" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Marks entry
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enter marks for your subject. Grades follow the school’s
                        grading scale and update as you type.
                    </p>
                </div>

                {sheet === null ? (
                    <Card className="items-center gap-1 px-4 py-12 text-center">
                        <ClipboardCheck
                            className="mb-1 size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="font-medium">
                            No exam is open for marking
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Marks entry opens when the principal or coordinator
                            opens marking for an exam your classes take.
                        </p>
                    </Card>
                ) : (
                    <SheetView key={resetKey} screen={screen} sheet={sheet} />
                )}
            </div>
        </>
    );
}

Marks.layout = {
    breadcrumbs: [
        {
            title: 'Marks entry',
            href: index(),
        },
    ],
};
