import { useId } from 'react';

/**
 * The prototype's switch: a labelled checkbox drawn as an indigo track,
 * with an optional badge at the end of the row.
 */
export default function ModuleToggle({
    label,
    checked,
    onChange,
    badge,
    disabled = false,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    badge?: React.ReactNode;
    disabled?: boolean;
}) {
    const id = useId();

    return (
        <div className="flex items-center gap-2 border-b border-border py-2.5 last:border-b-0">
            <label
                htmlFor={id}
                className="flex min-w-0 flex-1 cursor-pointer items-center gap-3 text-sm"
            >
                <input
                    id={id}
                    type="checkbox"
                    role="switch"
                    className="peer sr-only"
                    checked={checked}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span
                    aria-hidden="true"
                    className="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/40 peer-disabled:opacity-60 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-5"
                />
                <span>{label}</span>
            </label>
            {badge}
        </div>
    );
}
