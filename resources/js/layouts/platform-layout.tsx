import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import PlatformSidebar from '@/components/platform/platform-sidebar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * Super Admin shell: flat white sidebar, 60px top bar with the page title,
 * content on the light grey page background (as in the prototype).
 */
export default function PlatformLayout({
    title = '',
    breadcrumbs = [],
    children,
}: {
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppShell variant="sidebar">
            <PlatformSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="flex h-[60px] shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6">
                    <SidebarTrigger className="-ml-1 md:hidden" />
                    <h1 className="text-[15px] font-semibold tracking-tight">
                        {title || breadcrumbs.at(-1)?.title}
                    </h1>
                    <span className="ml-auto rounded-full bg-secondary px-3 py-1 text-[13px] font-semibold text-indigo-800">
                        Super Admin
                    </span>
                </header>
                <main id="main" className="flex-1 p-4 md:p-6">
                    {children}
                </main>
            </AppContent>
        </AppShell>
    );
}
