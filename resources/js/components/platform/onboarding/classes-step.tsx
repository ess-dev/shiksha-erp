import Field, { selectClass } from '@/components/platform/onboarding/field';
import {
    gradeName,
    gradeRange,
    parseSections,
    sectionsFor,
} from '@/components/platform/onboarding/draft';
import type { StepProps } from '@/components/platform/onboarding/draft';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const FROM_CLASSES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const TO_CLASSES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

const modes = [
    {
        key: 'now',
        title: 'Set up classes now',
        text: 'Pick a class range and the usual sections; they are created at every campus.',
    },
    {
        key: 'skip',
        title: 'Skip for now',
        text: 'The school adds its own classes and sections later in School structure.',
    },
] as const;

export default function ClassesStep({ draft, update, errors }: StepProps) {
    const grades = gradeRange(draft);
    const total = grades.reduce(
        (sum, grade) => sum + sectionsFor(draft, grade).length,
        0,
    );
    const hasOwn = Object.keys(draft.classes.per_class).length > 0;

    return (
        <div>
            <fieldset>
                <legend className="sr-only">How to set up classes</legend>
                <div className="grid gap-2">
                    {modes.map((mode) => {
                        const checked = draft.classes.mode === mode.key;

                        return (
                            <label
                                key={mode.key}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 focus-within:ring-[3px] focus-within:ring-ring/40',
                                    checked
                                        ? 'border-2 border-primary bg-secondary/60'
                                        : 'border-border',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="classes-mode"
                                    className="mt-1 accent-primary"
                                    checked={checked}
                                    onChange={() =>
                                        update((d) => ({
                                            ...d,
                                            classes: {
                                                ...d.classes,
                                                mode: mode.key,
                                            },
                                        }))
                                    }
                                />
                                <span className="grid">
                                    <strong>{mode.title}</strong>
                                    <span className="text-[13px] text-muted-foreground">
                                        {mode.text}
                                    </span>
                                </span>
                            </label>
                        );
                    })}
                </div>
            </fieldset>

            {draft.classes.mode === 'now' && (
                <>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <Field id="class-from" label="From class">
                            {(props) => (
                                <select
                                    {...props}
                                    className={selectClass}
                                    value={draft.classes.from}
                                    onChange={(event) =>
                                        update((d) => ({
                                            ...d,
                                            classes: {
                                                ...d.classes,
                                                from: Number(
                                                    event.target.value,
                                                ),
                                            },
                                        }))
                                    }
                                >
                                    {FROM_CLASSES.map((grade) => (
                                        <option key={grade} value={grade}>
                                            {grade === 0
                                                ? 'Nursery / Pre-primary'
                                                : grade}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </Field>
                        <Field
                            id="class-to"
                            label="To class"
                            error={errors['class.range']}
                        >
                            {(props) => (
                                <select
                                    {...props}
                                    className={selectClass}
                                    value={draft.classes.to}
                                    onChange={(event) =>
                                        update(
                                            (d) => ({
                                                ...d,
                                                classes: {
                                                    ...d.classes,
                                                    to: Number(
                                                        event.target.value,
                                                    ),
                                                },
                                            }),
                                            ['class.range'],
                                        )
                                    }
                                >
                                    {TO_CLASSES.map((grade) => (
                                        <option key={grade} value={grade}>
                                            {grade}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </Field>
                        <div className="sm:col-span-2">
                            <Field
                                id="class-sections"
                                label="Usual sections in each class"
                                hint="Comma-separated. Change any class below if it has more or fewer sections."
                                error={errors['class.usual']}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={draft.classes.sections}
                                        placeholder="A, B, C"
                                        onChange={(event) =>
                                            update(
                                                (d) => ({
                                                    ...d,
                                                    classes: {
                                                        ...d.classes,
                                                        sections:
                                                            event.target.value,
                                                    },
                                                }),
                                                ['class.usual'],
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-2">
                        <h3 className="font-semibold">Sections by class</h3>
                        {hasOwn && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    update((d) => ({
                                        ...d,
                                        classes: {
                                            ...d.classes,
                                            per_class: {},
                                        },
                                    }))
                                }
                            >
                                Use the usual sections everywhere
                            </Button>
                        )}
                    </div>

                    <div className="mt-2 overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/40 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                <tr>
                                    <th scope="col" className="px-3 py-2.5">
                                        Class
                                    </th>
                                    <th scope="col" className="px-3 py-2.5">
                                        Sections
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2.5 text-right"
                                    >
                                        Count
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {grades.map((grade) => {
                                    const own =
                                        draft.classes.per_class[String(grade)];
                                    const error = errors[`class.${grade}`];

                                    return (
                                        <tr key={grade}>
                                            <td className="px-3 py-2 font-semibold whitespace-nowrap">
                                                {gradeName(grade)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <label
                                                    className="sr-only"
                                                    htmlFor={`class-${grade}`}
                                                >
                                                    Sections for{' '}
                                                    {gradeName(grade)}
                                                </label>
                                                <Input
                                                    id={`class-${grade}`}
                                                    aria-invalid={Boolean(
                                                        error,
                                                    )}
                                                    aria-describedby={
                                                        error
                                                            ? `class-${grade}-error`
                                                            : undefined
                                                    }
                                                    value={
                                                        own !== undefined
                                                            ? own
                                                            : sectionsFor(
                                                                  draft,
                                                                  grade,
                                                              ).join(', ')
                                                    }
                                                    onChange={(event) =>
                                                        update(
                                                            (d) => ({
                                                                ...d,
                                                                classes: {
                                                                    ...d.classes,
                                                                    per_class: {
                                                                        ...d
                                                                            .classes
                                                                            .per_class,
                                                                        [grade]:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                },
                                                            }),
                                                            [`class.${grade}`],
                                                        )
                                                    }
                                                />
                                                {error && (
                                                    <p
                                                        id={`class-${grade}-error`}
                                                        role="alert"
                                                        className="mt-1 text-[13px] text-destructive"
                                                    >
                                                        {error}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {
                                                    parseSections(
                                                        own ??
                                                            draft.classes
                                                                .sections,
                                                    ).length
                                                }
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <p className="mt-3 font-semibold">
                        Creates {total} {total === 1 ? 'section' : 'sections'}{' '}
                        per campus.
                    </p>
                </>
            )}
        </div>
    );
}
