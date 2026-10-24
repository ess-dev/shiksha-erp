import { cn } from '@/lib/utils';

/**
 * A labelled on/off switch (a button with role "switch", so it works with the
 * keyboard and screen readers).
 */
export default function ToggleRow({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={checked}
                aria-labelledby={`${id}-label`}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    checked ? 'bg-primary' : 'bg-muted-foreground/30',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform',
                        checked && 'translate-x-5',
                    )}
                />
            </button>
            <span id={`${id}-label`} className="text-sm">
                {label}
            </span>
        </div>
    );
}
