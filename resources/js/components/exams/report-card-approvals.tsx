import { router } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import ReportCardDialog from '@/components/exams/report-card-dialog';
import Field, { selectClassName } from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCalendarDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import { decide } from '@/routes/report-cards';
import type {
    ApprovalCard,
    ApprovalGroup,
    ReportCardApprovals,
    ReportCardView,
} from '@/types';

type Filter = 'submitted' | 'published' | 'returned' | 'all';

const FILTERS: { key: Filter; label: string }[] = [
    { key: 'submitted', label: 'Awaiting approval' },
    { key: 'published', label: 'Published' },
    { key: 'returned', label: 'Sent back' },
    { key: 'all', label: 'All' },
];

function shortDay(value: string): string {
    return formatCalendarDay(value).replace(/^\w+, /, '');
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

function StatusPill({ status }: { status: ApprovalCard['status'] }) {
    const label = {
        submitted: 'Awaiting approval',
        published: 'Published',
        returned: 'Sent back',
    }[status];
    const tone = {
        submitted:
            'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
        published:
            'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
        returned:
            'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    }[status];

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                tone,
            )}
        >
            {label}
        </span>
    );
}

/**
 * The approvers' list of report cards, grouped by class and exam as in the
 * prototype's Approvals inbox: Send back and Publish all per group, Preview
 * and Publish per student. `filters` adds the status and class pickers the
 * Report cards screen uses; `url` and `query` say where a reload goes.
 */
