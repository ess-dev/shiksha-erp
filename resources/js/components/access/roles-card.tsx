import { Plus } from 'lucide-react';
import { useState } from 'react';
import RoleDialog from '@/components/access/role-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    PermissionOption,
    RoleSummary,
    RoleTemplateOption,
} from '@/types/access';

export default function RolesCard({
    roles,
    options,
    templates,
    canManage,
}: {
    roles: RoleSummary[];
    options: PermissionOption[];
    templates: RoleTemplateOption[];
    canManage: boolean;
}) {
    const [dialog, setDialog] = useState<
        { kind: 'new' } | { kind: 'edit'; role: RoleSummary } | null
    >(null);

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="grid gap-1.5">
                    <CardTitle>Roles</CardTitle>
                    <CardDescription>
                        A role is a set of permissions. Who gets it, and for
                        which part of the school, is decided below.
                    </CardDescription>
                </div>
                {canManage && (
                    <Button
                        size="sm"
                        onClick={() => setDialog({ kind: 'new' })}
                    >
                        <Plus /> New role
                    </Button>
                )}
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                            <th className="px-3 py-2 font-medium">Role</th>
                            <th className="hidden px-3 py-2 font-medium md:table-cell">
                                Copied from
                            </th>
                            <th className="px-3 py-2 font-medium">
                                Permissions
                            </th>
                            <th className="px-3 py-2 font-medium">People</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {roles.map((role) => (
                            <tr key={role.ulid} className="hover:bg-muted/30">
                                <td className="px-3 py-2 font-medium">
                                    {role.name}{' '}
                                    {role.is_owner && (
                                        <Badge variant="outline">Owner</Badge>
                                    )}{' '}
                                    {role.update_pending && (
                                        <Badge>Update available</Badge>
                                    )}
                                </td>
                                <td className="hidden px-3 py-2 text-muted-foreground md:table-cell">
                                    {role.template_name
                                        ? `${role.template_name} v${role.template_version}`
                                        : 'Own role'}
                                </td>
                                <td className="px-3 py-2">
                                    {role.permissions.length}
                                </td>
                                <td className="px-3 py-2">
                                    {role.assignee_count}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    {canManage && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setDialog({
                                                    kind: 'edit',
                                                    role,
                                                })
                                            }
                                        >
                                            Permissions
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>

            {dialog && (
                <RoleDialog
                    key={dialog.kind === 'edit' ? dialog.role.ulid : 'new'}
                    role={dialog.kind === 'edit' ? dialog.role : null}
                    options={options}
                    templates={templates}
                    onClose={() => setDialog(null)}
                />
            )}
        </Card>
    );
}
