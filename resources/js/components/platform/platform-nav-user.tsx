import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useInitials } from '@/hooks/use-initials';
import { signOut } from '@/routes/platform';

export default function PlatformNavUser() {
    const { platform } = usePage().props;
    const getInitials = useInitials();

    if (!platform.user) {
        return null;
    }

    const { name, email } = platform.user;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            data-test="platform-user-menu"
                        >
                            <Avatar className="size-8">
                                <AvatarFallback className="bg-indigo-100 text-xs font-semibold text-indigo-800">
                                    {getInitials(name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    Super Admin
                                </span>
                            </span>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side="top"
                    >
                        <DropdownMenuLabel className="font-normal">
                            <span className="block truncate text-sm font-medium">
                                {name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {email}
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href={signOut()}
                                as="button"
                                data-test="platform-sign-out"
                            >
                                <LogOut className="mr-2 inline size-4" />
                                Sign out
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
