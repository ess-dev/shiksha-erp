import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { parseRupees } from '@/lib/fees';
import { formatPaise } from '@/lib/format';
import { store as storeConcession } from '@/routes/fees/structure/concessions';
import { rename, store as storeGroup } from '@/routes/fees/structure/groups';
import type { FeeGroup, FeeStructure } from '@/types';

/** Server errors the structure screens show: one rule message, or a field message. */
function useServerErrors(): Record<string, string> {
    return usePage().props.errors as Record<string, string>;
}

function DialogShell({
    title,
    description,
    onClose,
    onSubmit,
    children,
    footer,
}: {
    title: string;
    description?: string;
    onClose: () => void;
    onSubmit: () => void;
    children: React.ReactNode;
    footer: React.ReactNode;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription
                        className={description === undefined ? 'sr-only' : ''}
                    >
                        {description ?? title}
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit();
                    }}
                >
                    <div className="grid gap-4 px-6 py-5">{children}</div>
                    <DialogFooter className="border-t px-6 py-4">
                        {footer}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** "New fee group": a name, optional starting fees and the classes to move in. */
export function NewGroupDialog({
    structure,
    onClose,
}: {
    structure: FeeStructure;
    onClose: () => void;
}) {
    const errors = useServerErrors();
    const [name, setName] = useState('');
    const [copyFrom, setCopyFrom] = useState('');
    const [classes, setClasses] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);

        router.post(
            storeGroup.url(),
            { name, copy_from: copyFrom || undefined, classes },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <DialogShell
            title="New fee group"
            onClose={onClose}
            onSubmit={submit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Create group
                    </Button>
                </>
            }
        >
            <Field id="group-name" label="Group name" error={errors.name}>
                <Input
                    id="group-name"
                    value={name}
                    maxLength={40}
                    autoFocus
                    placeholder="Classes 11–12 (Science)"
                    onChange={(event) => setName(event.target.value)}
                />
            </Field>
            <Field id="group-copy" label="Start with the fees of">
                <select
                    id="group-copy"
                    className={selectClassName}
                    value={copyFrom}
                    onChange={(event) => setCopyFrom(event.target.value)}
                >
                    <option value="">Nothing (enter fees after)</option>
                    {structure.groups.map((group) => (
                        <option key={group.ulid} value={group.ulid}>
                            {group.name} · {formatPaise(group.term_paise)}
                        </option>
                    ))}
                </select>
            </Field>
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">
                    Move these classes into it (optional)
                </legend>
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {structure.classes.map((item) => (
                        <label
                            key={item.ulid}
                            className="flex items-center gap-1.5 text-sm"
                        >
                            <Checkbox
                                checked={classes.includes(item.ulid)}
                                onCheckedChange={(value) =>
                                    setClasses((current) =>
                                        value === true
                                            ? [...current, item.ulid]
                                            : current.filter(
                                                  (ulid) => ulid !== item.ulid,
                                              ),
                                    )
                                }
                            />
                            {item.name.replace(/^Class\s+/, '')}
                        </label>
                    ))}
                </div>
            </fieldset>
            <InputError message={errors.fees} />
        </DialogShell>
    );
}

