import { useForm } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { templateUpdate } from '@/routes/school/roles';
import type { PermissionOption, RoleUpdate } from '@/types/access';

function describe(keys: string[], options: PermissionOption[]): string {
    return keys
        .map((key) => options.find((o) => o.key === key)?.description ?? key)
        .join(', ');
}

function UpdateRow({
    update,
    options,
    canManage,
}: {
    update: RoleUpdate;
    options: PermissionOption[];
    canManage: boolean;
}) {
    const form = useForm({ confirm_critical: false });
    const errors = form.errors as Record<string, string>;
    const [open, setOpen] = useState(false);

    const apply = () =>
        form.post(templateUpdate.url(update.role_ulid), {
            preserveScroll: true,
        });

    return (
        <li className="grid gap-2 py-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="grid gap-1 text-sm">
                    <span className="font-medium">
                        {update.role_name}{' '}
                        <span className="font-normal text-muted-foreground">
                            version {update.from_version ?? '?'} to{' '}
                            {update.to_version}
                        </span>
                    </span>
                    {update.added.length > 0 && (
                        <span>
                            <span className="text-muted-foreground">
                                Adds:{' '}
                            </span>
                            {describe(update.added, options)}
                        </span>
                    )}
                    {update.removed.length > 0 && (
                        <span>
                            <span className="text-muted-foreground">
                                Removes:{' '}
                            </span>
                            {describe(update.removed, options)}
                        </span>
                    )}
                    <span className="text-xs text-muted-foreground">
                        Permissions you added or removed yourself stay as they
                        are.
                    </span>
                </div>
                {canManage && !open && (
                    <Button size="sm" onClick={() => setOpen(true)}>
                        <RefreshCw /> Review and update
                    </Button>
                )}
            </div>

            {open && (
                <div className="grid gap-3 rounded-md border p-3">
                    {update.adds_critical && (
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id={`confirm-${update.role_ulid}`}
                                checked={form.data.confirm_critical}
                                onCheckedChange={(value) =>
                                    form.setData(
                                        'confirm_critical',
                                        value === true,
                                    )
                                }
                                className="mt-0.5"
                            />
                            <label
                                htmlFor={`confirm-${update.role_ulid}`}
                                className="text-sm"
                            >
                                The update adds critical permissions. I confirm
                                they are meant for this role. People who hold it
                                must set up two-step sign-in.
                            </label>
                        </div>
                    )}
                    <InputError message={errors.roles} />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            onClick={apply}
                            disabled={
                                form.processing ||
                                (update.adds_critical &&
                                    !form.data.confirm_critical)
                            }
                        >
                            Update {update.role_name}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Not now
                        </Button>
                    </div>
                </div>
            )}
        </li>
    );
}

/**
 * The "template update available" prompt. Skipping is simply not applying:
 * the prompt stays until the school applies it.
 */
export default function TemplateUpdateCard({
    updates,
    options,
    canManage,
}: {
    updates: RoleUpdate[];
    options: PermissionOption[];
    canManage: boolean;
}) {
    if (updates.length === 0) {
        return null;
    }

    return (
        <Alert>
            <RefreshCw />
            <AlertTitle>
                Template update available for {updates.length}{' '}
                {updates.length === 1 ? 'role' : 'roles'}
            </AlertTitle>
            <AlertDescription>
                <p>
                    Shiksha has improved the role templates these roles were
                    copied from. Nothing changes until you update a role.
                </p>
                <ul className="mt-2 w-full divide-y">
                    {updates.map((update) => (
                        <UpdateRow
                            key={update.role_ulid}
                            update={update}
                            options={options}
                            canManage={canManage}
                        />
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
