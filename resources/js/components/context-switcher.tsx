import { router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { switchMethod } from '@/routes/school/context';
import type { SchoolContext } from '@/types/parent';

/**
 * Shown only to someone who is both staff and a parent at this school: opens
 * the parent view. (The way back is in the parent menu and asks for the staff
 * password again.)
 */
export function ContextSwitcherControl() {
    const { schoolContext } = usePage<{
        schoolContext: SchoolContext | null;
    }>().props;

    if (
        schoolContext === null ||
        schoolContext === undefined ||
        !schoolContext.can_open_parent_view
    ) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() =>
                router.put(switchMethod.url(), { context: 'guardian' })
            }
        >
            <Users aria-hidden /> Parent view
        </Button>
    );
}
