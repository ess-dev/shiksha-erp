import { router, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { selectClassName } from '@/components/school/field';
import { select as selectSession } from '@/routes/school/session';
import type { SessionSwitcher } from '@/types/students';

/**
 * The top-bar academic session switcher. The server only sends it to people
 * holding "view past sessions" and re-checks every choice; past sessions are
 * read only.
 */
export function SessionSwitcherControl() {
    const { sessionSwitcher } = usePage<{
        sessionSwitcher: SessionSwitcher | null;
    }>().props;

    if (sessionSwitcher === null || sessionSwitcher === undefined) {
        return null;
    }

    const choose = (value: string) => {
        const current = sessionSwitcher.sessions.find(
            (session) => session.is_current,
        );

        router.put(
            selectSession.url(),
            { session: value === current?.ulid ? '' : value },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <CalendarDays
                className="size-4 text-muted-foreground"
                aria-hidden
            />
            <label htmlFor="session-switcher" className="sr-only">
                Academic session
            </label>
            <select
                id="session-switcher"
                value={sessionSwitcher.selected}
                onChange={(event) => choose(event.target.value)}
                className={`${selectClassName} w-auto min-w-32`}
            >
                {sessionSwitcher.sessions.map((session) => (
                    <option key={session.ulid} value={session.ulid}>
                        {session.name}
                        {session.is_current ? ' (current)' : ''}
                    </option>
                ))}
            </select>
        </div>
    );
}
