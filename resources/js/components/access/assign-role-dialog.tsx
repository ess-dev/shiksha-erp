import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { hasCritical } from '@/components/access/permission-picker';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { assign } from '@/routes/school/roles';
import type {
    PermissionOption,
    RoleSummary,
    ScopeKind,
    ScopeOptions,
    StaffAccess,
} from '@/types/access';

function toggle(list: string[], value: string, on: boolean): string[] {
    return on ? [...list, value] : list.filter((item) => item !== value);
}

export default function AssignRoleDialog({
    staff,
    roles,
    permissions,
    scopeOptions,
    onClose,
}: {
    staff: StaffAccess;
    roles: RoleSummary[];
    permissions: PermissionOption[];
    scopeOptions: ScopeOptions;
    onClose: () => void;
}) {
    const firstLevel = scopeOptions.levels[0]?.level ?? 1;
    const lastLevel =
        scopeOptions.levels[scopeOptions.levels.length - 1]?.level ?? 1;

    const form = useForm({
        role: roles[0]?.ulid ?? '',
        scope_type: 'school' as ScopeKind,
        campuses: [] as string[],
        sections: [] as string[],
        from_level: firstLevel,
        to_level: lastLevel,
        confirm_critical: false,
    });
    const errors = form.errors as Record<string, string>;
    const role = roles.find((r) => r.ulid === form.data.role);
    const critical = role ? hasCritical(role.permissions, permissions) : false;
    const type = form.data.scope_type;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            scope_type: data.scope_type,
            confirm_critical: data.confirm_critical,
            ...(data.scope_type === 'campus' || data.scope_type === 'wing'
                ? { campuses: data.campuses }
                : {}),
            ...(data.scope_type === 'wing'
                ? { from_level: data.from_level, to_level: data.to_level }
                : {}),
            ...(data.scope_type === 'sections'
                ? { sections: data.sections }
                : {}),
        }));

        form.post(assign.url({ role: form.data.role, staff: staff.ulid }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const campusList = (
        <div className="grid gap-2">
            {scopeOptions.campuses.map((campus) => (
                <div key={campus.ulid} className="flex items-center gap-2">
                    <Checkbox
                        id={`scope-campus-${campus.ulid}`}
                        checked={form.data.campuses.includes(campus.ulid)}
                        onCheckedChange={(value) =>
                            form.setData(
                                'campuses',
                                toggle(
                                    form.data.campuses,
                                    campus.ulid,
                                    value === true,
                                ),
                            )
                        }
                    />
                    <Label htmlFor={`scope-campus-${campus.ulid}`}>
                        {campus.name}
                    </Label>
                </div>
            ))}
            <InputError message={errors.campuses} />
        </div>
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Give a role to {staff.full_name}</DialogTitle>
                    <DialogDescription>
                        The role only works for the part of the school you
                        choose. You can only give what you hold yourself.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                    noValidate
                >
                    <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto pr-1">
                        <div className="grid gap-1.5">
                            <Label htmlFor="assign-role">Role</Label>
                            <select
                                id="assign-role"
                                className={selectClassName}
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData('role', e.target.value)
                                }
                            >
                                {roles.map((r) => (
                                    <option key={r.ulid} value={r.ulid}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="assign-scope">Applies to</Label>
                            <select
                                id="assign-scope"
                                className={selectClassName}
                                value={type}
                                onChange={(e) =>
                                    form.setData(
                                        'scope_type',
                                        e.target.value as ScopeKind,
                                    )
                                }
                            >
                                <option value="school">Whole school</option>
                                <option value="campus">
                                    One or more campuses
                                </option>
                                <option value="wing">
                                    A range of classes at some campuses
                                </option>
                                <option value="sections">
                                    Specific sections
                                </option>
                            </select>
                        </div>

                        {(type === 'campus' || type === 'wing') && campusList}

                        {type === 'wing' && (
                            <div className="grid grid-cols-2 gap-3">
                                {(['from_level', 'to_level'] as const).map(
                                    (field) => (
                                        <div
                                            key={field}
                                            className="grid gap-1.5"
                                        >
                                            <Label htmlFor={`assign-${field}`}>
                                                {field === 'from_level'
                                                    ? 'From class'
                                                    : 'To class'}
                                            </Label>
                                            <select
                                                id={`assign-${field}`}
                                                className={selectClassName}
                                                value={form.data[field]}
                                                onChange={(e) =>
                                                    form.setData(
                                                        field,
                                                        Number(e.target.value),
                                                    )
                                                }
                                            >
                                                {scopeOptions.levels.map(
                                                    (l) => (
                                                        <option
                                                            key={l.level}
                                                            value={l.level}
                                                        >
                                                            {l.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors[field]}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                        )}

                        {type === 'sections' && (
                            <div className="grid max-h-56 gap-2 overflow-y-auto rounded-md border p-3">
                                {scopeOptions.sections.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        There are no sections in the current
                                        academic year yet.
                                    </p>
                                )}
                                {scopeOptions.sections.map((section) => (
                                    <div
                                        key={section.ulid}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`scope-section-${section.ulid}`}
                                            checked={form.data.sections.includes(
                                                section.ulid,
                                            )}
                                            onCheckedChange={(value) =>
                                                form.setData(
                                                    'sections',
                                                    toggle(
                                                        form.data.sections,
                                                        section.ulid,
                                                        value === true,
                                                    ),
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`scope-section-${section.ulid}`}
                                        >
                                            {section.label}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                {section.campus_name}
                                            </span>
                                        </Label>
                                    </div>
                                ))}
                                <InputError message={errors.sections} />
                            </div>
                        )}

                        {critical && (
                            <div className="flex items-start gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-3">
                                <Checkbox
                                    id="assign-confirm-critical"
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
                                    htmlFor="assign-confirm-critical"
                                    className="text-sm"
                                >
                                    This role has critical permissions. I
                                    confirm {staff.full_name} should have them.
                                    They must set up two-step sign-in before
                                    using the staff area.
                                </label>
                            </div>
                        )}

                        <InputError message={errors.roles} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !role}
                        >
                            {form.processing && <Spinner />}
                            Give role
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
