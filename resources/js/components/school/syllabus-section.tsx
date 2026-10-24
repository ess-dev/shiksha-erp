import { router } from '@inertiajs/react';
import { useState } from 'react';
import SyllabusController from '@/actions/App/Http/Controllers/School/SyllabusController';
import InputError from '@/components/input-error';
import Field from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { formatCalendarDate } from '@/lib/format';
import type { SyllabusDetail } from '@/types';

/**
 * One section: tick chapters as they are finished, mark the one in class
 * now, and leave a note for the section's parents.
 */
export default function SyllabusSection({
    detail,
}: {
    detail: SyllabusDetail;
}) {
    const chapters = detail.class_syllabus.is_saved
        ? detail.class_syllabus.chapters
        : [];
    const progress = detail.progress;

    const [done, setDone] = useState<Set<number>>(
        new Set(progress.done_chapters),
    );
    const [current, setCurrent] = useState<number | null>(
        progress.current_chapter,
    );
    const [periods, setPeriods] = useState(
        progress.periods_per_week === null
            ? ''
            : String(progress.periods_per_week),
    );
    const [note, setNote] = useState(progress.note);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const toggle = (index: number, checked: boolean) => {
        setDone((previous) => {
            const next = new Set(previous);

            if (checked) {
                next.add(index);
            } else {
                next.delete(index);
            }

            return next;
        });
    };

    const save = () => {
        router.put(
            SyllabusController.updateSection.url({
                section: detail.section_ulid,
                subject: detail.subject_ulid,
            }),
            {
                done: [...done].sort((a, b) => a - b),
                current,
                periods: periods === '' ? null : Number(periods),
                note,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setErrors({}),
                onError: setErrors,
            },
        );
    };

    return (
        <div className="grid gap-4 p-4">
            <p className="text-sm text-muted-foreground">
                Only parents of{' '}
                <strong className="text-foreground">
                    {detail.section_name}
                </strong>{' '}
                see this. Tick chapters as you finish them.
            </p>

            {chapters.length === 0 ? (
                <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    No chapter list is saved for {detail.class_name}{' '}
                    {detail.subject_name} this year. Add it on the Whole class
                    tab, then tick chapters here.
                </p>
            ) : (
                <ol className="grid gap-2">
                    {chapters.map((chapter, index) => {
                        const isDone = done.has(index);
                        const isCurrent = current === index;

                        return (
                            <li
                                key={`${index}-${chapter}`}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm',
                                    isDone &&
                                        'border-green-300 bg-green-50 text-green-950 dark:border-green-800 dark:bg-green-950/40 dark:text-green-100',
                                    !isDone &&
                                        isCurrent &&
                                        'border-primary/40 bg-primary/5',
                                )}
                            >
                                <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={isDone}
                                        onChange={(event) =>
                                            toggle(index, event.target.checked)
                                        }
                                        className="size-4 shrink-0 accent-primary"
                                    />
                                    <span className="min-w-0 flex-1 break-words">
                                        {index + 1}. {chapter}
                                        {isDone && (
                                            <span className="sr-only">
                                                {' '}
                                                (done)
                                            </span>
                                        )}
                                    </span>
                                </label>
                                <label className="flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                    <input
                                        type="radio"
                                        name="syllabus-current"
                                        checked={isCurrent}
                                        onChange={() => setCurrent(index)}
                                        className="size-4 accent-primary"
                                    />
                                    Now
                                </label>
                            </li>
                        );
                    })}
                </ol>
            )}

            {current !== null && (
                <div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setCurrent(null)}
                    >
                        Clear &ldquo;Now&rdquo;
                    </Button>
                </div>
            )}

            <div className="grid gap-4 sm:max-w-xs">
                <Field
                    id="syllabus-periods"
                    label="Periods a week"
                    error={errors.periods}
                >
                    <Input
                        id="syllabus-periods"
                        type="number"
                        min={1}
                        max={12}
                        value={periods}
                        onChange={(event) => setPeriods(event.target.value)}
                    />
                </Field>
            </div>

            <Field
                id="syllabus-note"
                label={`Note for parents of ${detail.section_name}`}
                error={errors.note}
            >
                <textarea
                    id="syllabus-note"
                    rows={3}
                    maxLength={300}
                    value={note}
                    placeholder="Fractions test on 28 October. Practise Exercise 7.3 at home."
                    onChange={(event) => setNote(event.target.value)}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
            </Field>

            <InputError message={errors.syllabus} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-muted-foreground">
                    {progress.updated_on !== null &&
                        `Last updated ${formatCalendarDate(progress.updated_on)}${progress.updated_by === null ? '' : ` by ${progress.updated_by}`}`}
                </p>
                <Button type="button" disabled={processing} onClick={save}>
                    {processing && <Spinner />}
                    Save for {detail.section_name}
                </Button>
            </div>
        </div>
    );
}