export default function ReportCardApprovalsList({
    data,
    view,
    url,
    query = {},
    filters = false,
}: {
    data: ReportCardApprovals;
    view?: ReportCardView | null;
    url: string;
    query?: Record<string, string | undefined>;
    filters?: boolean;
}) {
    const [viewing, setViewing] = useState<string | null>(null);
    const [publishing, setPublishing] = useState<ApprovalGroup | null>(null);
    const [sendingBack, setSendingBack] = useState<ApprovalGroup | null>(null);
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    const filter: Filter = data.status ?? 'all';
    const viewed = view && view.ulid === viewing ? view : null;

    const go = (next: Record<string, string | undefined>) =>
        router.get(
            url,
            { ...query, ...next },
            { preserveScroll: true, replace: true },
        );

    const openView = (ulid: string) => {
        setViewing(ulid);
        router.reload({ only: ['view'], data: { ...query, view: ulid } });
    };

    const send = (
        group: ApprovalGroup,
        approve: boolean,
        extra: { note?: string; students?: string[] } = {},
        onDone?: () => void,
    ) =>
        router.post(
            decide.url(group.exam_ulid),
            { section: group.section_ulid, approve, ...extra },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setError(undefined);
                    onDone?.();
                },
                onError: (errors) =>
                    setError(errors.report_cards ?? Object.values(errors)[0]),
            },
        );

    const groupOf = (cardUlid: string) =>
        data.groups.find((group) =>
            group.cards.some((card) => card.ulid === cardUlid),
        );

    const publishOne = (card: ApprovalCard, onDone?: () => void) => {
        const group = groupOf(card.ulid);

        if (!group) {
            return;
        }

        // The Action takes student ulids, so find this card's student.
        send(group, true, { students: [card.student_ulid] }, onDone);
    };

    return (
        <div className="flex flex-col gap-4">
            {filters && (
                <Card className="flex-row flex-wrap items-end gap-4 p-4">
                    <Field id="rca-status" label="Show">
                        <select
                            id="rca-status"
                            className={cn(selectClassName, 'min-w-48')}
                            value={filter}
                            onChange={(event) =>
                                go({
                                    status: event.target.value,
                                })
                            }
                        >
                            {FILTERS.map((option) => (
                                <option key={option.key} value={option.key}>
                                    {option.label}
                                    {option.key === 'submitted'
                                        ? ` (${data.awaiting_count})`
                                        : option.key === 'published'
                                          ? ` (${data.published_count})`
                                          : option.key === 'returned'
                                            ? ` (${data.sent_back_count})`
                                            : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    {data.sections.length > 1 && (
                        <Field id="rca-class" label="Class">
                            <select
                                id="rca-class"
                                className={cn(selectClassName, 'min-w-48')}
                                value={data.section_ulid ?? ''}
                                onChange={(event) =>
                                    go({
                                        class: event.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">All classes</option>
                                {data.sections.map((section) => (
                                    <option
                                        key={section.ulid}
                                        value={section.ulid}
                                    >
                                        {section.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                </Card>
            )}

            {data.groups.length === 0 && (
                <Card className="items-center gap-1 px-4 py-12 text-center">
                    <FileCheck
                        className="mb-1 size-6 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="font-medium">
                        {filter === 'submitted'
                            ? 'No report cards waiting'
                            : 'No report cards to show'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Class teachers send report cards here before parents can
                        see them.
                    </p>
                </Card>
            )}

            {data.groups.map((group) => (
                <Card
                    key={`${group.section_ulid}-${group.exam_ulid}`}
                    className="gap-0 p-0"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
                        <div>
                            <h2 className="font-semibold">
                                {group.section_label} · {group.exam_name}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {group.class_teacher_name
                                    ? `Class teacher ${group.class_teacher_name} · `
                                    : ''}
                                {group.published_count} of {group.student_count}{' '}
                                already published
                                {data.sections.length > 1 &&
                                    ` · ${group.campus_name}`}
                            </p>
                        </div>
                        {group.awaiting_count > 0 && (
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => {
                                        setNote('');
                                        setError(undefined);
                                        setSendingBack(group);
                                    }}
                                >
                                    Send back
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => setPublishing(group)}
                                >
                                    Publish all {group.awaiting_count}
                                </Button>
                            </div>
                        )}
                    </div>
                    <ul className="divide-y">
                        {group.cards.map((card) => (
                            <li
                                key={card.ulid}
                                className="flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <InitialsBadge name={card.student_name} />
                                <div className="min-w-48 flex-1">
                                    <p className="font-medium">
                                        {card.student_name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {group.section_label} ·{' '}
                                        {card.admission_number}
                                    </p>
                                </div>
                                <div className="flex min-w-48 flex-1 flex-col items-start gap-0.5">
                                    {card.status !== 'submitted' && (
                                        <StatusPill status={card.status} />
                                    )}
                                    <span className="text-sm text-muted-foreground">
                                        {card.kind === 'file'
                                            ? `Uploaded file${card.file_name ? `: ${card.file_name}` : ''}`
                                            : 'Generated from marks'}{' '}
                                        · {shortDay(card.prepared_on)}
                                    </span>
                                    {card.return_note !== null && (
                                        <span className="text-sm text-amber-700 dark:text-amber-400">
                                            {card.return_note}
                                        </span>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openView(card.ulid)}
                                    >
                                        Preview
                                        <span className="sr-only">
                                            {' '}
                                            {card.student_name}’s report card
                                        </span>
                                    </Button>
                                    {card.status === 'submitted' && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={processing}
                                            onClick={() => publishOne(card)}
                                        >
                                            Publish
                                            <span className="sr-only">
                                                {' '}
                                                {card.student_name}’s report
                                                card
                                            </span>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            ))}

            {publishing !== null && (
                <ConfirmDialog
                    title={`Publish ${plural(publishing.awaiting_count, 'report card', 'report cards')}?`}
                    body={`${publishing.section_label} · ${publishing.exam_name}. Parents will see them straight away.`}
                    confirmLabel="Publish"
                    onCancel={() => setPublishing(null)}
                    onConfirm={() => {
                        const group = publishing;
                        setPublishing(null);
                        send(group, true);
                    }}
                />
            )}

            {sendingBack !== null && (
                <Dialog
                    open
                    onOpenChange={(open) => !open && setSendingBack(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                Send back to{' '}
                                {sendingBack.class_teacher_name ??
                                    'the class teacher'}
                            </DialogTitle>
                            <DialogDescription>
                                {sendingBack.section_label} ·{' '}
                                {sendingBack.exam_name}.{' '}
                                {plural(
                                    sendingBack.awaiting_count,
                                    'report card',
                                    'report cards',
                                )}{' '}
                                will go back to be prepared again.
                            </DialogDescription>
                        </DialogHeader>
                        <Field
                            id="rca-note"
                            label="What needs fixing?"
                            error={error}
                        >
                            <textarea
                                id="rca-note"
                                value={note}
                                maxLength={200}
                                rows={3}
                                placeholder="Remarks missing for three students."
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSendingBack(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    send(sendingBack, false, { note }, () =>
                                        setSendingBack(null),
                                    )
                                }
                            >
                                Send back
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
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
                    actions={(() => {
                        const card = data.groups
                            .flatMap((group) => group.cards)
                            .find((item) => item.ulid === viewing);

                        return card?.status === 'submitted' ? (
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    publishOne(card, () => setViewing(null))
                                }
                            >
                                Publish to parents
                            </Button>
                        ) : null;
                    })()}
                >
                    {viewed === null && (
                        <p className="text-sm text-muted-foreground">
                            Loading…
                        </p>
                    )}
                </ReportCardDialog>
            )}
        </div>
    );
}
