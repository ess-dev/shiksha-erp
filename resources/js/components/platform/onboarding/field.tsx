import { Label } from '@/components/ui/label';

/** Same look as the shared Input, for a native select. */
export const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-invalid:border-destructive';

/**
 * A labelled field with an optional hint and an error message, wired with
 * aria-describedby. `children` receives the props the control needs.
 */
export default function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: React.ReactNode;
    error?: string;
    children: (props: {
        id: string;
        'aria-invalid': boolean;
        'aria-describedby': string | undefined;
    }) => React.ReactNode;
}) {
    const describedBy = [
        hint ? `${id}-hint` : null,
        error ? `${id}-error` : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="grid gap-1.5" data-field={id}>
            <Label htmlFor={id}>{label}</Label>
            {children({
                id,
                'aria-invalid': Boolean(error),
                'aria-describedby': describedBy || undefined,
            })}
            {hint && (
                <p
                    id={`${id}-hint`}
                    className="text-[13px] text-muted-foreground"
                >
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-[13px] text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
