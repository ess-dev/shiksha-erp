import Field, { selectClass } from '@/components/platform/onboarding/field';
import { suggestSubdomain } from '@/components/platform/onboarding/draft';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Input } from '@/components/ui/input';

export default function OrganizationStep({
    draft,
    options,
    update,
    errors,
}: StepProps) {
    const org = draft.org;
    const set = (key: keyof typeof org, value: string, errorKey: string) =>
        update((d) => ({ ...d, org: { ...d.org, [key]: value } }), [errorKey]);

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field id="org-name" label="School name" error={errors['org.name']}>
                {(props) => (
                    <Input
                        {...props}
                        value={org.name}
                        placeholder="Holy Heart Convent School"
                        maxLength={120}
                        onChange={(event) =>
                            update(
                                (d) => ({
                                    ...d,
                                    org: {
                                        ...d.org,
                                        name: event.target.value,
                                        subdomain: d.org.subdomainEdited
                                            ? d.org.subdomain
                                            : suggestSubdomain(
                                                  event.target.value,
                                              ),
                                    },
                                }),
                                ['org.name', 'org.subdomain'],
                            )
                        }
                    />
                )}
            </Field>

            <Field id="org-board" label="Board" error={errors['org.board']}>
                {(props) => (
                    <select
                        {...props}
                        className={selectClass}
                        value={org.board}
                        onChange={(event) =>
                            set('board', event.target.value, 'org.board')
                        }
                    >
                        {options.boards.map((board) => (
                            <option key={board} value={board}>
                                {board}
                            </option>
                        ))}
                    </select>
                )}
            </Field>

            <Field id="org-city" label="City" error={errors['org.city']}>
                {(props) => (
                    <Input
                        {...props}
                        value={org.city}
                        placeholder="Patiala"
                        maxLength={80}
                        onChange={(event) =>
                            set('city', event.target.value, 'org.city')
                        }
                    />
                )}
            </Field>

            <Field id="org-state" label="State" error={errors['org.state']}>
                {(props) => (
                    <select
                        {...props}
                        className={selectClass}
                        value={org.state}
                        onChange={(event) =>
                            set('state', event.target.value, 'org.state')
                        }
                    >
                        {options.states.map((state) => (
                            <option key={state} value={state}>
                                {state}
                            </option>
                        ))}
                    </select>
                )}
            </Field>

            <Field
                id="org-contact-name"
                label="Contact person"
                error={errors['org.contact_name']}
            >
                {(props) => (
                    <Input
                        {...props}
                        value={org.contact_name}
                        placeholder="Principal or owner"
                        maxLength={120}
                        onChange={(event) =>
                            set(
                                'contact_name',
                                event.target.value,
                                'org.contact_name',
                            )
                        }
                    />
                )}
            </Field>

            <Field
                id="org-contact-phone"
                label="Mobile number"
                error={errors['org.contact_phone']}
            >
                {(props) => (
                    <Input
                        {...props}
                        value={org.contact_phone}
                        inputMode="numeric"
                        maxLength={10}
                        placeholder="98765 43210"
                        onChange={(event) =>
                            set(
                                'contact_phone',
                                event.target.value.replace(/\D/g, ''),
                                'org.contact_phone',
                            )
                        }
                    />
                )}
            </Field>

            <div className="sm:col-span-2">
                <Field
                    id="org-contact-email"
                    label="Work email"
                    hint="Invoices and platform notices go here."
                    error={errors['org.contact_email']}
                >
                    {(props) => (
                        <Input
                            {...props}
                            type="email"
                            value={org.contact_email}
                            placeholder="principal@school.edu.in"
                            maxLength={190}
                            onChange={(event) =>
                                set(
                                    'contact_email',
                                    event.target.value,
                                    'org.contact_email',
                                )
                            }
                        />
                    )}
                </Field>
            </div>

            <div className="sm:col-span-2">
                <Field
                    id="org-subdomain"
                    label="Web address"
                    hint={
                        <>
                            Where the school signs in:{' '}
                            <strong className="font-semibold">
                                {org.subdomain || 'your-school'}.
                                {options.base_domain}
                            </strong>
                            . Suggested from the name; lowercase letters, digits
                            and hyphens.
                        </>
                    }
                    error={errors['org.subdomain']}
                >
                    {(props) => (
                        <Input
                            {...props}
                            value={org.subdomain}
                            maxLength={63}
                            autoCapitalize="none"
                            spellCheck={false}
                            onChange={(event) =>
                                update(
                                    (d) => ({
                                        ...d,
                                        org: {
                                            ...d.org,
                                            subdomain: event.target.value
                                                .toLowerCase()
                                                .replace(/[^a-z0-9-]/g, ''),
                                            subdomainEdited: true,
                                        },
                                    }),
                                    ['org.subdomain'],
                                )
                            }
                        />
                    )}
                </Field>
            </div>
        </div>
    );
}
