import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Bell,
    GraduationCap,
    LogOut,
    Menu,
    Repeat,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { NotBuiltBadge } from '@/components/parent/not-built';
import { parentNav } from '@/components/parent/parent-nav';
import type { ParentNavItem } from '@/components/parent/parent-nav';
import { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import { attendance, home, signOut } from '@/routes/parent';
import { select as selectSession } from '@/routes/parent/session';
import { switchMethod } from '@/routes/school/context';
import type { ParentShell } from '@/types/parent';

type Props = { shell?: ParentShell };

/**
 * The parent shell, mirroring the prototype: an indigo top bar with the school,
 * a greeting, the child switcher and the session; a side menu on wide screens
 * and bottom tabs on phones. Only Home and Attendance exist so far; the other
 * menu entries are visible but marked "Soon".
 */
export default function ParentLayout({ children }: { children: ReactNode }) {
    useFlashToast();

    const page = usePage<Props>();
    const shell = page.props.shell;
    const path = page.url.split('?')[0] ?? '/';
    const onHome = path.replace(/\/$/, '') === home.url();
    const [menuOpen, setMenuOpen] = useState(false);

    if (shell === undefined) {
        return <>{children}</>;
    }

    const childParam =
        shell.selected_child === null ? {} : { child: shell.selected_child };
    const hrefFor = (key: string): string | null => {
        if (key === 'home') {
            return home.url({ query: childParam });
        }

        if (key === 'attendance' && shell.attendance_enabled) {
            return attendance.url({ query: childParam });
        }

        return null;
    };

    const switchChild = (ulid: string) => {
        router.get(path, { child: ulid }, { preserveScroll: true });
    };

    const chooseSession = (ulid: string) => {
        const current = shell.sessions?.sessions.find((s) => s.is_current);

        router.put(
            selectSession.url(),
            { session: ulid === current?.ulid ? '' : ulid },
            { preserveScroll: true },
        );
    };

    const title = onHome ? `Hi, ${shell.guardian_first_name}` : 'Attendance';

    return (
        <div className="min-h-svh bg-background text-foreground md:flex">
            <aside className="hidden w-60 shrink-0 flex-col border-r bg-card md:flex">
                <div className="flex items-center gap-2.5 border-b px-4 py-4">
                    <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <GraduationCap className="size-5" aria-hidden />
                    </span>
                    <div>
                        <p className="text-sm font-semibold">Shiksha ERP</p>
                        <p className="text-xs text-muted-foreground">
                            Parent portal
                        </p>
                    </div>
                </div>
                <nav
                    aria-label="Parent menu"
                    className="flex-1 space-y-0.5 overflow-y-auto p-3"
                >
                    {parentNav.map((item) => (
                        <NavRow
                            key={item.key}
                            item={item}
                            href={hrefFor(item.key)}
                            active={
                                (item.key === 'home' && onHome) ||
                                (item.key === 'attendance' && !onHome)
                            }
                            notInPlan={
                                item.key === 'attendance' &&
                                !shell.attendance_enabled
                            }
                        />
                    ))}
                </nav>
                <div className="border-t p-3">
                    <div className="mb-2 flex items-center gap-2.5 px-1">
                        <span className="flex size-9 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground">
                            {initials(shell.guardian_name)}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {shell.guardian_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Parent · {shell.children.length}{' '}
                                {shell.children.length === 1
                                    ? 'child'
                                    : 'children'}
                            </p>
                        </div>
                    </div>
                    <AccountButtons shell={shell} />
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="bg-primary text-primary-foreground">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 md:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            {!onHome && (
                                <Link
                                    href={home.url({ query: childParam })}
                                    aria-label="Back to home"
                                    className="flex size-9 items-center justify-center rounded-lg bg-white/15 hover:bg-white/25"
                                >
                                    <ArrowLeft className="size-4" aria-hidden />
                                </Link>
                            )}
                            <div className="min-w-0">
                                <p className="truncate text-xs text-primary-foreground/80">
                                    {shell.school_name}
                                </p>
                                <h1 className="truncate text-xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                            </div>
                        </div>

                        <div className="order-3 flex w-full [scrollbar-width:none] items-center gap-2 overflow-x-auto md:order-none md:w-auto md:flex-1">
                            <button
                                type="button"
                                disabled
                                title="Notifications are not built yet"
                                aria-label="Notifications (not built yet)"
                                className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/15 opacity-70"
                            >
                                <Bell className="size-4" aria-hidden />
                            </button>
                            {shell.children.map((child) => (
                                <button
                                    key={child.ulid}
                                    type="button"
                                    onClick={() => switchChild(child.ulid)}
                                    aria-pressed={
                                        child.ulid === shell.selected_child
                                    }
                                    className={cn(
                                        'flex shrink-0 items-center gap-2 rounded-full py-1 pr-4 pl-1 text-left text-sm',
                                        child.ulid === shell.selected_child
                                            ? 'bg-white text-foreground'
                                            : 'bg-white/15 hover:bg-white/25',
                                    )}
                                >
                                    <span className="flex size-8 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground">
                                        {initials(child.name)}
                                    </span>
                                    <span>
                                        <span className="block leading-tight font-semibold">
                                            {child.first_name}
                                        </span>
                                        <span className="block text-xs leading-tight opacity-75">
                                            {child.section_label} ·{' '}
                                            {child.campus_name}
                                        </span>
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="ml-auto flex items-center gap-2">
                            {shell.sessions !== null && (
                                <>
                                    <label
                                        htmlFor="parent-session"
                                        className="sr-only"
                                    >
                                        Academic session
                                    </label>
                                    <select
                                        id="parent-session"
                                        value={shell.sessions.selected}
                                        onChange={(e) =>
                                            chooseSession(e.target.value)
                                        }
                                        className={`${selectClassName} w-auto border-white/30 bg-white/15 text-primary-foreground`}
                                    >
                                        {shell.sessions.sessions.map((s) => (
                                            <option
                                                key={s.ulid}
                                                value={s.ulid}
                                                className="text-foreground"
                                            >
                                                {s.name}
                                                {s.is_current
                                                    ? ' (current)'
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                </>
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-primary-foreground hover:bg-white/20 hover:text-primary-foreground md:hidden"
                                aria-label="Open menu"
                                onClick={() => setMenuOpen(true)}
                            >
                                <Menu className="size-5" aria-hidden />
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl flex-1 p-4 pb-24 md:p-6 md:pb-8">
                    {children}
                </main>
            </div>

            <nav
                aria-label="Parent tabs"
                className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 border-t bg-card md:hidden"
            >
                {(['home', 'attendance'] as const).map((key) => {
                    const item = parentNav.find((n) => n.key === key);
                    const href = hrefFor(key);

                    if (item === undefined) {
                        return null;
                    }

                    const active =
                        (key === 'home' && onHome) ||
                        (key === 'attendance' && !onHome);
                    const Icon = item.icon;

                    return href === null ? (
                        <span
                            key={key}
                            className="flex flex-col items-center gap-0.5 py-2 text-[11px] text-muted-foreground opacity-60"
                        >
                            <Icon className="size-5" aria-hidden />
                            {item.label}
                        </span>
                    ) : (
                        <Link
                            key={key}
                            href={href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex flex-col items-center gap-0.5 py-2 text-[11px]',
                                active
                                    ? 'font-semibold text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <Icon className="size-5" aria-hidden />
                            {item.label}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setMenuOpen(true)}
                    className="flex flex-col items-center gap-0.5 py-2 text-[11px] text-muted-foreground"
                >
                    <Menu className="size-5" aria-hidden />
                    More
                </button>
            </nav>

            <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                <SheetContent side="right" className="w-72 p-0">
                    <SheetHeader className="border-b">
                        <SheetTitle>{shell.guardian_name}</SheetTitle>
                    </SheetHeader>
                    <div className="space-y-0.5 overflow-y-auto p-3">
                        {parentNav.map((item) => (
                            <NavRow
                                key={item.key}
                                item={item}
                                href={hrefFor(item.key)}
                                active={false}
                                notInPlan={
                                    item.key === 'attendance' &&
                                    !shell.attendance_enabled
                                }
                            />
                        ))}
                    </div>
                    <div className="border-t p-3">
                        <AccountButtons shell={shell} />
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}

function NavRow({
    item,
    href,
    active,
    notInPlan,
}: {
    item: ParentNavItem;
    href: string | null;
    active: boolean;
    notInPlan: boolean;
}) {
    const Icon = item.icon;
    const body = (
        <>
            <Icon className="size-[18px] shrink-0" aria-hidden />
            <span className="flex-1">{item.label}</span>
            {notInPlan ? (
                <NotBuiltBadge text="Not in plan" />
            ) : (
                !item.built && <NotBuiltBadge text="Soon" />
            )}
        </>
    );

    if (href === null) {
        return (
            <span
                aria-disabled="true"
                className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-muted-foreground opacity-70"
            >
                {body}
            </span>
        );
    }

    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm',
                active
                    ? 'bg-accent font-semibold text-accent-foreground'
                    : 'hover:bg-muted',
            )}
        >
            {body}
        </Link>
    );
}

function AccountButtons({ shell }: { shell: ParentShell }) {
    return (
        <div className="grid gap-1.5">
            {shell.has_staff_context && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="justify-start"
                    onClick={() =>
                        router.put(switchMethod.url(), { context: 'staff' })
                    }
                >
                    <Repeat aria-hidden /> Switch to staff
                </Button>
            )}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="justify-start"
                onClick={() => router.post(signOut.url())}
            >
                <LogOut aria-hidden /> Sign out
            </Button>
        </div>
    );
}
