import { router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import Field, { selectClassName } from '@/components/school/field';
import { Input } from '@/components/ui/input';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { approve, index, reject, store } from '@/routes/fees/refunds';
import type {
    FeeOption,
    RefundCandidate,
    RefundMethod,
    RefundRow,
    RefundsScreen,
} from '@/types';

const textareaClassName =
    'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Errors = Record<string, string>;

/** Posts a refund form and keeps the dialog open, showing the server's message, when it is refused. */
function useRefundPost(onDone: () => void) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const post = (url: string, data: Record<string, string | boolean>) => {
        setProcessing(true);
        setErrors({});

        router.post(url, data, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: (bag) => setErrors(bag as Errors),
            onFinish: () => setProcessing(false),
        });
    };

    return { processing, errors, post };
}

/** Approve: how the money reaches the family, and the principal's sign-off above the school's limit. */
export function ApproveRefundDialog({
    row,
    methods,
    limitPaise,
    onClose,
}: {
    row: RefundRow;
    methods: FeeOption[];
    limitPaise: number;
    onClose: () => void;
}) {
    const { processing, errors, post } = useRefundPost(onClose);
    const [method, setMethod] = useState<RefundMethod>('original');
    const [reference, setReference] = useState('');
    const [signedOff, setSignedOff] = useState(false);

    return (
        <ConfirmDialog
            title={`Approve refund of ${formatPaise(row.amount_paise)}`}
            description={`${row.student_name} · ${row.reason}`}
            confirmLabel="Approve refund"
            processing={processing}
            confirmDisabled={row.needs_principal && !signedOff}
            onClose={onClose}
            onConfirm={() =>
                post(approve.url(row.ulid), {
                    method,
                    reference,
                    principal_signed_off: signedOff,
                })
            }
        >
            {row.needs_principal && (
                <div
                    role="status"
                    className="flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2 text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                    <label className="flex items-start gap-2">
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={signedOff}
                            onChange={(event) =>
                                setSignedOff(event.target.checked)
                            }
                        />
                        <span>
                            Over {formatPaise(limitPaise)}: the principal has
                            signed off on this refund.
                        </span>
                    </label>
                </div>
            )}
            <Field id="refund-method" label="Refund to" error={errors.method}>
                <select
                    id="refund-method"
                    className={selectClassName}
                    value={method}
                    onChange={(event) =>
                        setMethod(event.target.value as RefundMethod)
                    }
                >
                    {methods.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.value === 'original'
                                ? row.original_label
                                : option.label}
                        </option>
                    ))}
                </select>
            </Field>
            {method === 'adjustment' && (
                <p className="text-xs text-muted-foreground">
                    The amount is credited now to this student’s unpaid
                    installments, oldest first, so their dues go down.
                </p>
            )}
            <Field
                id="refund-reference"
                label="Reference"
                error={errors.reference}
            >
                <Input
                    id="refund-reference"
                    value={reference}
                    maxLength={60}
                    placeholder="NEFT UTR or cheque number (optional)"
                    onChange={(event) => setReference(event.target.value)}
                />
            </Field>
            {(errors.fees ?? errors.principal_signed_off) && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.fees ?? errors.principal_signed_off}
                </p>
            )}
        </ConfirmDialog>
    );
}

/** Reject: the reason is shared with the parent. */
export function RejectRefundDialog({
    row,
    onClose,
}: {
    row: RefundRow;
    onClose: () => void;
}) {
    const { processing, errors, post } = useRefundPost(onClose);
    const [reason, setReason] = useState('');

    return (
        <ConfirmDialog
            title="Reject refund"
            description={`${row.student_name} · ${formatPaise(row.amount_paise)}`}
            confirmLabel="Reject"
            destructive
            processing={processing}
            onClose={onClose}
            onConfirm={() => post(reject.url(row.ulid), { reason })}
        >
            <Field
                id="reject-reason"
                label="Reason (shared with the parent)"
                error={errors.reason ?? errors.fees}
            >
                <textarea
                    id="reject-reason"
                    className={textareaClassName}
                    maxLength={300}
                    value={reason}
                    placeholder="Fees for the current term are non-refundable after the term starts."
                    onChange={(event) => setReason(event.target.value)}
                />
            </Field>
        </ConfirmDialog>
    );
}

