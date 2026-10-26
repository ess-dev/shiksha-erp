import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, CalendarX, Send } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCalendarDay, formatInstantDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { cancel, store } from '@/routes/parent/leave';
import type { LeaveForm, LeaveStatus, ParentLeaveItem } from '@/types';
import type { ParentShell } from '@/types/parent';

type Props = {
    shell: ParentShell;
    requests: ParentLeaveItem[];
    form: LeaveForm | null;
    child_ulid: string | null;
    child_first_name: string | null;
    session_name: string;
    is_current_session: boolean;
};

const STATUS: Record<LeaveStatus, { label: string; tone: string }> = {
    pending: {
        label: 'With class teacher',
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    },
    recommended: {
        label: 'With coordinator',
        tone: 'bg-primary/10 text-primary',
    },
    approved: {
        label: 'Approved',
        tone: 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
    },
    rejected: {
        label: 'Not approved',
        tone: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    },
    cancelled: { label: 'Cancelled', tone: 'bg-muted text-muted-foreground' },
};

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/** ISO weekday (1 Monday to 7 Sunday) of a calendar date, never via the local time zone. */
function isoWeekday(value: string): number {
    const [y, m, d] = value.split('-').map(Number);
    const day = new Date(Date.UTC(y, m - 1, d)).getUTCDay();

    return day === 0 ? 7 : day;
}

function nextDay(value: string): string {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(Date.UTC(y, m - 1, d + 1)).toISOString().slice(0, 10);
}

function schoolDays(from: string, to: string, form: LeaveForm): string[] {
    const found: string[] = [];

    for (let day = from; day <= to && found.length < 400; day = nextDay(day)) {
        if (
            form.working_days.includes(isoWeekday(day)) &&
            !form.holidays.includes(day)
        ) {
            found.push(day);
        }
    }

    return found;
}

function range(from: string, to: string): string {
    return from === to
        ? formatCalendarDay(from)
        : `${formatCalendarDay(from)} – ${formatCalendarDay(to)}`;
}

