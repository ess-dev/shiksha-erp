import { Plus, Trash2 } from 'lucide-react';
import Field, { selectClass } from '@/components/platform/onboarding/field';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function StaffStep({
    draft,
    options,
    update,
    errors,
}: StepProps) {
    const change = (
        index: number,
        key: 'name' | 'email' | 'role',
        value: string,
    ) =>
        update(
            (d) => ({
                ...d,
                staff: d.staff.map((person, i) =>
                    i === index ? { ...person, [key]: value } : person,
                ),
            }),
            [`staff.${index}.${key}`, 'staff'],
        );

    return (
        <div>
            <div className="grid gap-4">
                {draft.staff.map((person, index) => {
                    const role = options.staff_roles.find(
                        (option) => option.key === person.role,
                    );

                    return (
                        <div key={index}>
                            <div className="grid items-start gap-3 sm:grid-cols-[2fr_2fr_1.4fr_auto]">
                                <Field
                                    id={`staff-name-${index}`}
                                    label="Name"
                                    error={errors[`staff.${index}.name`]}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            value={person.name}
                                            maxLength={120}
                                            onChange={(event) =>
                                                change(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    id={`staff-email-${index}`}
                                    label="Email"
                                    error={errors[`staff.${index}.email`]}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="email"
                                            value={person.email}
                                            maxLength={190}
                                            onChange={(event) =>
                                                change(
                                                    index,
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    id={`staff-role-${index}`}
                                    label="Role"
                                    error={errors[`staff.${index}.role`]}
                                >
                                    {(props) => (
                                        <select
                                            {...props}
                                            className={selectClass}
                                            value={person.role}
                                            onChange={(event) =>
                                                change(
                                                    index,
                                                    'role',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {options.staff_roles.map(
                                                (option) => (
                                                    <option
                                                        key={option.key}
                                                        value={option.key}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    )}
                                </Field>
                                <div className="grid gap-1.5">
                                    <span
                                        className="hidden h-[14px] sm:block"
                                        aria-hidden="true"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove row ${index + 1}`}
                                        disabled={draft.staff.length < 2}
                                        onClick={() =>
                                            update((d) => ({
                                                ...d,
                                                staff: d.staff.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            }))
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>
                            {role?.requires_two_step && (
                                <p className="mt-1 text-[13px] text-muted-foreground">
                                    {role.label} has sensitive permissions, so
                                    they will be asked to set up two-step
                                    sign-in at their first sign-in.
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-3"
                disabled={draft.staff.length >= 30}
                onClick={() =>
                    update((d) => ({
                        ...d,
                        staff: [
                            ...d.staff,
                            { name: '', email: '', role: 'teacher' },
                        ],
                    }))
                }
            >
                <Plus aria-hidden="true" />
                Add person
            </Button>

            {errors.staff && (
                <p role="alert" className="mt-2 text-[13px] text-destructive">
                    {errors.staff}
                </p>
            )}

            <p className="mt-3 text-[13px] text-muted-foreground">
                Teachers can also be added later, in bulk, by the school’s
                Principal. Each person gets a set-password link on the last
                screen; no email is sent yet.
            </p>
        </div>
    );
}
