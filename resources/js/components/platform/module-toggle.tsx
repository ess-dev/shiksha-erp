import { useId } from 'react';

/**
 * The prototype's switch: a labelled checkbox drawn as an indigo track.
 */
export default function ModuleToggle({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    const id = useId();

    return (
        <div className="border-b border-border py-2.5 last:border-b-0">
            <label
                htmlFor={id}
                className="flex cursor-pointer items-center gap-3 text-sm"
            >
                <input
                    id={id}
                    type="checkbox"
                    role="switch"
                    className="peer sr-only"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span
                    aria-hidden="true"
                    className="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/40 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-5"
                />
                <span>{label}</span>
            </label>
        </div>
    );
}
