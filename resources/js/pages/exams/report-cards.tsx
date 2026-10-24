import { Head, router } from '@inertiajs/react';
import { FileBadge, Upload, WandSparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import ReportCardDialog from '@/components/exams/report-card-dialog';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatCalendarDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import { generate, index, upload, withdraw } from '@/routes/report-cards';
import type { ReportCardRow, ReportCardsScreen, ReportCardView } from '@/types';
import type { ReportCard } from '@/types/parent';

type Props = {
    screen: ReportCardsScreen;
    preview?: ReportCard | null;
    view?: ReportCardView | null;
};

const MAX_REMARK = 300;

function shortDay(value: string): string {
    // "Thu, 3 Oct" → "3 Oct", as the prototype shows it.
    return formatCalendarDay(value).replace(/^\w+, /, '');
}

function Counter({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border px-4 py-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function Pill({
    tone,
    children,
}: {
    tone: 'green' | 'amber' | 'red' | 'grey';
    children: string;
}) {
    const tones = {
        green: 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
        red: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
        grey: 'bg-muted text-muted-foreground',
    };

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}

function MarksCell({ row }: { row: ReportCardRow }) {
    if (row.marks === 'ready') {
        return (
            <span className="text-sm text-green-700 dark:text-green-400">
                All subjects entered
            </span>
        );
    }

    return (
        <span className="text-sm text-amber-700 dark:text-amber-400">
            {row.marks === 'missing'
                ? 'Marks missing'
                : 'Marks not published yet'}
        </span>
    );
}

function CardCell({ row }: { row: ReportCardRow }) {
    const card = row.card;

    if (card === null) {
        return <Pill tone="grey">Not uploaded</Pill>;
    }

    const label = {
        published: 'Published',
        submitted: 'Awaiting approval',
        returned: 'Sent back',
    }[card.status];
    const tone = { published: 'green', submitted: 'amber', returned: 'red' }[
        card.status
    ] as 'green' | 'amber' | 'red';
    const source =
        card.kind === 'file'
            ? `File${card.file_name ? `: ${card.file_name}` : ''}`
            : 'Generated';

    return (
        <div className="flex flex-col items-start gap-0.5">
            <Pill tone={tone}>{label}</Pill>
            <span className="text-sm text-muted-foreground">
                {source} · {shortDay(card.prepared_on)}
            </span>
            {card.return_note !== null && (
                <span className="text-sm text-amber-700 dark:text-amber-400">
                    {card.return_note}
                </span>
            )}
        </div>
    );
}

export default function ReportCards({ screen, preview, view }: Props) {
    const [previewing, setPreviewing] = useState<{
        ulid: string;
        name: string;
    } | null>(null);
    const [remark, setRemark] = useState('');
    const [viewing, setViewing] = useState<string | null>(null);
    const [confirmAll, setConfirmAll] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const uploadFor = useRef<string | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);

    const sendsForApproval = screen.approval_required;
    // Partial reloads replace these props; ignore one left from an earlier student.
    const previewCard =
        preview && previewing && preview.student_name === previewing.name
            ? preview
            : null;
    const viewed = view && view.ulid === viewing ? view : null;
    const todo = screen.rows.filter(
        (row) => row.card === null || row.card.status === 'returned',
    );
    const ready = todo.filter((row) => row.marks === 'ready');

    // A new preview arriving fills the remark box with the suggested remark.
    useEffect(() => {
        if (previewCard !== null) {
            setRemark(previewCard.remark ?? '');
        }
    }, [previewCard]);

    const go = (next: { section?: string; exam?: string }) =>
        router.get(
            index.url(),
            {
                section: next.section ?? screen.section_ulid ?? undefined,
                exam: next.section
                    ? undefined
                    : (next.exam ?? screen.exam_ulid ?? undefined),
            },
            { preserveScroll: true, replace: true },
        );

    const query = {
        section: screen.section_ulid ?? undefined,
        exam: screen.exam_ulid ?? undefined,
    };

    const openPreview = (row: ReportCardRow) => {
        setError(undefined);
        setPreviewing({ ulid: row.student_ulid, name: row.name });
        router.reload({
            only: ['preview'],
            data: { ...query, preview: row.student_ulid },
        });
    };

    const openView = (cardUlid: string) => {
        setViewing(cardUlid);
        router.reload({ only: ['view'], data: { ...query, view: cardUlid } });
    };

    const options = (onDone?: () => void) => ({
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setError(undefined);
            onDone?.();
        },
        onError: (errors: Record<string, string>) =>
            setError(errors.report_cards ?? Object.values(errors)[0]),
    });

    const send = (
        remarks: Record<string, string | null>,
        onDone?: () => void,
    ) => {
        if (screen.exam_ulid === null) {
            return;
        }

        router.post(
            generate.url(screen.exam_ulid),
            { section: screen.section_ulid, remarks },
            options(onDone),
        );
    };

    const chooseFile = (studentUlid: string) => {
        uploadFor.current = studentUlid;

        if (fileInput.current) {
            fileInput.current.value = '';
            fileInput.current.click();
        }
    };

    const uploadFile = (file: File | undefined) => {
        const student = uploadFor.current;

        if (
            file === undefined ||
            student === null ||
            screen.exam_ulid === null
        ) {
            return;
        }

        router.post(
            upload.url(screen.exam_ulid),
            { section: screen.section_ulid, student, file },
            { ...options(), forceFormData: true },
        );
    };

    const subtitle = sendsForApproval
        ? 'Prepare report cards for your class. Parents see them once they’re approved.'
        : 'Prepare report cards for your class. Parents see them as soon as you send them.';

    if (screen.sections.length === 0) {
        return (
            <>
                <Head title="Report cards" />
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Report cards
                    </h1>
                    <Card className="items-center gap-1 px-4 py-12 text-center">
                        <FileBadge
                            className="mb-1 size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="font-medium">
                            Only class teachers prepare report cards
                        </p>
                        <p className="text-sm text-muted-foreground">
                            You can still enter marks for the subjects you
                            teach.
                        </p>
                    </Card>
                </div>
            </>
        );
    }

    const section = screen.sections.find((s) => s.ulid === screen.section_ulid);

    return (
        <>
            <Head title="Report cards" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Report cards
                    </h1>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>

                <Card className="gap-0 p-0">
                    <div className="flex flex-wrap items-end gap-4 border-b p-4">
                        {screen.sections.length > 1 ? (
                            <Field id="rc-class" label="Class">
                                <select
                                    id="rc-class"
                                    className={cn(selectClassName, 'min-w-40')}
                                    value={screen.section_ulid ?? ''}
                                    onChange={(event) =>
                                        go({ section: event.target.value })
                                    }
                                >
                                    {screen.sections.map((option) => (
                                        <option
                                            key={option.ulid}
                                            value={option.ulid}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        ) : (
                            <div className="grid min-w-40 gap-2">
                                <span className="text-sm font-medium">
                                    Class
                                </span>
                                <strong className="leading-9">
                                    {section?.label}
                                </strong>
                            </div>
                        )}
                        {screen.exams.length > 0 && (
                            <Field id="rc-exam" label="Exam">
                                <select
                                    id="rc-exam"
                                    className={cn(selectClassName, 'min-w-64')}
                                    value={screen.exam_ulid ?? ''}
                                    onChange={(event) =>
                                        go({ exam: event.target.value })
                                    }
                                >
                                    {screen.exams.map((option) => (
                                        <option
                                            key={option.ulid}
                                            value={option.ulid}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        <div className="grow" />
                        <Button
                            type="button"
                            disabled={todo.length === 0 || processing}
                            onClick={() => setConfirmAll(true)}
                        >
                            <WandSparkles aria-hidden /> Generate all missing (
                            {todo.length})
                        </Button>
                    </div>
                    <div className="flex flex-col gap-3 p-4">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Counter
                                label="Not started"
                                value={screen.not_started_count}
                            />
                            <Counter
                                label="Sent back"
                                value={screen.sent_back_count}
                            />
                            <Counter
                                label="Awaiting approval"
                                value={screen.awaiting_count}
                            />
                            <Counter
                                label="Visible to parents"
                                value={screen.published_count}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {sendsForApproval
                                ? `Report cards go to ${screen.approvers ?? 'the coordinator'} for approval before parents can see them.`
                                : 'Approval is switched off, so report cards are published to parents immediately.'}
                        </p>
                        {error !== undefined && !previewing && (
                            <InputError message={error} />
                        )}
                    </div>
                </Card>

                {screen.exams.length === 0 ? (
                    <Card className="items-center gap-1 px-4 py-12 text-center">
                        <FileBadge
                            className="mb-1 size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="font-medium">No exam has marks yet</p>
                        <p className="text-sm text-muted-foreground">
                            Report cards can be made once marking opens for an
                            exam your class takes.
                        </p>
                    </Card>
                ) : (
                    <Card className="gap-0 overflow-hidden p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-16 px-4 py-3"
                                        >
                                            Roll
                                        </th>
                                        <th scope="col" className="px-2 py-3">
                                            Student
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-2 py-3 sm:table-cell"
                                        >
                                            Marks
                                        </th>
                                        <th scope="col" className="px-2 py-3">
                                            Report card
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {screen.rows.map((row) => {
                                        const editable =
                                            row.card === null ||
                                            row.card.status === 'returned';

                                        return (
                                            <tr key={row.student_ulid}>
                                                <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                    {row.roll_number ?? '—'}
                                                </td>
                                                <td className="px-2 py-3">
                                                    <div className="flex items-center gap-2.5">
                                                        <InitialsBadge
                                                            name={row.name}
                                                            className="size-8"
                                                        />
                                                        <span className="font-medium">
                                                            {row.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="hidden px-2 py-3 sm:table-cell">
                                                    <MarksCell row={row} />
                                                </td>
                                                <td className="px-2 py-3">
                                                    <CardCell row={row} />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        {row.card !== null && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    row.card &&
                                                                    openView(
                                                                        row.card
                                                                            .ulid,
                                                                    )
                                                                }
                                                            >
                                                                View
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    {row.name}’s
                                                                    report card
                                                                </span>
                                                            </Button>
                                                        )}
                                                        {editable && (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={
                                                                        row.marks !==
                                                                        'ready'
                                                                    }
                                                                    title={
                                                                        row.marks !==
                                                                        'ready'
                                                                            ? 'Enter and publish all marks first'
                                                                            : undefined
                                                                    }
                                                                    onClick={() =>
                                                                        openPreview(
                                                                            row,
                                                                        )
                                                                    }
                                                                >
                                                                    Generate
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        for{' '}
                                                                        {
                                                                            row.name
                                                                        }
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    onClick={() =>
                                                                        chooseFile(
                                                                            row.student_ulid,
                                                                        )
                                                                    }
                                                                >
                                                                    <Upload
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Upload
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        for{' '}
                                                                        {
                                                                            row.name
                                                                        }
                                                                    </span>
                                                                </Button>
                                                            </>
                                                        )}
                                                        {row.card?.status ===
                                                            'submitted' && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                disabled={
                                                                    processing
                                                                }
                                                                onClick={() =>
                                                                    row.card &&
                                                                    router.post(
                                                                        withdraw.url(
                                                                            row
                                                                                .card
                                                                                .ulid,
                                                                        ),
                                                                        {},
                                                                        options(),
                                                                    )
                                                                }
                                                            >
                                                                Withdraw
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>

            <input
                ref={fileInput}
                type="file"
                accept="application/pdf,image/jpeg,image/png"
                className="sr-only"
                aria-label="Report card file"
                tabIndex={-1}
                onChange={(event) => uploadFile(event.target.files?.[0])}
            />

            {previewing !== null && (
                <ReportCardDialog
                    title={`Report card: ${screen.exam_name ?? ''} · ${previewing.name}`}
                    card={
                        previewCard === null ? null : { ...previewCard, remark }
                    }
                    onClose={() => setPreviewing(null)}
                    actions={
                        <Button
                            type="button"
                            disabled={processing || previewCard === null}
                            onClick={() =>
                                send({ [previewing.ulid]: remark }, () =>
                                    setPreviewing(null),
                                )
                            }
                        >
                            {sendsForApproval
                                ? 'Send for approval'
                                : 'Publish to parents'}
                        </Button>
                    }
                >
                    {previewCard === null ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            Preparing the report card…
                        </p>
                    ) : (
                        <div className="mt-4 grid gap-2">
                            <Field
                                id="rc-remark"
                                label="Class teacher’s remarks"
                                error={error}
                            >
                                <textarea
                                    id="rc-remark"
                                    value={remark}
                                    maxLength={MAX_REMARK}
                                    rows={2}
                                    onChange={(event) =>
                                        setRemark(event.target.value)
                                    }
                                    className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                            </Field>
                            <p className="text-xs text-muted-foreground">
                                A suggestion from the overall result; change it
                                as you like. The card shows what you send.
                            </p>
                        </div>
                    )}
                </ReportCardDialog>
            )}

            {viewing !== null && (
                <ReportCardDialog
                    title={
                        viewed
                            ? `Report card: ${viewed.exam_name} · ${viewed.session_name}`
                            : 'Report card'
                    }
                    card={viewed?.card ?? null}
                    file={
                        viewed && viewed.file_url !== null
                            ? {
                                  url: viewed.file_url,
                                  name: viewed.file_name,
                                  isImage: viewed.is_image,
                              }
                            : null
                    }
                    onClose={() => setViewing(null)}
                >
                    {viewed === null && (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            Opening the report card…
                        </p>
                    )}
                </ReportCardDialog>
            )}

            {confirmAll && (
                <ConfirmDialog
                    title={`Generate ${ready.length} report ${ready.length === 1 ? 'card' : 'cards'}?`}
                    body={
                        (todo.length > ready.length
                            ? `${todo.length - ready.length} ${todo.length - ready.length === 1 ? 'student is' : 'students are'} skipped because marks are not entered and published. `
                            : '') +
                        (sendsForApproval
                            ? 'They go to the coordinator for approval.'
                            : 'Parents see them straight away.')
                    }
                    confirmLabel="Generate"
                    onCancel={() => setConfirmAll(false)}
                    onConfirm={() => {
                        setConfirmAll(false);

                        if (ready.length === 0) {
                            setError(
                                'Marks are missing for everyone left. Enter and publish marks first.',
                            );

                            return;
                        }

                        send(
                            Object.fromEntries(
                                ready.map((row) => [row.student_ulid, null]),
                            ),
                        );
                    }}
                />
            )}
        </>
    );
}

ReportCards.layout = {
    breadcrumbs: [{ title: 'Report cards', href: index() }],
};
