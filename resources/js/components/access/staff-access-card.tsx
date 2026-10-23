import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import AssignRoleDialog from '@/components/access/assign-role-dialog';
import scopeLabel from '@/components/access/scope-label';
import InitialsBadge from '@/components/students/initials-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { destroy } from '@/routes/school/roles/assignments';
import type {
    PermissionOption,
    RoleAssignmentRow,
    RoleSummary,
    ScopeOptions,
    StaffAccess,
} from '@/types/access';

export default function StaffAccessCard({
    staff,
    roles,
    permissions,
    scopeOptions,
    canAssign,
    error,
}: {
    staff: StaffAccess[];
    roles: RoleSummary[];
    permissions: PermissionOption[];
    scopeOptions: ScopeOptions | null;
    canAssign: boolean;
    error?: string;
}) {
    const [assigning, setAssigning] = useState<StaffAccess | null>(null);
    const [removing, setRemoving] = useState<{
        staff: StaffAccess;
        assignment: RoleAssignmentRow;
    } | null>(null);
    const [busy, setBusy] = useState(false);

    const remove = () => {
        if (!removing) {
            return;
        }

        router.delete(destroy.url(removing.assignment.ulid), {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: () => setRemoving(null),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Who has which role</CardTitle>
                <CardDescription>
                    Each role works only for the part of the school it was given
                    for. Parents and students have no roles; their access comes
                    from the children linked to them.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-1">
                <InputError message={error} />
                {staff.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No staff have been added yet.
                    </p>
                )}
                <ul className="divide-y">
                    {staff.map((member) => (
                        <li
                            key={member.ulid}
                            className="flex flex-wrap items-start gap-3 py-3"
                        >
                            <InitialsBadge name={member.full_name} />
                            <div className="grid min-w-0 flex-1 gap-1.5">
                                <span className="text-sm font-medium">
                                    {member.full_name}{' '}
                                    {member.status !== 'active' && (
                                        <Badge variant="secondary">
                                            Inactive
                                        </Badge>
                                    )}
                                </span>
                                {member.assignments.length === 0 ? (
                                    <span className="text-sm text-muted-foreground">
                                        No role yet
                                    </span>
                                ) : (
                                    <ul className="flex flex-wrap gap-2">
                                        {member.assignments.map(
                                            (assignment) => (
                                                <li
                                                    key={assignment.ulid}
                                                    className="flex items-center gap-1 rounded-full border bg-muted/40 py-0.5 pr-1 pl-3 text-xs"
                                                >
                                                    <span>
                                                        <span className="font-medium">
                                                            {
                                                                assignment.role_name
                                                            }
                                                        </span>{' '}
                                                        <span className="text-muted-foreground">
                                                            {scopeLabel(
                                                                assignment,
                                                                scopeOptions,
                                                            )}
                                                        </span>
                                                    </span>
                                                    {canAssign && (
                                                        <button
                                                            type="button"
                                                            aria-label={`Remove ${assignment.role_name} from ${member.full_name}`}
                                                            className="rounded-full p-1 hover:bg-muted"
                                                            onClick={() =>
                                                                setRemoving({
                                                                    staff: member,
                                                                    assignment,
                                                                })
                                                            }
                                                        >
                                                            <X className="size-3" />
                                                        </button>
                                                    )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </div>
                            {canAssign &&
                                scopeOptions &&
                                member.status === 'active' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setAssigning(member)}
                                    >
                                        Give role
                                    </Button>
                                )}
                        </li>
                    ))}
                </ul>
            </CardContent>

            {assigning && scopeOptions && (
                <AssignRoleDialog
                    key={assigning.ulid}
                    staff={assigning}
                    roles={roles}
                    permissions={permissions}
                    scopeOptions={scopeOptions}
                    onClose={() => setAssigning(null)}
                />
            )}

            {removing && (
                <Dialog
                    open
                    onOpenChange={(open) => !open && setRemoving(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                Remove {removing.assignment.role_name}?
                            </DialogTitle>
                            <DialogDescription>
                                {removing.staff.full_name} loses what this role
                                allowed for{' '}
                                {scopeLabel(removing.assignment, scopeOptions)}.
                                Their other roles are not affected. The change
                                is recorded in the audit trail.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setRemoving(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={busy}
                                onClick={remove}
                            >
                                Remove role
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </Card>
    );
}
