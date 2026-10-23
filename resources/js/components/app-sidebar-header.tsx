import { Breadcrumbs } from '@/components/breadcrumbs';
import { CampusSwitcherControl } from '@/components/campus-switcher';
import { SessionSwitcherControl } from '@/components/session-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * School top bar, styled like the Super Admin one: 60px, white, with the page
 * trail on the left and the session and campus switchers on the right.
 */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-[60px] shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-3">
                <SessionSwitcherControl />
                <CampusSwitcherControl />
            </div>
        </header>
    );
}
