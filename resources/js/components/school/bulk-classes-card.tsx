import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import SchoolClassController from '@/actions/App/Http/Controllers/School/SchoolClassController';
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
import type {
    AcademicYear,
    Campus,
    ClassLadderStep,
    SchoolClass,
} from '@/types';

type Props = {
    ladder: ClassLadderStep[];
    classes: SchoolClass[];
    years: AcademicYear[];
    campuses: Campus[];
};

const MAX_SECTIONS = 12;

const sectionNames = (count: number, letters: boolean): string[] =>
    Array.from({ length: count }, (_, index) =>
        letters ? String.fromCharCode(65 + index) : String(index + 1),
    );

// illustrative - verify: quick ranges matching common school stages
const presets: { label: string; from: number; to: number }[] = [
    { label: 'Nursery to 12', from: -2, to: 12 },
    { label: 'Class 1 to 12', from: 1, to: 12 },
    { label: 'Primary (1 to 5)', from: 1, to: 5 },
    { label: 'Middle (6 to 8)', from: 6, to: 8 },
    { label: 'Senior (9 to 12)', from: 9, to: 12 },
];

export default function BulkClassesCard({
    ladder,
    classes,
    years,
    campuses,
}: Props) {
    const activeCampuses = campuses.filter(
        (campus) => campus.status === 'active',
    );
    const [perClass, setPerClass] = useState(false);

    const form = useForm<{
        academic_year: string;
        campus: string;
        from_level: number;
        to_level: number;
        sections: number;
        naming: 'letters' | 'numbers';
        capacity: string;
        overrides: Record<string, number>;
    }>({
        academic_year: years.find((year) => year.is_current)?.ulid ?? '',
        campus: activeCampuses[0]?.ulid ?? '',
        from_level: ladder[3]?.level ?? ladder[0]?.level ?? 0,
        to_level: ladder[ladder.length - 1]?.level ?? 0,
        sections: 3,
        naming: 'letters',
        capacity: '',
        overrides: {},
    });
    const { data, setData } = form;

    const existingLevels = useMemo(
        () => new Set(classes.map((schoolClass) => schoolClass.level)),
        [classes],
    );

    const rows = ladder
        .filter(
            (step) =>
                step.level >= data.from_level && step.level <= data.to_level,
        )
        .map((step) => {
            const count = perClass
                ? (data.overrides[step.level] ?? data.sections)
                : data.sections;

            return {
                ...step,
                count,
                exists: existingLevels.has(step.level),
                names: sectionNames(count, data.naming === 'letters'),
            };
        });

    const newClasses = rows.filter((row) => !row.exists).length;
    const totalSections = rows.reduce((sum, row) => sum + row.count, 0);

    const submit = () => {
        form.transform((current) => ({
            ...current,
            overrides: perClass ? current.overrides : {},
        }));
        form.post(SchoolClassController.storeBulk.url(), {
            preserveScroll: true,
        });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Add classes in bulk</CardTitle>
                <CardDescription>
                    Pick a range of classes and how many sections each gets.
                    Classes and sections that already exist are skipped.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="flex flex-wrap gap-2">
                    {presets.map((preset) => (
                        <Button
                            key={preset.label}
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setData((current) => ({
                                    ...current,
                                    from_level: preset.from,
                                    to_level: preset.to,
                                }));
                            }}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field
                        id="bulk-from"
                        label="From class"
                        error={errors.from_level}
                    >
                        <select
                            id="bulk-from"
                            value={data.from_level}
                            onChange={(event) => {
                                const level = Number(event.target.value);
                                setData((current) => ({
                                    ...current,
                                    from_level: level,
                                    to_level: Math.max(current.to_level, level),
                                }));
                            }}
                            className={selectClassName}
                        >
                            {ladder.map((step) => (
                                <option key={step.level} value={step.level}>
                                    {step.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="bulk-to"
                        label="To class"
                        error={errors.to_level}
                    >
                        <select
                            id="bulk-to"
                            value={data.to_level}
                            onChange={(event) => {
                                const level = Number(event.target.value);
                                setData((current) => ({
                                    ...current,
                                    to_level: level,
                                    from_level: Math.min(
                                        current.from_level,
                                        level,
                                    ),
                                }));
                            }}
                            className={selectClassName}
                        >
                            {ladder.map((step) => (
                                <option key={step.level} value={step.level}>
                                    {step.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="bulk-sections"
                        label="Sections per class"
                        error={errors.sections}
                    >
                        <Input
                            id="bulk-sections"
                            type="number"
                            min={1}
                            max={MAX_SECTIONS}
                            value={data.sections}
                            onChange={(event) =>
                                setData(
                                    'sections',
                                    Math.min(
                                        MAX_SECTIONS,
                                        Math.max(
                                            1,
                                            Number(event.target.value) || 1,
                                        ),
                                    ),
                                )
                            }
                        />
                    </Field>
                    <Field
                        id="bulk-naming"
                        label="Section names"
                        error={errors.naming}
                    >
                        <select
                            id="bulk-naming"
                            value={data.naming}
                            onChange={(event) =>
                                setData(
                                    'naming',
                                    event.target.value as 'letters' | 'numbers',
                                )
                            }
                            className={selectClassName}
                        >
                            <option value="letters">A, B, C…</option>
                            <option value="numbers">1, 2, 3…</option>
                        </select>
                    </Field>
                    <Field
                        id="bulk-year"
                        label="Academic year"
                        error={errors.academic_year}
                    >
                        <select
                            id="bulk-year"
                            value={data.academic_year}
                            onChange={(event) =>
                                setData('academic_year', event.target.value)
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
                    <Field
                        id="bulk-campus"
                        label="Campus"
                        error={errors.campus}
                    >
                        <select
                            id="bulk-campus"
                            value={data.campus}
                            onChange={(event) =>
                                setData('campus', event.target.value)
                            }
                            className={selectClassName}
                        >
                            {activeCampuses.map((campus) => (
                                <option key={campus.ulid} value={campus.ulid}>
                                    {campus.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="bulk-capacity"
                        label="Capacity per section (optional)"
                        error={errors.capacity}
                    >
                        <Input
                            id="bulk-capacity"
                            type="number"
                            min={1}
                            placeholder="40"
                            value={data.capacity}
                            onChange={(event) =>
                                setData('capacity', event.target.value)
                            }
                        />
                    </Field>
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={perClass}
                        onChange={(event) => setPerClass(event.target.checked)}
                    />
                    Different section count for some classes
                </label>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Preview</h3>
                    {rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No class in this range.
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
                                            Sections
                                        </th>
                                        <th className="py-2 font-medium">
                                            Class
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.level}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                {row.name}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {perClass ? (
                                                    <Input
                                                        aria-label={`Sections for ${row.name}`}
                                                        type="number"
                                                        min={1}
                                                        max={MAX_SECTIONS}
                                                        className="mr-3 inline-block h-8 w-16"
                                                        value={row.count}
                                                        onChange={(event) =>
                                                            setData(
                                                                'overrides',
                                                                {
                                                                    ...data.overrides,
                                                                    [row.level]:
                                                                        Math.min(
                                                                            MAX_SECTIONS,
                                                                            Math.max(
                                                                                1,
                                                                                Number(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                    1,
                                                                            ),
                                                                        ),
                                                                },
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                                {row.names.join(', ')}
                                            </td>
                                            <td className="py-2 text-muted-foreground">
                                                {row.exists
                                                    ? 'Exists, missing sections added'
                                                    : 'New'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <InputError message={errors.structure} />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="text-sm text-muted-foreground">
                        {newClasses} new classes, up to {totalSections} sections
                        (sections already present are skipped)
                    </span>
                    <Button
                        type="button"
                        disabled={
                            form.processing ||
                            rows.length === 0 ||
                            data.campus === '' ||
                            data.academic_year === ''
                        }
                        onClick={submit}
                    >
                        Create classes
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
