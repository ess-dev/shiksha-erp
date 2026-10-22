import { History } from 'lucide-react';

/** Shown on Students screens while a past academic session is selected. */
export default function PastSessionBanner({ name }: { name: string }) {
    return (
        <div
            role="status"
            className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
        >
            <History className="size-4 shrink-0" aria-hidden />
            Viewing the {name} session. Records are read only; switch to the
            current session at the top to make changes.
        </div>
    );
}
