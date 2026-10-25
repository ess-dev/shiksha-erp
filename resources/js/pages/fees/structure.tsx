import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgePercent,
    Info,
    Pencil,
    Plus,
    Split,
    Trash2,
    Type,
} from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import {
    AddConcessionDialog,
    NewGroupDialog,
    OwnFeesDialog,
    RenameGroupDialog,
} from '@/components/fees/structure-dialogs';
import { selectClassName } from '@/components/school/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { parseRupees, relativeDays } from '@/lib/fees';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { index, schedule } from '@/routes/fees/structure';
import { assign } from '@/routes/fees/structure/classes';
import { destroy, update } from '@/routes/fees/structure/groups';
import type { FeeClassRow, FeeGroup, FeeStructure } from '@/types';

type Props = { structure: FeeStructure };

type Draft = { name: string; amount: string }[];

type Dialog =
    | { kind: 'new' | 'own' | 'concession' }
    | { kind: 'rename' | 'delete'; group: FeeGroup }
    | { kind: 'save'; group: FeeGroup }
    | { kind: 'move'; row: FeeClassRow; group: FeeGroup }
    | null;

export default function FeeStructurePage({ structure }: Props) {
    const errors = usePage().props.errors as Record<string, string>;
    const canManage = structure.can_manage;
    const [drafts, setDrafts] = useState<Record<string, Draft>>({});
    const [dialog, setDialog] = useState<Dialog>(null);
    const [processing, setProcessing] = useState(false);
    const termCount = Math.max(1, structure.installments.length);
    const firstError = Object.values(errors)[0];

    const run = (
        method: 'put' | 'delete' | 'post',
        url: string,
        data: Record<string, unknown>,
        onDone?: () => void,
    ) => {
        setProcessing(true);

        router.visit(url, {
            method,
            data: data as Record<string, string>,
            preserveScroll: true,
            onSuccess: () => {
                setDialog(null);
                onDone?.();
            },
            onFinish: () => setProcessing(false),
        });
    };

    const startEdit = (group: FeeGroup) =>
        setDrafts((current) => ({
            ...current,
            [group.ulid]: group.heads.map((head) => ({
                name: head.name,
                amount: String(head.amount_paise / 100),
            })),
        }));

    const stopEdit = (ulid: string) =>
        setDrafts((current) => {
            const next = { ...current };
            delete next[ulid];

            return next;
        });

    const draftProblem = (draft: Draft): string | null => {
        if (draft.length === 0) {
            return 'Add at least one fee head.';
        }

        if (draft.some((head) => head.name.trim() === '')) {
            return 'Every fee head needs a name.';
        }

        if (draft.some((head) => parseRupees(head.amount) === null)) {
            return 'Amounts must be whole rupees, zero or more.';
        }

        const names = draft.map((head) => head.name.trim().toLowerCase());

        return new Set(names).size !== names.length
            ? 'Two fee heads have the same name.'
            : null;
    };

    const groupOf = (ulid: string | null) =>
        structure.groups.find((group) => group.ulid === ulid) ?? null;

    return (
        <>
            <Head title="Fee structure" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Fee structure · {structure.academic_year}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Which classes share a fee structure, the fee heads of
                        each group, the installment schedule and concessions.
                    </p>
                </div>

                <p
                    role="note"
                    className="flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                    Changing amounts mid-year re-prices only future installments
                    with nothing paid. Issued, paid and part-paid installments
                    keep their amounts.
                </p>

                {firstError !== undefined && (
                    <p
                        role="alert"
                        className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        {firstError}
                    </p>
                )}

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="flex flex-wrap items-start justify-between gap-3 px-4 py-4">
                        <div>
                            <h2 className="font-semibold">
                                Which classes share fees
                            </h2>
                            <p className="max-w-xl text-sm text-muted-foreground">
                                Put classes with the same fees in one group, or
                                give a class its own fees. Moving a class
                                re-prices only its future installments with
                                nothing paid.
                            </p>
                        </div>
                        {canManage && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={structure.classes.length === 0}
                                    onClick={() => setDialog({ kind: 'own' })}
                                >
                                    <Split /> Give a class its own fees
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => setDialog({ kind: 'new' })}
                                >
                                    <Plus /> New fee group
                                </Button>
                            </div>
                        )}
                    </div>

                    {structure.classes_without_group.length > 0 && (
                        <p
                            role="status"
                            className="mx-4 mb-3 flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            {structure.classes_without_group.join(', ')}{' '}
                            {structure.classes_without_group.length > 1
                                ? 'have'
                                : 'has'}{' '}
                            no fee group, so new students there aren’t billed.
                        </p>
                    )}

                    <div className="overflow-x-auto border-t">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-2.5 font-medium"
                                    >
                                        Class
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2.5 font-medium"
                                    >
                                        Fee group
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2.5 text-right font-medium"
                                    >
                                        Per term
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-2.5 text-right font-medium sm:table-cell"
                                    >
                                        Students
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {structure.classes.map((row) => {
                                    const group = groupOf(row.fee_group_ulid);

                                    return (
                                        <tr key={row.ulid}>
                                            <th
                                                scope="row"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                {row.name}
                                            </th>
                                            <td className="px-4 py-2">
                                                {canManage ? (
                                                    <select
                                                        aria-label={`Fee group for ${row.name}`}
                                                        className={`${selectClassName} max-w-64`}
                                                        value={
                                                            row.fee_group_ulid ??
                                                            ''
                                                        }
                                                        onChange={(event) => {
                                                            const next =
                                                                groupOf(
                                                                    event.target
                                                                        .value,
                                                                );

                                                            if (
                                                                next !== null &&
                                                                next.ulid !==
                                                                    row.fee_group_ulid
                                                            ) {
                                                                setDialog({
                                                                    kind: 'move',
                                                                    row,
                                                                    group: next,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        {row.fee_group_ulid ===
                                                            null && (
                                                            <option value="">
                                                                Choose a group
                                                            </option>
                                                        )}
                                                        {structure.groups.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.ulid
                                                                    }
                                                                    value={
                                                                        option.ulid
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                ) : (
                                                    (group?.name ?? '—')
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {row.term_paise === null
                                                    ? '—'
                                                    : formatPaise(
                                                          row.term_paise,
                                                      )}
                                            </td>
                                            <td className="hidden px-4 py-3 text-right sm:table-cell">
                                                {row.students}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    {structure.groups.map((group) => {
                        const draft = drafts[group.ulid];
                        const editing = draft !== undefined;
                        const term = editing
                            ? draft.reduce(
                                  (sum, head) =>
                                      sum +
                                      (parseRupees(head.amount) ?? 0) * 100,
                                  0,
                              )
                            : group.term_paise;
                        const problem = editing ? draftProblem(draft) : null;

                        return (
                            <Card key={group.ulid} className="gap-3 p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h2 className="font-semibold">
                                            {group.name}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {group.class_names.length === 0 ? (
                                                <span className="text-amber-700 dark:text-amber-400">
                                                    No classes yet
                                                </span>
                                            ) : (
                                                group.class_names.join(', ')
                                            )}{' '}
                                            · {group.students}{' '}
                                            {group.students === 1
                                                ? 'student'
                                                : 'students'}
                                        </p>
                                    </div>
                                    {canManage && !editing && (
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => startEdit(group)}
                                            >
                                                <Pencil /> Fees
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Rename ${group.name}`}
                                                onClick={() =>
                                                    setDialog({
                                                        kind: 'rename',
                                                        group,
                                                    })
                                                }
                                            >
                                                <Type />
                                            </Button>
                                            {group.can_delete && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Delete ${group.name}`}
                                                    onClick={() =>
                                                        setDialog({
                                                            kind: 'delete',
                                                            group,
                                                        })
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <table className="w-full text-sm">
                                    <tbody>
                                        {editing
                                            ? draft.map((head, position) => (
                                                  <tr key={position}>
                                                      <td className="py-1 pr-2">
                                                          <Input
                                                              aria-label="Fee head name"
                                                              value={head.name}
                                                              maxLength={60}
                                                              onChange={(
                                                                  event,
                                                              ) =>
                                                                  setDrafts(
                                                                      (
                                                                          current,
                                                                      ) => ({
                                                                          ...current,
                                                                          [group.ulid]:
                                                                              current[
                                                                                  group
                                                                                      .ulid
                                                                              ].map(
                                                                                  (
                                                                                      item,
                                                                                      i,
                                                                                  ) =>
                                                                                      i ===
                                                                                      position
                                                                                          ? {
                                                                                                ...item,
                                                                                                name: event
                                                                                                    .target
                                                                                                    .value,
                                                                                            }
                                                                                          : item,
                                                                              ),
                                                                      }),
                                                                  )
                                                              }
                                                          />
                                                      </td>
                                                      <td className="w-28 py-1 pr-1">
                                                          <Input
                                                              aria-label={`Amount per term for ${head.name || 'this head'} (₹)`}
                                                              inputMode="numeric"
                                                              value={
                                                                  head.amount
                                                              }
                                                              onChange={(
                                                                  event,
                                                              ) =>
                                                                  setDrafts(
                                                                      (
                                                                          current,
                                                                      ) => ({
                                                                          ...current,
                                                                          [group.ulid]:
                                                                              current[
                                                                                  group
                                                                                      .ulid
                                                                              ].map(
                                                                                  (
                                                                                      item,
                                                                                      i,
                                                                                  ) =>
                                                                                      i ===
                                                                                      position
                                                                                          ? {
                                                                                                ...item,
                                                                                                amount: event
                                                                                                    .target
                                                                                                    .value,
                                                                                            }
                                                                                          : item,
                                                                              ),
                                                                      }),
                                                                  )
                                                              }
                                                          />
                                                      </td>
                                                      <td className="w-8 py-1">
                                                          <Button
                                                              variant="ghost"
                                                              size="icon"
                                                              aria-label={`Remove ${head.name || 'this fee head'}`}
                                                              onClick={() =>
                                                                  setDrafts(
                                                                      (
                                                                          current,
                                                                      ) => ({
                                                                          ...current,
                                                                          [group.ulid]:
                                                                              current[
                                                                                  group
                                                                                      .ulid
                                                                              ].filter(
                                                                                  (
                                                                                      _,
                                                                                      i,
                                                                                  ) =>
                                                                                      i !==
                                                                                      position,
                                                                              ),
                                                                      }),
                                                                  )
                                                              }
                                                          >
                                                              <Trash2 />
                                                          </Button>
                                                      </td>
                                                  </tr>
                                              ))
                                            : group.heads.map((head) => (
                                                  <tr key={head.name}>
                                                      <td className="py-1">
                                                          {head.name}
                                                      </td>
                                                      <td className="py-1 text-right">
                                                          {formatPaise(
                                                              head.amount_paise,
                                                          )}
                                                      </td>
                                                  </tr>
                                              ))}
                                        <tr className="border-t font-semibold">
                                            <td className="pt-2">Per term</td>
                                            <td
                                                className="pt-2 text-right"
                                                colSpan={editing ? 2 : 1}
                                            >
                                                {formatPaise(term)}
                                            </td>
                                            {editing && <td />}
                                        </tr>
                                        <tr className="text-muted-foreground">
                                            <td>
                                                Per year ({termCount} terms)
                                            </td>
                                            <td
                                                className="text-right"
                                                colSpan={editing ? 2 : 1}
                                            >
                                                {formatPaise(term * termCount)}
                                            </td>
                                            {editing && <td />}
                                        </tr>
                                    </tbody>
                                </table>

                                {editing && (
                                    <>
                                        {problem !== null && (
                                            <p
                                                role="alert"
                                                className="text-sm text-red-700 dark:text-red-400"
                                            >
                                                {problem}
                                            </p>
                                        )}
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [group.ulid]: [
                                                            ...current[
                                                                group.ulid
                                                            ],
                                                            {
                                                                name: '',
                                                                amount: '0',
                                                            },
                                                        ],
                                                    }))
                                                }
                                            >
                                                <Plus /> Add fee head
                                            </Button>
                                            <span className="flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        stopEdit(group.ulid)
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    disabled={problem !== null}
                                                    onClick={() =>
                                                        setDialog({
                                                            kind: 'save',
                                                            group,
                                                        })
                                                    }
                                                >
                                                    Save
                                                </Button>
                                            </span>
                                        </div>
                                    </>
                                )}
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="gap-0 overflow-hidden p-0">
                        <div className="px-4 py-4">
                            <h2 className="font-semibold">
                                Installment schedule
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Same for every class group
                            </p>
                        </div>
                        {structure.has_schedule ? (
                            <table className="w-full border-t text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Installment
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Due date
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 font-medium"
                                        >
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {structure.installments.map((item) => (
                                        <tr key={item.ulid}>
                                            <th
                                                scope="row"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                {item.label}
                                            </th>
                                            <td className="px-4 py-3">
                                                {formatCalendarDate(
                                                    item.due_on,
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {relativeDays(
                                                        structure.today,
                                                        item.due_on,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="secondary"
                                                    className="rounded-full"
                                                >
                                                    {item.is_billed
                                                        ? 'Billed'
                                                        : 'Upcoming'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="grid gap-3 border-t px-4 py-5 text-sm">
                                <p className="text-muted-foreground">
                                    There is no installment schedule for{' '}
                                    {structure.academic_year} yet, so no student
                                    can be billed. A new year starts with four
                                    terms due on the 15th of each term’s first
                                    month (a starting point; confirm it with the
                                    school).
                                </p>
                                {canManage && (
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                        onClick={() =>
                                            run('post', schedule.url(), {})
                                        }
                                    >
                                        {processing && <Spinner />}
                                        Create the installment schedule
                                    </Button>
                                )}
                            </div>
                        )}
                        <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                            Students admitted mid-year are billed only for terms
                            that haven’t ended. A one-time admission fee is not
                            built yet.
                        </p>
                    </Card>

                    <Card className="gap-0 overflow-hidden p-0">
                        <div className="flex items-start justify-between gap-3 px-4 py-4">
                            <div>
                                <h2 className="font-semibold">
                                    Concessions and scholarships
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Discounts that reduce a student’s fee
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={structure.head_names.length === 0}
                                    onClick={() =>
                                        setDialog({ kind: 'concession' })
                                    }
                                >
                                    <Plus /> Add
                                </Button>
                            )}
                        </div>
                        <ul className="divide-y border-t">
                            {structure.concessions.length === 0 ? (
                                <li className="px-4 py-5 text-sm text-muted-foreground">
                                    No concessions yet.
                                </li>
                            ) : (
                                structure.concessions.map((item) => (
                                    <li
                                        key={item.ulid}
                                        className="flex items-start gap-3 px-4 py-3"
                                    >
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <BadgePercent
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {item.description}
                                                {item.needs_approval &&
                                                    ' · needs the principal’s approval'}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {item.students}{' '}
                                            {item.students === 1
                                                ? 'student'
                                                : 'students'}
                                        </span>
                                    </li>
                                ))
                            )}
                        </ul>
                        <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                            Assigning a concession to a student from their
                            profile is not built yet; the sibling concession is
                            applied automatically.
                        </p>
                    </Card>
                </div>
            </div>

            {dialog?.kind === 'new' && (
                <NewGroupDialog
                    structure={structure}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'own' && (
                <OwnFeesDialog
                    structure={structure}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'rename' && (
                <RenameGroupDialog
                    group={dialog.group}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'concession' && (
                <AddConcessionDialog
                    headNames={structure.head_names}
                    onClose={() => setDialog(null)}
                />
            )}

            {dialog?.kind === 'delete' && (
                <ConfirmDialog
                    title={`Delete ${dialog.group.name}?`}
                    description="It has no classes and was never billed."
                    confirmLabel="Delete"
                    destructive
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        run('delete', destroy.url(dialog.group.ulid), {})
                    }
                />
            )}

            {dialog?.kind === 'save' && (
                <ConfirmDialog
                    title={`Save ${dialog.group.name} fees?`}
                    description={`Per-term total goes from ${formatPaise(dialog.group.term_paise)} to ${formatPaise(
                        (drafts[dialog.group.ulid] ?? []).reduce(
                            (sum, head) =>
                                sum + (parseRupees(head.amount) ?? 0) * 100,
                            0,
                        ),
                    )}. Installments due later with nothing paid are re-priced; billed, paid and part-paid ones keep their amounts. This is recorded in the audit log.`}
                    confirmLabel="Save fees"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        run(
                            'put',
                            update.url(dialog.group.ulid),
                            {
                                heads: (drafts[dialog.group.ulid] ?? []).map(
                                    (head) => ({
                                        name: head.name.trim(),
                                        amount: parseRupees(head.amount) ?? 0,
                                    }),
                                ),
                            },
                            () => stopEdit(dialog.group.ulid),
                        )
                    }
                />
            )}

            {dialog?.kind === 'move' && (
                <ConfirmDialog
                    title={`Move ${dialog.row.name} to ${dialog.group.name}?`}
                    description={`Per term goes from ${dialog.row.term_paise === null ? 'nothing' : formatPaise(dialog.row.term_paise)} to ${formatPaise(dialog.group.term_paise)}. Future installments with nothing paid are re-priced; paid and part-paid ones keep their amounts. Students who were never billed are billed now. Recorded in the audit log.`}
                    confirmLabel="Move class"
                    processing={processing}
                    onClose={() => setDialog(null)}
                    onConfirm={() =>
                        run('put', assign.url(dialog.row.ulid), {
                            group: dialog.group.ulid,
                        })
                    }
                />
            )}
        </>
    );
}

FeeStructurePage.layout = {
    breadcrumbs: [{ title: 'Fee structure', href: index() }],
};
