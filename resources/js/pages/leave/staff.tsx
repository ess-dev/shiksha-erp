import { Head, Link, router } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import StaffLeaveRow from '@/components/leave/staff-leave-row';
import { selectClassName } from '@/components/school/field';
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
import { Input } from '@/components/ui/input';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import { decide, index } from '@/routes/staff-leave';
import type {
    StaffLeaveRequest,
    StaffLeaveRequestsScreen,
    StaffOption,
} from '@/types';

type Decision = 'approve' | 'reject';

const textareaClassName =
    'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function DecisionDialog({
    item,
    decision,
    coverOptions,
    onClose,
}: {
    item: StaffLeaveRequest;
    decision: Decision;
    coverOptions: StaffOption[];
    onClose: () => void;
}) {
    const options = coverOptions.filter(
        (option) => option.ulid !== item.staff_ulid,
    );
    const [note, setNote] = useState('');
    const [cover, setCover] = useState(options[0]?.ulid ?? '');
    const [error, setError] = useState<string>();
    const [busy, setBusy] = useState(false);
    const needsCover = decision === 'approve' && item.section_label !== null;

    const send = () => {
        router.post(
            decide.url(item.ulid),
            { decision, note, cover: needsCover ? cover : '' },
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
                        {decision === 'approve'
                            ? `Approve leave for ${item.staff_name}`
                            : 'Reject leave'}
                    </DialogTitle>
                    <DialogDescription>
                        {item.type_label}, {item.from_on}
                        {item.to_on !== item.from_on && ` to ${item.to_on}`}.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    {needsCover && (
                        <div className="grid gap-2">
                            <label
                                htmlFor="sl-cover"
                                className="text-sm font-medium"
                            >
                                Who marks {item.section_label}’s register
                                meanwhile?
                            </label>
                            <select
                                id="sl-cover"
                                className={selectClassName}
                                value={cover}
                                onChange={(e) => setCover(e.target.value)}
                            >
                                {options.map((option) => (
                                    <option
                                        key={option.ulid}
                                        value={option.ulid}
                                    >
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                They get temporary access to the register for
                                these days.
                            </p>
                        </div>
                    )}
                    {decision === 'reject' && (
                        <div className="grid gap-2">
                            <label
                                htmlFor="sl-note"
                                className="text-sm font-medium"
                            >
                                Note to the staff member
                            </label>
                            <textarea
                                id="sl-note"
                                className={textareaClassName}
                                maxLength={200}
                                value={note}
                                placeholder="PTM is on 24 Oct; please apply for another date."
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                    )}
                    <InputError message={error} />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={
                            decision === 'reject' ? 'destructive' : 'default'
                        }
                        disabled={busy}
                        onClick={send}
                    >
                        {decision === 'approve' ? 'Approve' : 'Reject'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RequestRow({
    item,
    coverOptions,
}: {
    item: StaffLeaveRequest;
    coverOptions: StaffOption[];
}) {
    const [decision, setDecision] = useState<Decision | null>(null);

    return (
        <>
            <StaffLeaveRow
                item={item}
                showStaff
                actions={
                    item.can_decide && (
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setDecision('reject')}
                            >
                                Reject
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setDecision('approve')}
                            >
                                Approve
                            </Button>
                        </div>
                    )
                }
            />
            {decision !== null && (
                <DecisionDialog
                    item={item}
                    decision={decision}
                    coverOptions={coverOptions}
                    onClose={() => setDecision(null)}
                />
            )}
        </>
    );
}

function Directory({ screen }: { screen: StaffLeaveRequestsScreen }) {
    const [query, setQuery] = useState('');
    const rows = screen.directory.filter((row) =>
        `${row.name} ${row.roles.join(' ')} ${row.class_label ?? ''}`
            .toLowerCase()
            .includes(query.toLowerCase()),
    );

    return (
        <Card className="gap-0 p-0">
            <div className="p-4">
                <label htmlFor="sd-search" className="sr-only">
                    Search staff
                </label>
                <Input
                    id="sd-search"
                    placeholder="Search by name, role or class"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </div>
            {rows.length === 0 ? (
                <p className="px-4 pb-8 text-center text-sm text-muted-foreground">
                    No staff match.
                </p>
            ) : (
                <ul className="divide-y border-t">
                    {rows.map((row) => (
                        <li
                            key={row.ulid}
                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                {initials(row.name)}
                            </span>
                            <div className="min-w-48 grow">
                                <strong>{row.name}</strong>
                                <p className="text-sm text-muted-foreground">
                                    {row.roles.length > 0
                                        ? row.roles.join(', ')
                                        : 'No role yet'}
                                    {row.class_label !== null &&
                                        ` · Class teacher, ${row.class_label}`}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    !row.is_active
                                        ? 'bg-muted text-muted-foreground'
                                        : row.on_leave_today
                                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                                          : 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
                                )}
                            >
                                {!row.is_active
                                    ? 'Inactive'
                                    : row.on_leave_today
                                      ? 'On leave'
                                      : 'In school'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

export default function StaffAndLeave({
    screen,
}: {
    screen: StaffLeaveRequestsScreen;
}) {
    const tabs = [
        ...(screen.can_view_leave
            ? [
                  {
                      key: 'leave',
                      label: `Leave requests${screen.pending_count > 0 ? ` (${screen.pending_count})` : ''}`,
                  },
              ]
            : []),
        ...(screen.can_view_directory
            ? [{ key: 'directory', label: 'Staff directory' }]
            : []),
    ];

    return (
        <>
            <Head title="Staff and leave" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Staff and leave
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Everyone on staff, and leave requests waiting for
                            approval.
                        </p>
                    </div>
                    {tabs.length > 1 && (
                        <div
                            role="group"
                            aria-label="Show"
                            className="flex gap-1 rounded-lg border p-1"
                        >
                            {tabs.map((tab) => (
                                <Link
                                    key={tab.key}
                                    href={index({
                                        query:
                                            tab.key === 'directory'
                                                ? { tab: 'directory' }
                                                : {},
                                    })}
                                    preserveScroll
                                    aria-current={
                                        screen.tab === tab.key
                                            ? 'page'
                                            : undefined
                                    }
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground',
                                        screen.tab === tab.key &&
                                            'bg-primary/10 text-primary hover:text-primary',
                                    )}
                                >
                                    {tab.label}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {screen.tab === 'directory' ? (
                    <Directory screen={screen} />
                ) : (
                    <Card className="gap-0 p-0">
                        {screen.requests.length === 0 ? (
                            <div className="flex flex-col items-center gap-1 px-4 py-12 text-center">
                                <CalendarCheck
                                    className="size-6 text-muted-foreground"
                                    aria-hidden
                                />
                                <p className="font-medium">No leave requests</p>
                                <p className="text-sm text-muted-foreground">
                                    Staff apply from My leave; requests land
                                    here.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {screen.requests.map((item) => (
                                    <RequestRow
                                        key={item.ulid}
                                        item={item}
                                        coverOptions={screen.cover_options}
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>
                )}
            </div>
        </>
    );
}

StaffAndLeave.layout = {
    breadcrumbs: [{ title: 'Staff and leave', href: index() }],
};
