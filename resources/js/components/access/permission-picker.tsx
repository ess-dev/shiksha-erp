import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { PermissionOption } from '@/types/access';

const riskLabel = {
    normal: null,
    sensitive: 'Sensitive',
    critical: 'Critical',
};

export function hasCritical(
    keys: string[],
    options: PermissionOption[],
): boolean {
    return options.some(
        (option) => option.risk === 'critical' && keys.includes(option.key),
    );
}

/**
 * Permissions grouped by module. A permission the viewer does not hold, or
 * an owner-only one in a non-owner role, cannot be switched on (the server
 * refuses it as well); one the role already has can always be switched off.
 */
export default function PermissionPicker({
    options,
    selected,
    isOwnerRole,
    idPrefix,
    onChange,
}: {
    options: PermissionOption[];
    selected: string[];
    isOwnerRole: boolean;
    idPrefix: string;
    onChange: (keys: string[]) => void;
}) {
    const modules = Array.from(new Set(options.map((o) => o.module)));

    const toggle = (key: string, on: boolean) =>
        onChange(on ? [...selected, key] : selected.filter((k) => k !== key));

    return (
        <div className="grid gap-4">
            {modules.map((module) => (
                <fieldset key={module} className="grid gap-2">
                    <legend className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {module}
                    </legend>
                    {options
                        .filter((option) => option.module === module)
                        .map((option) => {
                            const checked = selected.includes(option.key);
                            const blocked =
                                !checked &&
                                (!option.held_by_you ||
                                    (option.is_owner_only && !isOwnerRole));
                            const id = `${idPrefix}-${option.key}`;
                            const risk = riskLabel[option.risk];

                            return (
                                <div
                                    key={option.key}
                                    className="flex items-start gap-3"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={checked}
                                        disabled={blocked}
                                        onCheckedChange={(value) =>
                                            toggle(option.key, value === true)
                                        }
                                        className="mt-0.5"
                                    />
                                    <label
                                        htmlFor={id}
                                        className="grid flex-1 gap-0.5 text-sm"
                                    >
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {option.description}
                                            </span>
                                            {risk && (
                                                <Badge
                                                    variant={
                                                        option.risk ===
                                                        'critical'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {risk}
                                                </Badge>
                                            )}
                                            {option.is_owner_only && (
                                                <Badge variant="outline">
                                                    Owner only
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {option.key}
                                            {blocked &&
                                                (option.held_by_you
                                                    ? ' · only an owner role can have this'
                                                    : ' · you do not hold this permission')}
                                        </span>
                                    </label>
                                </div>
                            );
                        })}
                </fieldset>
            ))}
        </div>
    );
}
