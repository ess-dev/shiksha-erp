import { router } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { decide } from '@/routes/leave';
import type { LeaveStatus, StaffLeaveItem } from '@/types';

type Decision = 'approve' | 'reject' | 'recommend';

const STATUS: Record<LeaveStatus, { label: string; tone: string }> = {
    pending: {
        label: 'Pending',
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    },
    recommended: {
        label: 'Recommended',
        tone: 'bg-primary/10 text-primary',
    },
    approved: {
        label: 'Approved',
        tone: 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
    },
    rejected: {
        label: 'Rejected',
        tone: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    },
    cancelled: { label: 'Cancelled', tone: 'bg-muted text-muted-foreground' },
};

const textareaClassName =
    'min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const TITLES: Record<Decision, string> = {
    approve: 'Approve leave',
    reject: 'Reject leave',
    recommend: 'Recommend to the coordinator',
};

function range(from: string, to: string): string {
    return from === to
        ? formatCalendarDay(from)
        : `${formatCalendarDay(from)} – ${formatCalendarDay(to)}`;
}

/** One leave request with the decisions the signed-in person may take. */
export default function LeaveCard({
    item,
    escalationDays,
}: {
    item: StaffLeaveItem;
    escalationDays: number;
}) {
    const [decision, setDecision] = useState<Decision | null>(null);
    const status = STATUS[item.status];

    return (
        <li className="flex flex-wrap items-start gap-3 px-4 py-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                {item.student_name
                    .split(' ')
                    .map((part) => part[0])
                    .slice(0, 2)
                    .join('')}
            </span>
            <div className="min-w-56 grow">
                <div className="flex flex-wrap items-center gap-2">
                    <strong>{item.student_name}</strong>
                    <span className="text-sm text-muted-foreground">
                        {item.section_label}
                        {item.roll_number !== null &&
                            ` · Roll ${item.roll_number}`}
                    </span>
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            status.tone,
                        )}
                    >
                        {status.label}
                    </span>
                </div>
                <p className="text-sm">
                    {item.reason_label} · {range(item.from_on, item.to_on)} ·{' '}
                    <strong>
                        {item.school_days} school{' '}
                        {item.school_days === 1 ? 'day' : 'days'}
                    </strong>
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    “{item.reason}” — {item.parent_name},{' '}
                    {formatCalendarDay(item.applied_on)}
                    {item.attachment !== null && (
                        <>
                            {' · '}
                            <a
                                href={item.attachment.url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
                            >
                                <Paperclip className="size-3.5" aria-hidden />
                                {item.attachment.name}
                            </a>
                        </>
                    )}
                </p>
                {item.can_recommend && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        Longer than {escalationDays} school{' '}
                        {escalationDays === 1 ? 'day' : 'days'}, so the
                        coordinator makes the final decision. Add your
                        recommendation.
                    </p>
                )}
                {item.status === 'pending' &&
                    item.needs_coordinator &&
                    !item.can_recommend &&
                    !item.can_approve && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            Waiting for the class teacher’s recommendation.
                        </p>
                    )}
                {item.recommended_by !== null && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        Recommended by {item.recommended_by}
                        {item.recommendation_note !== null &&
                            `: “${item.recommendation_note}”`}
                    </p>
                )}
                {item.decided_by !== null && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.status === 'approved' ? 'Approved' : 'Rejected'}{' '}
                        by {item.decided_by}
                        {item.decision_note !== null &&
                            `: “${item.decision_note}”`}
                    </p>
                )}
            </div>
            {(item.can_reject || item.can_approve || item.can_recommend) && (
                <div className="flex gap-2">
                    {item.can_reject && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setDecision('reject')}
                        >
                            Reject
                        </Button>
                    )}
                    {item.can_approve && (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setDecision('approve')}
                        >
                            Approve
                        </Button>
                    )}
                    {item.can_recommend && (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setDecision('recommend')}
                        >
                            Recommend to coordinator
                        </Button>
                    )}
                </div>
            )}
            {decision !== null && (
                <DecisionDialog
                    item={item}
                    decision={decision}
                    onClose={() => setDecision(null)}
                />
            )}
        </li>
    );
}

function DecisionDialog({
    item,
    decision,
    onClose,
}: {
    item: StaffLeaveItem;
    decision: Decision;
    onClose: () => void;
}) {
    const [note, setNote] = useState('');
    const [error, setError] = useState<string>();
    const [busy, setBusy] = useState(false);
    const first = item.student_name.split(' ')[0];

    const send = () => {
        router.post(
            decide.url(item.ulid),
            { decision, note },
            {
                preserveScroll: true,
                onStart: () => {
                    setBusy(true);
                    setError(undefined);
                },
                onFinish: () => setBusy(false),
                onSuccess: onClose,
                onError: (errors) =>
                    setError(
                        errors.leave ??
                            errors.note ??
                            Object.values(errors)[0] ??
                            'That did not work.',
                    ),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {TITLES[decision]} · {first}
                    </DialogTitle>
                    <DialogDescription>
                        {decision === 'recommend'
                            ? 'Goes to the coordinator.'
                            : 'The parents are told what you decide.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <label htmlFor="lv-note" className="text-sm font-medium">
                        {decision === 'recommend'
                            ? 'Your recommendation'
                            : `Note for the parent${decision === 'approve' ? ' (optional)' : ''}`}
                    </label>
                    <textarea
                        id="lv-note"
                        className={textareaClassName}
                        maxLength={200}
                        value={note}
                        placeholder={
                            decision === 'approve'
                                ? 'Get well soon!'
                                : decision === 'recommend'
                                  ? 'Good attendance otherwise; I’ll share notes.'
                                  : 'This clashes with the unit test. Please speak to me.'
                        }
                        onChange={(e) => setNote(e.target.value)}
                    />
                    <InputError message={error} />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Back
                    </Button>
                    <Button
                        type="button"
                        variant={
                            decision === 'reject' ? 'destructive' : 'default'
                        }
                        disabled={busy}
                        onClick={send}
                    >
                        {decision === 'approve'
                            ? 'Approve'
                            : decision === 'reject'
                              ? 'Reject'
                              : 'Send recommendation'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
