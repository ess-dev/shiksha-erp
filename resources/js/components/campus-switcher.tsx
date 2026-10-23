import { router, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { selectClassName } from '@/components/school/field';
import { select as selectCampus } from '@/routes/school/campus';
import type { CampusSwitcher } from '@/types/students';

/**
 * The top-bar campus switcher. It only shows for people who can reach more
 * than one campus, and only narrows what they see; the server checks the
 * choice against their access.
 */
export function CampusSwitcherControl() {
    const { campusSwitcher } = usePage<{
        campusSwitcher: CampusSwitcher | null;
    }>().props;

    if (campusSwitcher === null || campusSwitcher === undefined) {
        return null;
    }

    const choose = (value: string) => {
        router.put(
            selectCampus.url(),
            { campus: value },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <MapPin className="size-4 text-muted-foreground" aria-hidden />
            <label htmlFor="campus-switcher" className="sr-only">
                Campus
            </label>
            <select
                id="campus-switcher"
                value={campusSwitcher.selected ?? ''}
                onChange={(event) => choose(event.target.value)}
                className={`${selectClassName} w-auto min-w-40`}
            >
                <option value="">All campuses</option>
                {campusSwitcher.campuses.map((campus) => (
                    <option key={campus.ulid} value={campus.ulid}>
                        {campus.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
