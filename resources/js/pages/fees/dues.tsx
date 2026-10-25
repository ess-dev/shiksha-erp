import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useRef, useState } from 'react';
import { InvoiceStatusBadge } from '@/components/fees/status-badges';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import { selectClassName } from '@/components/school/field';
import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { relativeDays, splitInstallment } from '@/lib/fees';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as collect } from '@/routes/fees/collect';
import { exportMethod, index, remind } from '@/routes/fees/dues';
import { index as structure } from '@/routes/fees/structure';
import type { DueRow, DuesFilters, DuesScreen } from '@/types';

type Props = { screen: DuesScreen; filters: DuesFilters };

type Visit = Partial<DuesFilters> & { page?: number };

export default function Dues({ screen, filters }: Props) {
    const canRemind = useCan('fees.remind');
    const canManage = useCan('fee_structures.manage');
    const [search, setSearch] = useState(filters.q);
    const [checked, setChecked] = useState<Record<string, DueRow>>({});
    const [confirming, setConfirming] = useState<DueRow[] | null>(null);
    const [processing, setProcessing] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const query = (changes: Visit) => {
        const next = { ...filters, ...changes };

        return {
            q: next.q === '' ? undefined : next.q,
            class: next.class ?? undefined,
            installment: next.installment ?? undefined,
            status: next.status === 'open' ? undefined : next.status,
            sort: next.sort === 'due' ? undefined : next.sort,
            page: changes.page,
        };
    };

    const visit = (changes: Visit) => {
        if (changes.page === undefined) {
            setChecked({});
        }

        router.get(index.url(), query(changes), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const onSearch = (value: string) => {
        setSearch(value);

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => visit({ q: value }), 300);
    };

    const rows = screen.rows;
    const selectable = rows.filter(
        (row) => row.can_remind && !row.reminded_today,
    );
    const selected = Object.values(checked);
    const selectedStudents = new Set(selected.map((row) => row.student_ulid))
        .size;
    const allOnPage =
        selectable.length > 0 && selectable.every((row) => checked[row.ulid]);

    const toggle = (row: DueRow, on: boolean) => {
        setChecked((current) => {
            const next = { ...current };

            if (on) {
                next[row.ulid] = row;
            } else {
                delete next[row.ulid];
            }

            return next;
        });
    };

    const togglePage = (on: boolean) => {
        setChecked((current) => {
            const next = { ...current };

            for (const row of selectable) {
                if (on) {
                    next[row.ulid] = row;
                } else {
                    delete next[row.ulid];
                }
            }

            return next;
        });
    };

    const send = (targets: DueRow[]) => {
        setProcessing(true);

        router.post(
            remind.url(),
            { invoices: targets.map((row) => row.ulid) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setChecked({});
                    setConfirming(null);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const families = (targets: DueRow[]): number =>
        new Set(targets.map((row) => row.student_ulid)).size;

    return (
        <>
            <Head title="Fee dues" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Fee dues
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every unpaid installment. Filter, remind guardians
                            or collect at the counter.
                        </p>
                    </div>
                    {screen.can_export && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportMethod.url({ query: query({}) })}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Summary
                        label="Installments"
                        value={screen.summary.installments.toLocaleString(
                            'en-IN',
                        )}
                    />
                    <Summary
                        label="Students"
                        value={screen.summary.students.toLocaleString('en-IN')}
                    />
                    <Summary
                        label="Balance due"
                        value={formatPaise(screen.summary.balance_paise)}
                    />
                    <Summary
                        label="Of which overdue"
                        value={formatPaise(screen.summary.overdue_paise)}
                        danger={screen.summary.overdue_paise > 0}
                    />
                </div>

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-5">
                        <FilterField id="due-search" label="Search">
                            <Input
                                id="due-search"
                                value={search}
                                onChange={(event) =>
                                    onSearch(event.target.value)
                                }
                                placeholder="Name or admission number"
                            />
                        </FilterField>
                        <FilterField id="due-class" label="Class">
                            <select
                                id="due-class"
                                className={selectClassName}
                                value={filters.class ?? ''}
                                onChange={(event) =>
                                    visit({
                                        class: event.target.value || null,
                                    })
                                }
                            >
                                <option value="">All classes</option>
                                {screen.sections.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                        <FilterField id="due-installment" label="Installment">
                            <select
                                id="due-installment"
                                className={selectClassName}
                                value={filters.installment ?? ''}
                                onChange={(event) =>
                                    visit({
                                        installment: event.target.value || null,
                                    })
                                }
                            >
                                <option value="">All installments</option>
                                {screen.installments.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                        <FilterField id="due-status" label="Status">
                            <select
                                id="due-status"
                                className={selectClassName}
                                value={filters.status}
                                onChange={(event) =>
                                    visit({
                                        status: event.target
                                            .value as DuesFilters['status'],
                                    })
                                }
                            >
                                <option value="open">All unpaid</option>
                                <option value="overdue">Overdue</option>
                                <option value="due">Due soon</option>
                                <option value="partial">Partly paid</option>
                                <option value="upcoming">Upcoming</option>
                            </select>
                        </FilterField>
                        <FilterField id="due-sort" label="Sort by">
                            <select
                                id="due-sort"
                                className={selectClassName}
                                value={filters.sort}
                                onChange={(event) =>
                                    visit({
                                        sort: event.target
                                            .value as DuesFilters['sort'],
                                    })
                                }
                            >
                                <option value="due">Due date</option>
                                <option value="balance">Highest balance</option>
                                <option value="class">Class</option>
                            </select>
                        </FilterField>
                    </div>

                    {selected.length > 0 && (
                        <div
                            role="status"
                            className="flex flex-wrap items-center justify-between gap-2 border-b bg-primary/5 px-4 py-2 text-sm"
                        >
                            <span>
                                {selected.length}{' '}
                                {selected.length === 1
                                    ? 'installment'
                                    : 'installments'}{' '}
                                selected · {selectedStudents}{' '}
                                {selectedStudents === 1
                                    ? 'student'
                                    : 'students'}
                            </span>
                            <span className="flex gap-2">
                                <Button
                                    size="sm"
                                    onClick={() => setConfirming(selected)}
                                >
                                    Send reminders
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setChecked({})}
                                >
                                    Clear
                                </Button>
                            </span>
                        </div>
                    )}

                    {rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="font-medium">
                                {screen.has_schedule
                                    ? 'No dues match these filters'
                                    : 'No installment schedule yet'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {screen.has_schedule ? (
                                    'Try another class or status.'
                                ) : canManage ? (
                                    <>
                                        Create the schedule on{' '}
                                        <Link
                                            href={structure()}
                                            className="text-primary hover:underline"
                                        >
                                            Fee structure
                                        </Link>{' '}
                                        and students are billed.
                                    </>
                                ) : (
                                    'Ask the school office to set up the fee structure.'
                                )}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="w-10 px-4 py-2.5">
                                            {canRemind && (
                                                <Checkbox
                                                    aria-label="Select all on this page"
                                                    checked={allOnPage}
                                                    disabled={
                                                        selectable.length === 0
                                                    }
                                                    onCheckedChange={(value) =>
                                                        togglePage(
                                                            value === true,
                                                        )
                                                    }
                                                />
                                            )}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Student
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 font-medium md:table-cell"
                                        >
                                            Class
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Installment
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 font-medium md:table-cell"
                                        >
                                            Due date
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 text-right font-medium lg:table-cell"
                                        >
                                            Paid
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 text-right font-medium"
                                        >
                                            Balance
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-2.5 font-medium lg:table-cell"
                                        >
                                            Status
                                        </th>
                                        <th scope="col" className="px-4 py-2.5">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.map((row) => (
                                        <DueTableRow
                                            key={row.ulid}
                                            row={row}
                                            today={screen.today}
                                            canRemind={canRemind}
                                            checked={
                                                checked[row.ulid] !== undefined
                                            }
                                            onToggle={(on) => toggle(row, on)}
                                            onRemind={() =>
                                                setConfirming([row])
                                            }
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            Showing {screen.from}–{screen.to} of {screen.total}
                        </span>
                        <span className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={screen.current_page <= 1}
                                onClick={() =>
                                    visit({ page: screen.current_page - 1 })
                                }
                            >
                                Previous
                            </Button>
                            <span>
                                Page {screen.current_page} of {screen.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    screen.current_page >= screen.last_page
                                }
                                onClick={() =>
                                    visit({ page: screen.current_page + 1 })
                                }
                            >
                                Next
                            </Button>
                        </span>
                    </div>
                </Card>
            </div>

            {confirming !== null && (
                <ConfirmDialog
                    title="Send fee reminders?"
                    description={`Guardians of ${families(confirming)} ${families(confirming) === 1 ? 'student' : 'students'} get a reminder with the amount due. No SMS provider is set up yet, so for now the reminder is only recorded.`}
                    confirmLabel={`Send to ${families(confirming)} ${families(confirming) === 1 ? 'family' : 'families'}`}
                    processing={processing}
                    onClose={() => setConfirming(null)}
                    onConfirm={() => send(confirming)}
                />
            )}
        </>
    );
}

function Summary({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <Card className="gap-1 px-4 py-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <strong
                className={cn(
                    'text-xl font-semibold',
                    danger && 'text-red-700 dark:text-red-400',
                )}
            >
                {value}
            </strong>
        </Card>
    );
}

function FilterField({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </label>
            {children}
        </div>
    );
}

function DueTableRow({
    row,
    today,
    canRemind,
    checked,
    onToggle,
    onRemind,
}: {
    row: DueRow;
    today: string;
    canRemind: boolean;
    checked: boolean;
    onToggle: (on: boolean) => void;
    onRemind: () => void;
}) {
    const [term, months] = splitInstallment(row.installment_label);
    const overdue =
        row.status === 'overdue' || row.status === 'partial_overdue';

    return (
        <tr className="hover:bg-muted/30">
            <td className="px-4 py-3">
                {canRemind && row.can_remind && (
                    <Checkbox
                        aria-label={`Select ${row.student_name}, ${row.installment_label}`}
                        checked={checked}
                        disabled={row.reminded_today}
                        onCheckedChange={(value) => onToggle(value === true)}
                    />
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-3">
                    <InitialsBadge name={row.student_name} />
                    <div>
                        <p className="font-medium">{row.student_name}</p>
                        <p className="text-xs text-muted-foreground">
                            {row.admission_number}
                            <span className="md:hidden">
                                {' '}
                                · {row.section_label}
                            </span>
                        </p>
                    </div>
                </div>
            </td>
            <td className="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                {row.section_label}
                <span className="block text-xs text-muted-foreground">
                    {row.campus_name}
                </span>
            </td>
            <td className="px-4 py-3 whitespace-nowrap">
                {term}
                <span className="block text-xs text-muted-foreground">
                    {months}
                </span>
            </td>
            <td className="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                {formatCalendarDate(row.due_on)}
                <span
                    className={cn(
                        'block text-xs',
                        overdue
                            ? 'text-red-700 dark:text-red-400'
                            : 'text-muted-foreground',
                    )}
                >
                    {relativeDays(today, row.due_on)}
                </span>
            </td>
            <td className="hidden px-4 py-3 text-right lg:table-cell">
                {row.paid_paise > 0 ? formatPaise(row.paid_paise) : '—'}
            </td>
            <td className="px-4 py-3 text-right font-semibold">
                {formatPaise(row.balance_paise)}
            </td>
            <td className="hidden px-4 py-3 lg:table-cell">
                <InvoiceStatusBadge status={row.status} />
                {row.payment_pending && (
                    <span className="mt-1 block text-xs text-amber-700 dark:text-amber-400">
                        Payment pending
                    </span>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex justify-end gap-2">
                    {row.can_remind && (
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={row.reminded_today}
                            onClick={onRemind}
                        >
                            {row.reminded_today ? 'Reminded' : 'Remind'}
                        </Button>
                    )}
                    {row.can_collect && (
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={collect.url({
                                    query: { student: row.student_ulid },
                                })}
                            >
                                Collect
                            </Link>
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}

Dues.layout = {
    breadcrumbs: [{ title: 'Fee dues', href: index() }],
};