/** New refund request: find a student, optionally pick one of their payments, enter the amount and reason. */
export function NewRefundDialog({
    screen,
    onClose,
}: {
    screen: RefundsScreen;
    onClose: () => void;
}) {
    const { processing, errors, post } = useRefundPost(onClose);
    const [search, setSearch] = useState(screen.search);
    const [student, setStudent] = useState<RefundCandidate | null>(null);
    const [payment, setPayment] = useState('');
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
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
                    {
                        tab: screen.tab === 'pending' ? undefined : screen.tab,
                        q: value === '' ? undefined : value,
                    },
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

    const chosen = student?.payments.find((item) => item.ulid === payment);
    const refundable =
        chosen?.refundable_paise ?? student?.refundable_paise ?? 0;

    return (
        <ConfirmDialog
            title="New refund request"
            description="Ask for money back for a student, for example a withdrawal or a fee charged in error."
            confirmLabel="Add request"
            processing={processing}
            confirmDisabled={student === null}
            onClose={onClose}
            onConfirm={() =>
                student !== null &&
                post(store.url(), {
                    student: student.ulid,
                    payment,
                    amount,
                    reason,
                })
            }
        >
            {student === null ? (
                <div className="grid gap-2">
                    <Field id="refund-student" label="Find a student">
                        <Input
                            id="refund-student"
                            value={search}
                            placeholder="Name or admission number"
                            autoComplete="off"
                            onChange={(event) => onSearch(event.target.value)}
                        />
                    </Field>
                    {search.trim().length >= 2 &&
                        screen.candidates.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                No student with a confirmed payment matches.
                            </p>
                        )}
                    <ul className="grid gap-1">
                        {screen.candidates.map((candidate) => (
                            <li key={candidate.ulid}>
                                <button
                                    type="button"
                                    className="w-full rounded-md border px-3 py-2 text-left hover:bg-muted/50"
                                    onClick={() => setStudent(candidate)}
                                >
                                    <span className="font-medium">
                                        {candidate.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Class {candidate.section_label} ·{' '}
                                        {candidate.admission_number} ·{' '}
                                        {formatPaise(
                                            candidate.refundable_paise,
                                        )}{' '}
                                        refundable
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <>
                    <div className="flex items-start justify-between gap-2 rounded-md bg-muted/50 px-3 py-2">
                        <span>
                            <span className="font-medium">{student.name}</span>
                            <span className="block text-xs text-muted-foreground">
                                Class {student.section_label} ·{' '}
                                {student.admission_number}
                            </span>
                        </span>
                        <button
                            type="button"
                            className="text-xs underline"
                            onClick={() => {
                                setStudent(null);
                                setPayment('');
                            }}
                        >
                            Change
                        </button>
                    </div>
                    <Field
                        id="refund-payment"
                        label="Payment"
                        error={errors.payment}
                    >
                        <select
                            id="refund-payment"
                            className={selectClassName}
                            value={payment}
                            onChange={(event) => setPayment(event.target.value)}
                        >
                            <option value="">Not tied to one payment</option>
                            {student.payments.map((item) => (
                                <option key={item.ulid} value={item.ulid}>
                                    {item.receipt_number ?? 'No receipt'} ·{' '}
                                    {item.mode_label} ·{' '}
                                    {formatCalendarDate(item.paid_on)} ·{' '}
                                    {formatPaise(item.amount_paise)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="refund-amount"
                        label={`Amount in rupees (up to ${formatPaise(refundable)})`}
                        error={errors.amount}
                    >
                        <Input
                            id="refund-amount"
                            inputMode="numeric"
                            value={amount}
                            onChange={(event) =>
                                setAmount(event.target.value.replace(/\D/g, ''))
                            }
                        />
                    </Field>
                    <Field
                        id="refund-reason"
                        label="Reason"
                        error={errors.reason}
                    >
                        <textarea
                            id="refund-reason"
                            className={textareaClassName}
                            maxLength={300}
                            value={reason}
                            placeholder="Withdrawal before term start"
                            onChange={(event) => setReason(event.target.value)}
                        />
                    </Field>
                </>
            )}
            {errors.fees && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.fees}
                </p>
            )}
        </ConfirmDialog>
    );
}