/** "Give a class its own fees": a new group named after the class, copied from its current one. */
export function OwnFeesDialog({
    structure,
    onClose,
}: {
    structure: FeeStructure;
    onClose: () => void;
}) {
    const errors = useServerErrors();
    const [classUlid, setClassUlid] = useState(
        structure.classes[0]?.ulid ?? '',
    );
    const [processing, setProcessing] = useState(false);
    const chosen = structure.classes.find((item) => item.ulid === classUlid);
    const current = structure.groups.find(
        (group) => group.ulid === chosen?.fee_group_ulid,
    );

    const submit = () => {
        if (chosen === undefined) {
            return;
        }

        setProcessing(true);

        router.post(
            storeGroup.url(),
            {
                name: chosen.name,
                copy_from: current?.ulid,
                classes: [chosen.ulid],
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <DialogShell
            title="Give a class its own fees"
            onClose={onClose}
            onSubmit={submit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button disabled={processing || chosen === undefined}>
                        {processing && <Spinner />}
                        Create group
                    </Button>
                </>
            }
        >
            <Field id="own-class" label="Class">
                <select
                    id="own-class"
                    className={selectClassName}
                    value={classUlid}
                    onChange={(event) => setClassUlid(event.target.value)}
                >
                    {structure.classes.map((item) => {
                        const group = structure.groups.find(
                            (g) => g.ulid === item.fee_group_ulid,
                        );

                        return (
                            <option key={item.ulid} value={item.ulid}>
                                {item.name}
                                {group === undefined
                                    ? ''
                                    : ` (now in ${group.name})`}
                            </option>
                        );
                    })}
                </select>
                <span className="text-xs text-muted-foreground">
                    Starts with the fees of its current group; edit them after.
                </span>
            </Field>
            <InputError message={errors.fees} />
        </DialogShell>
    );
}

export function RenameGroupDialog({
    group,
    onClose,
}: {
    group: FeeGroup;
    onClose: () => void;
}) {
    const errors = useServerErrors();
    const [name, setName] = useState(group.name);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);

        router.patch(
            rename.url(group.ulid),
            { name },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <DialogShell
            title={`Rename ${group.name}`}
            onClose={onClose}
            onSubmit={submit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Rename
                    </Button>
                </>
            }
        >
            <Field
                id="rename-group"
                label="Name"
                error={errors.name ?? errors.fees}
            >
                <Input
                    id="rename-group"
                    value={name}
                    maxLength={40}
                    autoFocus
                    onChange={(event) => setName(event.target.value)}
                />
            </Field>
        </DialogShell>
    );
}

/** "Add concession": a discount on one fee head, assigned to students later. */
export function AddConcessionDialog({
    headNames,
    onClose,
}: {
    headNames: string[];
    onClose: () => void;
}) {
    const errors = useServerErrors();
    const [name, setName] = useState('');
    const [type, setType] = useState<'percent' | 'flat'>('percent');
    const [value, setValue] = useState('');
    const [head, setHead] = useState(headNames[0] ?? '');
    const [processing, setProcessing] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const submit = () => {
        const amount = parseRupees(value);

        if (amount === null || amount < 1) {
            setLocalError('Enter a whole number.');

            return;
        }

        setLocalError(null);
        setProcessing(true);

        router.post(
            storeConcession.url(),
            { name, type, value: amount, head },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <DialogShell
            title="Add concession"
            description="You can then assign it to students from their profile. Concessions above 50% need the principal’s approval."
            onClose={onClose}
            onSubmit={submit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || headNames.length === 0}
                    >
                        {processing && <Spinner />}
                        Add concession
                    </Button>
                </>
            }
        >
            <Field id="concession-name" label="Name" error={errors.name}>
                <Input
                    id="concession-name"
                    value={name}
                    maxLength={60}
                    autoFocus
                    placeholder="Sports scholarship"
                    onChange={(event) => setName(event.target.value)}
                />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field id="concession-type" label="Type">
                    <select
                        id="concession-type"
                        className={selectClassName}
                        value={type}
                        onChange={(event) =>
                            setType(event.target.value as 'percent' | 'flat')
                        }
                    >
                        <option value="percent">Percentage</option>
                        <option value="flat">Fixed amount</option>
                    </select>
                </Field>
                <Field
                    id="concession-value"
                    label={type === 'percent' ? 'Value (%)' : 'Value (₹)'}
                    error={localError ?? errors.value}
                >
                    <Input
                        id="concession-value"
                        inputMode="numeric"
                        value={value}
                        placeholder="25"
                        onChange={(event) => setValue(event.target.value)}
                    />
                </Field>
            </div>
            <Field id="concession-head" label="Applies to">
                <select
                    id="concession-head"
                    className={selectClassName}
                    value={head}
                    onChange={(event) => setHead(event.target.value)}
                >
                    {headNames.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </Field>
            <InputError message={errors.fees} />
        </DialogShell>
    );
}
