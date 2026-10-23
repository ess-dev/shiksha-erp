import { Head, usePage } from '@inertiajs/react';
import RolesCard from '@/components/access/roles-card';
import StaffAccessCard from '@/components/access/staff-access-card';
import TemplateUpdateCard from '@/components/access/template-update-card';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/school/roles';
import type {
    PermissionOption,
    RoleSummary,
    RoleTemplateOption,
    RoleUpdate,
    ScopeOptions,
    StaffAccess,
} from '@/types/access';

type Props = {
    roles: RoleSummary[];
    permissions: PermissionOption[];
    staff: StaffAccess[];
    updates: RoleUpdate[];
    scopeOptions: ScopeOptions | null;
    templates: RoleTemplateOption[];
};

export default function Roles({
    roles,
    permissions,
    staff,
    updates,
    scopeOptions,
    templates,
}: Props) {
    const canManage = useCan('roles.manage');
    const canAssign = useCan('roles.assign') || canManage;
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <>
            <Head title="Roles and access" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Roles and access
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Decide who can do what, and for which part of the
                        school.
                    </p>
                </div>

                <TemplateUpdateCard
                    updates={updates}
                    options={permissions}
                    canManage={canManage}
                />
                <RolesCard
                    roles={roles}
                    options={permissions}
                    templates={templates}
                    canManage={canManage}
                />
                <StaffAccessCard
                    staff={staff}
                    roles={roles}
                    permissions={permissions}
                    scopeOptions={scopeOptions}
                    canAssign={canAssign}
                    error={errors.roles}
                />
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [
        {
            title: 'Roles and access',
            href: index(),
        },
    ],
};
