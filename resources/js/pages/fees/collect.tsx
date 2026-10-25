import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { InvoiceStatusBadge } from '@/components/fees/status-badges';
import Field from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { parseRupees } from '@/lib/fees';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { index, store } from '@/routes/fees/collect';
import { index as structure } from '@/routes/fees/structure';
import type { CollectInstallment, CollectScreen, PaymentMode } from '@/types';

type Props = { screen: CollectScreen };

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `k${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

/** Installments worth collecting by default: late, due soon or already part paid. */
function preselected(
    installments: CollectInstallment[],
): Record<string, boolean> {
    return Object.fromEntries(
        installments
            .filter((item) => item.status !== 'upcoming')
            .map((item) => [item.ulid, true]),
    );
}

const MODE_HINT: Record<string, string> = {
    cash: 'Cash',
    upi: 'UPI (QR)',
    card: 'Card (POS)',
    cheque: 'Cheque',
    bank_transfer: 'Bank transfer',
};

export default function Collect({ screen }: Props) {
    const student = screen.student;
    const [search, setSearch] = useState(screen.search);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const onSearch = (value: string) => {
        setSearch(value);

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(
            () =>
                router.get(
                    index.url(),
                    { q: value === '' ? undefined : value },
                    {
                        only: ['screen'],
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            300,
        );
    };

    return (
        <>
            <Head title="Collect fee" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Collect fee
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Take a payment at the counter and issue the receipt.
                    </p>
                </div>

                {!screen.has_schedule && (
                    <p
                        role="status"
                        className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                    >
                        There is no installment schedule yet, so nobody has dues
                        to collect. Set one up on{' '}
                        <Link href={structure()} className="underline">
                            Fee structure
                        </Link>
                        .
                    </p>
                )}

                <Card className="gap-3 p-4">
                    <div className="grid gap-1.5">
                        <label
                            htmlFor="collect-search"
                            className="text-sm font-medium"
                        >
                            Find a student
                        </label>
                        <Input
                            id="collect-search"
                            value={search}
                            autoComplete="off"
                            placeholder="Name, admission number or class (for example 5-B)"
                            onChange={(event) => onSearch(event.target.value)}
                        />
                    </div>

                    {search.trim().length >= 2 && (
                        <ul
                            className="divide-y rounded-md border"
                            aria-label="Students found"
                        >
                            {screen.results.length === 0 ? (
                                <li className="p-4 text-center text-sm text-muted-foreground">
                                    No students found. Try another name or
                                    admission number.
                                </li>
                            ) : (
                                screen.results.map((result) => (
                                    <li key={result.ulid}>
                                        <Link
                                            href={index.url({
                                                query: { student: result.ulid },
                                            })}
                                            className="flex items-center gap-3 px-3 py-2 hover:bg-muted/50"
                                        >
                                            <InitialsBadge name={result.name} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-medium">
                                                    {result.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {result.section_label} ·{' '}
                                                    {result.admission_number} ·{' '}
                                                    {result.campus_name}
                                                </span>
                                            </span>
                                            <span
                                                className={
                                                    result.due_paise > 0
                                                        ? 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900'
                                                        : 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900'
                                                }
                                            >
                                                {result.due_paise > 0
                                                    ? `${formatPaise(result.due_paise)} due`
                                                    : 'No dues'}
                                            </span>
                                        </Link>
                                    </li>
                                ))
                            )}
                        </ul>
                    )}
                </Card>

                {student !== null && (
                    <CollectForm
                        key={student.ulid}
                        student={student}
                        modes={screen.modes}
                    />
                )}
            </div>
        </>
    );
}

function CollectForm({
    student,
    modes,
}: {
    student: NonNullable<CollectScreen['student']>;
    modes: CollectScreen['modes'];
}) {
    const [selected, setSelected] = useState<Record<string, boolean>>(() =>
        preselected(student.installments),
    );
    const key = useRef(newKey());
    const form = useForm({
        mode: 'cash' as PaymentMode,
        amount: '',
        reference: '',
        cheque_number: '',
        bank: '',
        cheque_date: '',
        received_from: student.guardian_name ?? '',
        note: '',
    });
    const errors = form.errors as Record<string, string>;

    const chosen = student.installments.filter((item) => selected[item.ulid]);
    const totalPaise = chosen.reduce(
        (sum, item) => sum + item.balance_paise,
        0,
    );
    const wholeRupees = totalPaise % 100 === 0;
    const typed =
        form.data.amount.trim() === '' ? null : parseRupees(form.data.amount);
    const amountPaise = typed === null ? totalPaise : typed * 100;
    const isCheque = form.data.mode === 'cheque';
    const needsReference = form.data.mode !== 'cash' && !isCheque;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            student: student.ulid,
            invoices: chosen.map((item) => item.ulid),
            idempotency_key: key.current,
            mode: data.mode,
            amount:
                typed === null || typed * 100 === totalPaise
                    ? undefined
                    : typed,
            reference: needsReference ? data.reference : undefined,
            cheque_number: isCheque ? data.cheque_number : undefined,
            bank: isCheque ? data.bank : undefined,
            cheque_date: isCheque ? data.cheque_date : undefined,
            received_from: data.received_from,
            note: data.note,
        }));

        form.post(store.url());
    };

    const pendingNotes = student.installments.filter(
        (item) => item.pending_note !== null,
    );

    return (
        <div className="grid gap-4 lg:grid-cols-5">
            <div className="grid content-start gap-4 lg:col-span-3">
                <Card className="gap-3 p-4">
                    <div className="flex items-center gap-3">
                        <InitialsBadge
                            name={student.name}
                            className="size-11"
                        />
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">{student.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {student.section_label} ·{' '}
                                {student.admission_number} ·{' '}
                                {student.campus_name}
                            </p>
                            {student.guardian_name !== null && (
                                <p className="text-sm text-muted-foreground">
                                    {student.guardian_name}
                                    {student.guardian_relation !== null &&
                                        ` (${student.guardian_relation})`}
                                </p>
                            )}
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={index()}>Change</Link>
                        </Button>
                    </div>
                </Card>

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="border-b px-4 py-3">
                        <h2 className="font-semibold">Installments</h2>
                        <p className="text-sm text-muted-foreground">
                            Tick what is being paid. Money goes to the oldest
                            due date first.
                        </p>
                    </div>
                    {student.installments.length === 0 ? (
                        <p className="p-6 text-center text-sm text-muted-foreground">
                            No dues. Everything is paid for this academic year.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {student.installments.map((item) => (
                                <li
                                    key={item.ulid}
                                    className="flex items-start gap-3 px-4 py-3"
                                >
                                    <Checkbox
                                        id={`inst-${item.ulid}`}
                                        className="mt-1"
                                        checked={selected[item.ulid] === true}
                                        onCheckedChange={(value) =>
                                            setSelected((current) => ({
                                                ...current,
                                                [item.ulid]: value === true,
                                            }))
                                        }
                                    />
                                    <div className="min-w-0 flex-1">
                                        <label
                                            htmlFor={`inst-${item.ulid}`}
                                            className="font-medium"
                                        >
                                            {item.label}
                                        </label>
                                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                            Due{' '}
                                            {formatCalendarDate(item.due_on)}
                                            <InvoiceStatusBadge
                                                status={item.status}
                                            />
                                            {item.concession_note !== null && (
                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                    {item.concession_note}
                                                </span>
                                            )}
                                        </p>
                                        {item.pending_note !== null && (
                                            <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                                {item.pending_note}
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right font-semibold">
                                        {formatPaise(item.balance_paise)}
                                        {item.paid_paise > 0 && (
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                of {formatPaise(item.net_paise)}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    {pendingNotes.length > 0 && (
                        <p className="sr-only" role="status">
                            Some installments have a payment waiting to be
                            confirmed.
                        </p>
                    )}
                </Card>
            </div>

            {student.installments.length > 0 && (
                <form onSubmit={submit} className="lg:col-span-2">
                    <Card className="gap-4 p-4">
                        <div>
                            <h2 className="font-semibold">Payment</h2>
                            <p className="text-sm text-muted-foreground">
                                {chosen.length === 0
                                    ? 'Select at least one installment'
                                    : `${chosen.length} ${chosen.length === 1 ? 'installment' : 'installments'} selected · ${formatPaise(totalPaise)}`}
                            </p>
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                How was it paid?
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {modes.map((mode) => (
                                    <label
                                        key={mode.value}
                                        className={`cursor-pointer rounded-md border px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50`}
                                    >
                                        <input
                                            type="radio"
                                            name="mode"
                                            value={mode.value}
                                            className="sr-only"
                                            checked={
                                                form.data.mode === mode.value
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'mode',
                                                    mode.value as PaymentMode,
                                                )
                                            }
                                        />
                                        {MODE_HINT[mode.value] ?? mode.label}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <Field
                            id="collect-amount"
                            label="Amount (₹)"
                            error={errors.amount}
                        >
                            <Input
                                id="collect-amount"
                                inputMode="numeric"
                                value={form.data.amount}
                                placeholder={
                                    wholeRupees
                                        ? String(totalPaise / 100)
                                        : formatPaise(totalPaise)
                                }
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                            <span className="text-xs text-muted-foreground">
                                {typed === null &&
                                form.data.amount.trim() !== ''
                                    ? 'Enter whole rupees.'
                                    : amountPaise < totalPaise
                                      ? `Part payment: ${formatPaise(totalPaise - amountPaise)} stays due.`
                                      : 'Leave empty to collect everything selected.'}
                            </span>
                        </Field>

                        {needsReference && (
                            <Field
                                id="collect-reference"
                                label="Transaction reference"
                                error={errors.reference}
                            >
                                <Input
                                    id="collect-reference"
                                    value={form.data.reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'reference',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}

                        {isCheque && (
                            <div className="grid gap-3">
                                <Field
                                    id="collect-cheque"
                                    label="Cheque number"
                                    error={errors.cheque_number}
                                >
                                    <Input
                                        id="collect-cheque"
                                        value={form.data.cheque_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'cheque_number',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="collect-bank"
                                    label="Bank"
                                    error={errors.bank}
                                >
                                    <Input
                                        id="collect-bank"
                                        value={form.data.bank}
                                        onChange={(event) =>
                                            form.setData(
                                                'bank',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="collect-cheque-date"
                                    label="Cheque date"
                                    error={errors.cheque_date}
                                >
                                    <Input
                                        id="collect-cheque-date"
                                        type="date"
                                        value={form.data.cheque_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'cheque_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <p className="text-xs text-muted-foreground">
                                    A cheque stays pending until it clears. The
                                    receipt is issued then.
                                </p>
                            </div>
                        )}

                        <Field
                            id="collect-from"
                            label="Received from"
                            error={errors.received_from}
                        >
                            <Input
                                id="collect-from"
                                value={form.data.received_from}
                                onChange={(event) =>
                                    form.setData(
                                        'received_from',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            id="collect-note"
                            label="Remarks (optional)"
                            error={errors.note}
                        >
                            <Input
                                id="collect-note"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                            />
                        </Field>

                        <InputError message={errors.fees} />
                        <InputError message={errors.invoices} />

                        <Button
                            type="submit"
                            disabled={form.processing || chosen.length === 0}
                            className="w-full"
                        >
                            {form.processing && <Spinner />}
                            {isCheque ? 'Record cheque' : 'Collect'}{' '}
                            {chosen.length > 0 && formatPaise(amountPaise)}
                        </Button>
                    </Card>
                </form>
            )}
        </div>
    );
}

Collect.layout = {
    breadcrumbs: [{ title: 'Collect fee', href: index() }],
};
