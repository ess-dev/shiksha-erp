import { Link } from '@inertiajs/react';
import {
    Building2,
    CircleUserRound,
    History,
    IndianRupee,
    Layers,
    LayoutDashboard,
    PlusCircle,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import PlatformBrand from '@/components/platform/platform-brand';
import PlatformNavUser from '@/components/platform/platform-nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes/platform';
import { index as plans } from '@/routes/platform/plans';
import { index as schools } from '@/routes/platform/schools';
import type { NavItem } from '@/types';

type PlatformNavItem = Omit<NavItem, 'href' | 'icon'> & {
    icon: LucideIcon;
    /** Absent while the screen is not built yet. */
    href?: NavItem['href'];
};

/** Same entries, in the same order, as the prototype's Super Admin menu. */
const items: PlatformNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
    { title: 'Schools', href: schools(), icon: Building2 },
    { title: 'Plans', href: plans(), icon: Layers },
    { title: 'Onboard a school', icon: PlusCircle },
    { title: 'Revenue', icon: IndianRupee },
    { title: 'Roles and permissions', icon: ShieldCheck },
    { title: 'Activity logs', icon: History },
    { title: 'My profile', icon: CircleUserRound },
];

export default function PlatformSidebar() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <Sidebar collapsible="offcanvas" variant="sidebar">
            <SidebarHeader className="border-b border-sidebar-border p-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto p-0 hover:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <PlatformBrand />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2.5 py-3">
                    <SidebarMenu className="gap-0.5">
                        {items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                {item.href ? (
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        className="h-9 px-3 font-medium data-[active=true]:font-semibold"
                                    >
                                        <Link href={item.href} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                ) : (
                                    <SidebarMenuButton
                                        disabled
                                        aria-disabled="true"
                                        className="h-9 px-3 font-medium text-muted-foreground"
                                    >
                                        <item.icon className="opacity-60" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {item.title}
                                        </span>
                                        <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap text-muted-foreground">
                                            Soon
                                        </span>
                                    </SidebarMenuButton>
                                )}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                <PlatformNavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
