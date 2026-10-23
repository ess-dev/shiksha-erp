import { cn } from '@/lib/utils';
import type { AttendanceStatus } from '@/types';

const OPTIONS: { value: AttendanceStatus; label: string; active: string }[] = [
    {
        value: 'present',
        label: 'Present',
        active: 'peer-checked:bg-green-600 peer-checked:text-white',
    },
    {
        value: 'absent',
        label: 'Absent',
        active: 'peer-checked:bg-destructive peer-checked:text-white',
    },
    {
        value: 'late',
        label: 'Late',
        active: 'peer-checked:bg-amber-500 peer-checked:text-white',
    },
];

/**
 * Present / Absent / Late for one student. Real radio inputs, so the keyboard
 * and screen readers work; the chosen option is filled and named, never colour only.
 */
export default function StatusToggle({
    studentUlid,
    studentName,
    value,
    disabled,
    onChange,
}: {
    studentUlid: string;
    studentName: string;
    value: AttendanceStatus | null;
    disabled: boolean;
    onChange: (status: AttendanceStatus) => void;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={`Attendance for ${studentName}`}
            className={cn(
                'inline-flex shrink-0 overflow-hidden rounded-md border',
                disabled && 'opacity-70',
            )}
        >
            {OPTIONS.map((option) => {
                const id = `m-${studentUlid}-${option.value}`;

                return (
                    <span key={option.value} className="flex">
                        <input
                            id={id}
                            type="radio"
                            name={`m-${studentUlid}`}
                            value={option.value}
                            checked={value === option.value}
                            disabled={disabled}
                            onChange={() => onChange(option.value)}
                            className="peer sr-only"
                        />
                        <label
                            htmlFor={id}
                            className={cn(
                                'flex min-h-9 min-w-[4.25rem] cursor-pointer items-center justify-center border-l px-3 text-sm font-medium transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-disabled:cursor-default first:border-l-0 hover:bg-muted peer-disabled:hover:bg-transparent',
                                option.active,
                            )}
                        >
                            {option.label}
                        </label>
                    </span>
                );
            })}
        </div>
    );
}