export default function ParentLeave({
    shell,
    requests,
    form,
    child_ulid: childUlid,
    child_first_name: childName,
    session_name: sessionName,
    is_current_session: isCurrent,
}: Props) {
    if (childName === null || childUlid === null || form === null) {
        return (
            <>
                <Head title="Leave" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No leave for {sessionName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Leave is shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Leave" />
            <div className="grid items-start gap-4 lg:grid-cols-2">
                {isCurrent ? (
                    <ApplyCard
                        form={form}
                        childUlid={childUlid}
                        childName={childName}
                    />
                ) : (
                    <div className="rounded-xl border bg-card p-6 text-sm text-muted-foreground">
                        {sessionName} is a past session, so leave can only be
                        viewed.
                    </div>
                )}
                <RequestsCard
                    requests={requests}
                    childUlid={childUlid}
                    timezone={shell.timezone}
                />
            </div>
        </>
    );
}

function ApplyCard({
    form,
    childUlid,
    childName,
}: {
    form: LeaveForm;
    childUlid: string;
    childName: string;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const f = useForm<{
        child: string;
        from_on: string;
        to_on: string;
        reason_type: string;
        reason: string;
        attachment: File | null;
    }>({
        child: childUlid,
        from_on: form.today,
        to_on: form.today,
        reason_type: form.reasons[0]?.value ?? 'sick',
        reason: '',
        attachment: null,
    });

    const days = useMemo(
        () =>
            f.data.to_on < f.data.from_on
                ? []
                : schoolDays(f.data.from_on, f.data.to_on, form),
        [f.data.from_on, f.data.to_on, form],
    );
    const errors = f.errors as Record<string, string | undefined>;
    const teacher = form.class_teacher_name ?? 'the class teacher';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        f.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                f.reset('reason', 'attachment');

                if (fileInput.current !== null) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-xl border bg-card p-5"
            noValidate
        >
            <h2 className="text-base font-semibold">Apply for leave</h2>
            <p className="-mt-2 text-sm text-muted-foreground">
                For {childName}.
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="lv-from" label="From" error={errors.from_on}>
                    <Input
                        id="lv-from"
                        type="date"
                        min={form.min_date}
                        value={f.data.from_on}
                        onChange={(e) => {
                            f.setData((d) => ({
                                ...d,
                                from_on: e.target.value,
                                to_on:
                                    d.to_on < e.target.value
                                        ? e.target.value
                                        : d.to_on,
                            }));
                        }}
                    />
                </Field>
                <Field id="lv-to" label="To" error={errors.to_on}>
                    <Input
                        id="lv-to"
                        type="date"
                        min={f.data.from_on}
                        value={f.data.to_on}
                        onChange={(e) => f.setData('to_on', e.target.value)}
                    />
                </Field>
            </div>

            <Field id="lv-type" label="Reason type" error={errors.reason_type}>
                <select
                    id="lv-type"
                    className={selectClassName}
                    value={f.data.reason_type}
                    onChange={(e) => f.setData('reason_type', e.target.value)}
                >
                    {form.reasons.map((reason) => (
                        <option key={reason.value} value={reason.value}>
                            {reason.label}
                        </option>
                    ))}
                </select>
            </Field>

            <Field
                id="lv-reason"
                label="Details for the teacher"
                error={errors.reason}
            >
                <textarea
                    id="lv-reason"
                    className={textareaClassName}
                    maxLength={form.reason_max}
                    placeholder="Fever since last night; the doctor advised two days’ rest."
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                />
            </Field>

            <Field
                id="lv-doc"
                label="Medical certificate or letter (optional)"
                error={errors.attachment}
            >
                <Input
                    id="lv-doc"
                    ref={fileInput}
                    type="file"
                    accept="image/*,application/pdf"
                    onChange={(e) =>
                        f.setData('attachment', e.target.files?.[0] ?? null)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    A photo or PDF of at most {form.max_file_mb} MB.
                </p>
            </Field>

            <div
                className="rounded-md bg-muted/50 px-3 py-2 text-sm"
                aria-live="polite"
            >
                {days.length === 0 ? (
                    <span className="text-amber-700 dark:text-amber-300">
                        {f.data.to_on < f.data.from_on
                            ? 'Pick the dates.'
                            : 'There is no school on these dates, so no leave is needed.'}
                    </span>
                ) : (
                    <>
                        <strong>
                            {days.length} school{' '}
                            {days.length === 1 ? 'day' : 'days'}
                        </strong>{' '}
                        ({range(f.data.from_on, f.data.to_on)})
                        {days.length > form.escalation_days && (
                            <span className="text-amber-700 dark:text-amber-300">
                                {' '}
                                · needs coordinator approval too
                            </span>
                        )}
                    </>
                )}
            </div>

            <InputError message={errors.leave} />

            <Button type="submit" disabled={f.processing}>
                <Send aria-hidden /> Send to {teacher}
            </Button>
            <p className="text-xs text-muted-foreground">
                Leave of up to {form.escalation_days} school{' '}
                {form.escalation_days === 1 ? 'day is' : 'days is'} approved by
                the class teacher. Longer leave is also reviewed by the
                coordinator. You can tell the school about a sick day up to{' '}
                {Math.round(
                    (Date.parse(form.today) - Date.parse(form.min_date)) /
                        86400000,
                )}{' '}
                days later.
            </p>
        </form>
    );
}

function RequestsCard({
    requests,
    childUlid,
    timezone,
}: {
    requests: ParentLeaveItem[];
    childUlid: string;
    timezone: string;
}) {
    const [busy, setBusy] = useState<string | null>(null);
    const [problem, setProblem] = useState<string | null>(null);

    const withdraw = (item: ParentLeaveItem) => {
        if (
            !window.confirm(
                'Cancel this leave request? The teacher won’t review it.',
            )
        ) {
            return;
        }

        router.post(
            cancel.url(item.ulid),
            { child: childUlid },
            {
                preserveScroll: true,
                onStart: () => {
                    setBusy(item.ulid);
                    setProblem(null);
                },
                onFinish: () => setBusy(null),
                onError: (errors) =>
                    setProblem(Object.values(errors)[0] ?? 'Could not cancel.'),
            },
        );
    };

    return (
        <section className="rounded-xl border bg-card p-5">
            <h2 className="text-base font-semibold">Your requests</h2>
            {problem !== null && <InputError message={problem} />}

            {requests.length === 0 ? (
                <div className="flex flex-col items-center gap-1 py-10 text-center">
                    <CalendarCheck
                        className="size-6 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="font-medium">No leave requests</p>
                    <p className="text-sm text-muted-foreground">
                        Requests you send appear here with their status.
                    </p>
                </div>
            ) : (
                <ul className="mt-3 grid gap-4">
                    {requests.map((item) => {
                        const status = STATUS[item.status];

                        return (
                            <li key={item.ulid} className="flex gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <CalendarX className="size-5" aria-hidden />
                                </span>
                                <div className="min-w-0 grow">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {range(item.from_on, item.to_on)} ·{' '}
                                            {item.school_days}{' '}
                                            {item.school_days === 1
                                                ? 'day'
                                                : 'days'}
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
                                    <p className="text-sm text-muted-foreground">
                                        {item.reason_label} · {item.reason}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Sent{' '}
                                        {formatInstantDate(
                                            item.applied_at,
                                            timezone,
                                        )}
                                        {item.recommended_by !== null &&
                                            ` · recommended by ${item.recommended_by}`}
                                        {item.decided_by !== null &&
                                            ` · ${item.status === 'approved' ? 'approved' : 'declined'} by ${item.decided_by}`}
                                    </p>
                                    {item.note !== null && (
                                        <p className="mt-2 rounded-md bg-muted/50 px-3 py-2 text-sm">
                                            <strong>Teacher:</strong>{' '}
                                            {item.note}
                                        </p>
                                    )}
                                    {item.can_cancel && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="mt-2"
                                            disabled={busy === item.ulid}
                                            onClick={() => withdraw(item)}
                                        >
                                            Cancel request
                                        </Button>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
