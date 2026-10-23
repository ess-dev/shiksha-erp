import { Form, Link, router } from '@inertiajs/react';
import ClassTeacherController from '@/actions/App/Http/Controllers/School/ClassTeacherController';
import SectionController from '@/actions/App/Http/Controllers/School/SectionController';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/school/structure';
import type {
    AcademicYear,
    Campus,
    PaginatedSections,
    SchoolClass,
    StaffOption,
    StructureFilters,
} from '@/types';

type Props = {
    sections: PaginatedSections;
    years: AcademicYear[];
    campuses: Campus[];
    classes: SchoolClass[];
    filters: StructureFilters;
    staffOptions: StaffOption[];
    canManage: boolean;
};

export default function SectionsCard({
    sections,
    years,
    campuses,
    classes,
    filters,
    staffOptions,
    canManage,
}: Props) {
    const currentYear = years.find((year) => year.is_current)?.ulid ?? '';

    const filter = (name: keyof StructureFilters, value: string) => {
        router.get(
            index.url(),
            { ...filters, [name]: value === '' ? undefined : value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Sections</CardTitle>
                <CardDescription>
                    A section is a class at one campus for one academic year,
                    such as Class 5 A.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Field id="filter-year" label="Academic year">
                        <select
                            id="filter-year"
                            value={filters.year ?? currentYear}
                            onChange={(event) =>
                                filter('year', event.target.value)
                            }
                            className={selectClassName}
                        >
                            {years.map((year) => (
                                <option key={year.ulid} value={year.ulid}>
                                    {year.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="filter-campus" label="Campus">
                        <select
                            id="filter-campus"
                            value={filters.campus ?? ''}
                            onChange={(event) =>
                                filter('campus', event.target.value)
                            }
                            className={selectClassName}
                        >
                            <option value="">All campuses</option>
                            {campuses.map((campus) => (
                                <option key={campus.ulid} value={campus.ulid}>
                                    {campus.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="filter-class" label="Class">
                        <select
                            id="filter-class"
                            value={filters.class ?? ''}
                            onChange={(event) =>
                                filter('class', event.target.value)
                            }
                            className={selectClassName}
                        >
                            <option value="">All classes</option>
                            {classes.map((schoolClass) => (
                                <option
                                    key={schoolClass.ulid}
                                    value={schoolClass.ulid}
                                >
                                    {schoolClass.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                {sections.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No sections match these filters.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Class
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Section
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Campus
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Class teacher
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Capacity
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sections.data.map((section) => (
                                    <tr
                                        key={section.ulid}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            {section.class.name}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {section.name}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {section.campus.name}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {canManage ? (
                                                <select
                                                    aria-label={`Class teacher of ${section.class.name} ${section.name}`}
                                                    value={
                                                        section.class_teacher
                                                            ?.ulid ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        router.put(
                                                            ClassTeacherController.update.url(
                                                                section.ulid,
                                                            ),
                                                            {
                                                                staff:
                                                                    event.target
                                                                        .value ||
                                                                    null,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className={selectClassName}
                                                >
                                                    <option value="">
                                                        Not assigned
                                                    </option>
                                                    {staffOptions.map(
                                                        (person) => (
                                                            <option
                                                                key={
                                                                    person.ulid
                                                                }
                                                                value={
                                                                    person.ulid
                                                                }
                                                            >
                                                                {person.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : (
                                                (section.class_teacher?.name ??
                                                'Not assigned')
                                            )}
                                        </td>
                                        <td className="py-2 text-right">
                                            {section.capacity ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {sections.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {sections.current_page} of {sections.last_page}{' '}
                            ({sections.total} sections)
                        </span>
                        <div className="flex gap-2">
                            {sections.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={sections.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {sections.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={sections.next_page_url}
                                        preserveScroll
                                    >
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {canManage && (
                    <Form
                        {...SectionController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess={['name', 'capacity']}
                        className="grid gap-4 border-t pt-6 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="section-year"
                                    label="Academic year"
                                    error={errors.academic_year}
                                >
                                    <select
                                        id="section-year"
                                        name="academic_year"
                                        defaultValue={currentYear}
                                        className={selectClassName}
                                    >
                                        {years.map((year) => (
                                            <option
                                                key={year.ulid}
                                                value={year.ulid}
                                            >
                                                {year.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    id="section-campus"
                                    label="Campus"
                                    error={errors.campus}
                                >
                                    <select
                                        id="section-campus"
                                        name="campus"
                                        className={selectClassName}
                                    >
                                        {campuses
                                            .filter(
                                                (campus) =>
                                                    campus.status === 'active',
                                            )
                                            .map((campus) => (
                                                <option
                                                    key={campus.ulid}
                                                    value={campus.ulid}
                                                >
                                                    {campus.name}
                                                </option>
                                            ))}
                                    </select>
                                </Field>
                                <Field
                                    id="section-class"
                                    label="Class"
                                    error={errors.class}
                                >
                                    <select
                                        id="section-class"
                                        name="class"
                                        className={selectClassName}
                                    >
                                        {classes.map((schoolClass) => (
                                            <option
                                                key={schoolClass.ulid}
                                                value={schoolClass.ulid}
                                            >
                                                {schoolClass.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    id="section-name"
                                    label="Section name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="section-name"
                                        name="name"
                                        placeholder="A"
                                    />
                                </Field>
                                <Field
                                    id="section-capacity"
                                    label="Capacity (optional)"
                                    error={errors.capacity}
                                >
                                    <Input
                                        id="section-capacity"
                                        name="capacity"
                                        type="number"
                                        min={1}
                                        placeholder="40"
                                    />
                                </Field>
                                <div className="sm:col-span-2">
                                    <InputError message={errors.structure} />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-2"
                                    >
                                        Add section
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
