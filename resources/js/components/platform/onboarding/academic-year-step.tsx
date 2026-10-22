import Field from '@/components/platform/onboarding/field';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Input } from '@/components/ui/input';

export default function AcademicYearStep({ draft, update, errors }: StepProps) {
    const set = (
        key: 'name' | 'starts_on' | 'ends_on',
        value: string,
        errorKey: string,
    ) =>
        update(
            (d) => ({ ...d, year: { ...d.year, [key]: value } }),
            [errorKey],
        );

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                id="year-name"
                label="Session name"
                error={errors['academic_year.name']}
            >
                {(props) => (
                    <Input
                        {...props}
                        value={draft.year.name}
                        maxLength={20}
                        placeholder="2026–27"
                        onChange={(event) =>
                            set(
                                'name',
                                event.target.value,
                                'academic_year.name',
                            )
                        }
                    />
                )}
            </Field>
            <div className="hidden sm:block" />
            <Field
                id="year-starts"
                label="Starts"
                hint="Most Indian schools start in April."
                error={errors['academic_year.starts_on']}
            >
                {(props) => (
                    <Input
                        {...props}
                        type="date"
                        value={draft.year.starts_on}
                        onChange={(event) =>
                            set(
                                'starts_on',
                                event.target.value,
                                'academic_year.starts_on',
                            )
                        }
                    />
                )}
            </Field>
            <Field
                id="year-ends"
                label="Ends"
                error={errors['academic_year.ends_on']}
            >
                {(props) => (
                    <Input
                        {...props}
                        type="date"
                        value={draft.year.ends_on}
                        onChange={(event) =>
                            set(
                                'ends_on',
                                event.target.value,
                                'academic_year.ends_on',
                            )
                        }
                    />
                )}
            </Field>
        </div>
    );
}
