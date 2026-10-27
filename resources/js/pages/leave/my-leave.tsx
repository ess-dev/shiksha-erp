import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, Send } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import StaffLeaveRow, {
    formatHalfDays,
} from '@/components/leave/staff-leave-row';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as myLeave, cancel, store } from '@/routes/my-leave';
import type { LeaveBalance, MyLeave as MyLeaveData } from '@/types';

const textareaClassName =
    'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function BalanceCard({ balance }: { balance: LeaveBalance }) {
    const allowed = balance.allowed_half_days;
    const used = balance.used_half_days;
    const pending = balance.pending_half_days;
    const percent =
        allowed === null || allowed === 0
            ? 0
            : Math.min(100, ((used + pending) / allowed) * 100);

    return (
        <Card className="gap-1 p-4">
            <span className="text-sm text-muted-foreground">
                {balance.label}
            </span>
            <strong className="text-2xl font-semibold">
                {allowed === null
                    ? 'As needed'
                    : `${(allowed - used - pending) / 2} left`}
            </strong>
            <span className="text-xs text-muted-foreground">
                {allowed === null
                    ? `${used / 2} used`
                    : `${used / 2} used of ${allowed / 2}${pending > 0 ? ` · ${pending / 2} pending` : ''}`}
            </span>
            {allowed !== null && (
                <div
                    className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                    aria-hidden
                >
                    <div
                        className="h-full bg-primary"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
        </Card>
    );
}

export default function MyLeave({ leave }: { leave: MyLeaveData }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const f = useForm<{
        leave_type: string;
        half_day: boolean;
        from_on: string;
        to_on: string;
        reason: string;
        suggested_cover: string;
        attachment: File | null;
    }>({
        leave_type: leave.balances[0]?.type ?? 'casual',
        half_day: false,
        from_on: leave.today,
        to_on: leave.today,
        reason: '',
        suggested_cover: '',
        attachment: null,
    });
    const errors = f.errors as Record<string, string | undefined>;
    const balance = leave.balances.find((b) => b.type === f.data.leave_type);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        f.transform((data) => ({
            ...data,
            half_day: data.half_day ? 1 : 0,
            to_on: data.half_day ? data.from_on : data.to_on,
        }));
        f.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                f.reset('reason', 'suggested_cover', 'attachment');

                if (fileInput.current !== null) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <>
            <Head title="My leave" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        My leave
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Apply for leave and see your balance for this session.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {leave.balances.map((b) => (
                        <BalanceCard key={b.type} balance={b} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <form
                        onSubmit={submit}
                        className="grid content-start gap-4 rounded-xl border bg-card p-5"
                        noValidate
                    >
                        <h2 className="text-base font-semibold">
                            Apply for leave
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="ml-type"
                                label="Type"
                                error={errors.leave_type}
                            >
                                <select
                                    id="ml-type"
                                    className={selectClassName}
                                    value={f.data.leave_type}
                                    onChange={(e) =>
                                        f.setData('leave_type', e.target.value)
                                    }
                                >
                                    {leave.balances.map((b) => (
                                        <option key={b.type} value={b.type}>
                                            {b.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field id="ml-half" label="Length">
                                <select
                                    id="ml-half"
                                    className={selectClassName}
                                    value={f.data.half_day ? 'half' : 'full'}
                                    onChange={(e) =>
                                        f.setData(
                                            'half_day',
                                            e.target.value === 'half',
                                        )
                                    }
                                >
                                    <option value="full">Full days</option>
                                    <option value="half">
                                        Half day (one date)
                                    </option>
                                </select>
                            </Field>
                            <Field
                                id="ml-from"
                                label="From"
                                error={errors.from_on}
                            >
                                <Input
                                    id="ml-from"
                                    type="date"
                                    min={leave.earliest_from}
                                    value={f.data.from_on}
                                    onChange={(e) =>
                                        f.setData((d) => ({
                                            ...d,
                                            from_on: e.target.value,
                                            to_on:
                                                d.to_on < e.target.value
                                                    ? e.target.value
                                                    : d.to_on,
                                        }))
                                    }
                                />
                            </Field>
                            <Field id="ml-to" label="To" error={errors.to_on}>
                                <Input
                                    id="ml-to"
                                    type="date"
                                    min={f.data.from_on}
                                    disabled={f.data.half_day}
                                    value={
                                        f.data.half_day
                                            ? f.data.from_on
                                            : f.data.to_on
                                    }
                                    onChange={(e) =>
                                        f.setData('to_on', e.target.value)
                                    }
                                />
                            </Field>
                        </div>
                        <Field
                            id="ml-reason"
                            label="Reason"
                            error={errors.reason}
                        >
                            <textarea
                                id="ml-reason"
                                className={textareaClassName}
                                maxLength={300}
                                placeholder="Family function in Ambala"
                                value={f.data.reason}
                                onChange={(e) =>
                                    f.setData('reason', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="ml-cover"
                            label="Suggested cover (optional)"
                            error={errors.suggested_cover}
                        >
                            <Input
                                id="ml-cover"
                                maxLength={80}
                                placeholder="Gaurav sir can take 7-A maths"
                                value={f.data.suggested_cover}
                                onChange={(e) =>
                                    f.setData('suggested_cover', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="ml-doc"
                            label="Supporting document (optional)"
                            error={errors.attachment}
                        >
                            <Input
                                id="ml-doc"
                                ref={fileInput}
                                type="file"
                                accept="image/*,application/pdf"
                                onChange={(e) =>
                                    f.setData(
                                        'attachment',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                A photo or PDF of at most 5 MB.
                            </p>
                        </Field>

                        <div
                            className="grid gap-1 rounded-md bg-muted/50 px-3 py-2 text-sm"
                            aria-live="polite"
                        >
                            {leave.class_label !== null && (
                                <span>
                                    You’re class teacher of {leave.class_label}:
                                    the approver names who marks the register
                                    meanwhile.
                                </span>
                            )}
                            {balance?.document_after_days != null && (
                                <span className="text-amber-700 dark:text-amber-300">
                                    Attach a certificate or letter for{' '}
                                    {balance.label.toLowerCase()} of more than{' '}
                                    {balance.document_after_days}{' '}
                                    {balance.document_after_days === 1
                                        ? 'day'
                                        : 'days'}
                                    .
                                </span>
                            )}
                            {balance?.allowed_half_days != null && (
                                <span>
                                    {formatHalfDays(
                                        Math.max(
                                            0,
                                            balance.allowed_half_days -
                                                balance.used_half_days -
                                                balance.pending_half_days,
                                        ),
                                    )}{' '}
                                    of {balance.label.toLowerCase()} left. You
                                    can apply for a date up to{' '}
                                    {leave.backdate_days} days back.
                                </span>
                            )}
                        </div>

                        <InputError message={errors.leave} />
                        <Button type="submit" disabled={f.processing}>
                            <Send aria-hidden /> Apply for leave
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Goes to your coordinator or the principal.
                        </p>
                    </form>

                    <Card className="content-start gap-0 p-0">
                        <h2 className="px-5 pt-5 text-base font-semibold">
                            My requests
                        </h2>
                        {leave.requests.length === 0 ? (
                            <div className="flex flex-col items-center gap-1 px-4 py-12 text-center">
                                <CalendarCheck
                                    className="size-6 text-muted-foreground"
                                    aria-hidden
                                />
                                <p className="font-medium">
                                    No leave this session
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {leave.requests.map((item) => (
                                    <StaffLeaveRow
                                        key={item.ulid}
                                        item={item}
                                        showStaff={false}
                                        actions={
                                            item.can_cancel && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Cancel this leave request? The approver is told it is withdrawn.',
                                                            )
                                                        ) {
                                                            router.post(
                                                                cancel.url(
                                                                    item.ulid,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            )
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

MyLeave.layout = {
    breadcrumbs: [{ title: 'My leave', href: myLeave() }],
};
