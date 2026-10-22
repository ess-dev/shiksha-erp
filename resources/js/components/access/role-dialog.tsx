import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PermissionPicker, {
    hasCritical,
} from '@/components/access/permission-picker';
import { selectClassName } from '@/components/school/field';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { permissions as permissionsRoute, store } from '@/routes/school/roles';
import type {
    PermissionOption,
    RoleSummary,
    RoleTemplateOption,
} from '@/types/access';

/**
 * Creates a role (role = null) or edits the permissions of an existing one.
 * A copy of a template is made on the server, so for a new role the template
 * choice only decides where the permissions start from.
 */
export default function RoleDialog({
    role,
    options,
    templates,
    onClose,
}: {
    role: RoleSummary | null;
    options: PermissionOption[];
    templates: RoleTemplateOption[];
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        template: '',
        permissions: role?.permissions ?? ([] as string[]),
        confirm_critical: false,
    });
    const errors = form.errors as Record<string, string>;
    const critical = hasCritical(form.data.permissions, options);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (role) {
            form.put(permissionsRoute.url(role.ulid), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {role ? `Permissions for ${role.name}` : 'New role'}
                    </DialogTitle>
                    <DialogDescription>
                        {role
                            ? 'You can only switch on permissions you hold yourself. Changes apply to everyone who has this role.'
                            : 'Copy a template, or start blank and pick permissions. You can change them later.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                    noValidate
                >
                    <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto pr-1">
                        {!role && (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="role-name">Role name</Label>
                                    <Input
                                        id="role-name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="role-template">
                                        Start from
                                    </Label>
                                    <select
                                        id="role-template"
                                        className={selectClassName}
                                        value={form.data.template}
                                        onChange={(e) =>
                                            form.setData(
                                                'template',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Blank (no permissions)
                                        </option>
                                        {templates.map((template) => (
                                            <option
                                                key={template.key}
                                                value={template.key}
                                            >
                                                {template.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </>
                        )}

                        <PermissionPicker
                            options={options}
                            selected={form.data.permissions}
                            isOwnerRole={role?.is_owner ?? false}
                            idPrefix="perm"
                            onChange={(keys) =>
                                form.setData('permissions', keys)
                            }
                        />
                    </div>

                    {critical && (
                        <div className="flex items-start gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-3">
                            <Checkbox
                                id="confirm-critical"
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
                                htmlFor="confirm-critical"
                                className="text-sm"
                            >
                                This role includes critical permissions. I
                                confirm they are meant for this role. People who
                                hold it must set up two-step sign-in.
                            </label>
                        </div>
                    )}

                    <InputError message={errors.roles} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {role ? 'Save permissions' : 'Create role'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
