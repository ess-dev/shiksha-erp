import { Form } from '@inertiajs/react';
import Field from '@/components/school/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AcademicYearController from '@/actions/App/Http/Controllers/School/AcademicYearController';
import InputError from '@/components/input-error';
import { formatCalendarDate } from '@/lib/format';
import type { AcademicYear } from '@/types';

export default function AcademicYearsCard({
    years,
    canManage,
}: {
    years: AcademicYear[];
    canManage: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Academic years</CardTitle>
                <CardDescription>
                    Exactly one year is current. Years cannot overlap.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {years.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No academic year has been added yet.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {years.map((year) => (
                            <li
                                key={year.ulid}
                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                            >
                                <div>
                                    <p className="font-medium">{year.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatCalendarDate(year.starts_on)} to{' '}
                                        {formatCalendarDate(year.ends_on)}
                                    </p>
                                </div>
                                {year.is_current ? (
                                    <Badge>Current</Badge>
                                ) : (
                                    canManage && (
                                        <Form
                                            {...AcademicYearController.makeCurrent.form(
                                                { academicYear: year.ulid },
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Make current
                                                </Button>
                                            )}
                                        </Form>
                                    )
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canManage && (
                    <Form
                        {...AcademicYearController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-4 sm:grid-cols-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="year-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="year-name"
                                        name="name"
                                        placeholder="2026-27"
                                    />
                                </Field>
                                <Field
                                    id="year-starts"
                                    label="First day"
                                    error={errors.starts_on}
                                >
                                    <Input
                                        id="year-starts"
                                        name="starts_on"
                                        type="date"
                                    />
                                </Field>
                                <Field
                                    id="year-ends"
                                    label="Last day"
                                    error={errors.ends_on}
                                >
                                    <Input
                                        id="year-ends"
                                        name="ends_on"
                                        type="date"
                                    />
                                </Field>
                                <div className="sm:col-span-3">
                                    <InputError message={errors.structure} />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-2"
                                    >
                                        Add academic year
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
