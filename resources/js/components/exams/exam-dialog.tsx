import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import ExamController from '@/actions/App/Http/Controllers/Exams/ExamController';
import { addDays, isSchoolDay } from '@/components/exams/exam-calendar';
import ToggleRow from '@/components/exams/toggle-row';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { ExamFormOptions, ExamKind, ExamRow } from '@/types';

type SheetRow = { subject_ulid: string; held_on: string; begins_at: string };

function CheckboxItem({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-1.5">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <Label htmlFor={id} className="font-normal">
                {label}
            </Label>
        </div>
    );
}

export default function ExamDialog({
    exam,
    options,
    onClose,
}: {
    exam: ExamRow | null;
    options: ExamFormOptions;
    onClose: () => void;
}) {
    const isNew = exam === null;
    const allClassUlids = options.classes.map((item) => item.ulid);
    const allCampusUlids = options.campuses.map((item) => item.ulid);

    const [name, setName] = useState(exam?.name ?? '');
    const [type, setType] = useState<ExamKind>(exam?.type ?? 'unit_test');
    const [maxMarks, setMaxMarks] = useState(String(exam?.max_marks ?? 25));
    const [maxTouched, setMaxTouched] = useState(false);
    const [startsOn, setStartsOn] = useState(
        exam?.starts_on ?? options.default_starts_on,
    );
    const [endsOn, setEndsOn] = useState(
        exam !== null && exam.ends_on !== exam.starts_on ? exam.ends_on : '',
    );
    const [classes, setClasses] = useState<Set<string>>(
        new Set(
            exam === null || exam.all_classes
                ? allClassUlids
                : exam.class_ulids,
        ),
    );
    const [campuses, setCampuses] = useState<Set<string>>(
        new Set(
            exam === null || exam.all_campuses
                ? allCampusUlids
                : exam.campus_ulids,
        ),
    );
    const [sheet, setSheet] = useState<SheetRow[]>(
        (exam?.date_sheet ?? []).map((row) => ({
            subject_ulid: row.subject_ulid,
            held_on: row.held_on,
            begins_at: row.begins_at ?? '',
        })),
    );
    const [instructions, setInstructions] = useState(exam?.instructions ?? '');
    const [showOnCalendar, setShowOnCalendar] = useState(
        exam?.show_on_calendar ?? true,
    );
    const [notify, setNotify] = useState(isNew);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const allClassesChecked =
        allClassUlids.length > 0 && allClassUlids.every((u) => classes.has(u));
    const allCampusesChecked =
        allCampusUlids.length > 0 &&
        allCampusUlids.every((u) => campuses.has(u));

    const subjects = useMemo(() => {
        const seen = new Map<string, string>();

        for (const entry of options.subjects_by_class) {
            if (classes.has(entry.class_ulid)) {
                for (const subject of entry.subjects) {
                    seen.set(subject.ulid, subject.name);
                }
            }
        }

        return [...seen.entries()].map(([ulid, subjectName]) => ({
            ulid,
            name: subjectName,
        }));
    }, [options.subjects_by_class, classes]);

    const subjectName = (ulid: string) =>
        subjects.find((subject) => subject.ulid === ulid)?.name ??
        exam?.date_sheet.find((row) => row.subject_ulid === ulid)
            ?.subject_name ??
        '';

    const toggle = (
        set: Set<string>,
        update: (next: Set<string>) => void,
        ulid: string,
        on: boolean,
    ) => {
        const next = new Set(set);

        if (on) {
            next.add(ulid);
        } else {
            next.delete(ulid);
        }

        update(next);
    };

    const changeType = (value: ExamKind) => {
        setType(value);

        const defaults = options.types.find((item) => item.value === value);

        if (isNew && !maxTouched && defaults !== undefined) {
            setMaxMarks(String(defaults.default_max_marks));
        }
    };

    const addRow = () => {
        const used = new Set(sheet.map((row) => row.subject_ulid));
        const next = subjects.find((subject) => !used.has(subject.ulid));

        setSheet([
            ...sheet,
            {
                subject_ulid: next?.ulid ?? subjects[0]?.ulid ?? '',
                held_on: startsOn,
                begins_at: '',
            },
        ]);
    };

    const fillOnePerDay = () => {
        if (startsOn === '') {
            setErrors({ starts_on: 'Pick a start date first.' });

            return;
        }

        let day = startsOn;
        const rows: SheetRow[] = [];

        for (const subject of subjects) {
            for (let i = 0; i < 400 && !isSchoolDay(options, day); i++) {
                day = addDays(day, 1);
            }

            rows.push({
                subject_ulid: subject.ulid,
                held_on: day,
                begins_at: '08:30',
            });
            day = addDays(day, 1);
        }

        setSheet(rows);

        if (rows.length > 0) {
            setEndsOn(rows[rows.length - 1].held_on);
        }
    };

    const updateRow = (index: number, patch: Partial<SheetRow>) =>
        setSheet(
            sheet.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const data = {
            name,
            type,
            max_marks: maxMarks,
            starts_on: startsOn,
            ends_on: endsOn === '' ? null : endsOn,
            all_classes: allClassesChecked && options.may_choose_all_classes,
            class_ulids: [...classes],
            all_campuses: allCampusesChecked && options.may_choose_all_campuses,
            campus_ulids: [...campuses],
            date_sheet: sheet
                .filter((row) => row.subject_ulid !== '')
                .map((row) => ({
                    ...row,
                    begins_at: row.begins_at === '' ? null : row.begins_at,
                })),
            instructions: instructions === '' ? null : instructions,
            show_on_calendar: showOnCalendar,
            notify_parents: notify,
        };

        const visit = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (bag: Record<string, string>) => setErrors(bag),
        };

        if (isNew) {
            router.post(ExamController.store.url(), data, visit);
        } else {
            router.put(ExamController.update.url(exam.ulid), data, visit);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isNew ? 'New unit test or exam' : `Edit ${exam.name}`}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <Field id="exam-name" label="Name" error={errors.name}>
                        <Input
                            id="exam-name"
                            value={name}
                            maxLength={60}
                            placeholder="December Unit Test"
                            autoFocus
                            onChange={(event) => setName(event.target.value)}
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="exam-type" label="Type" error={errors.type}>
                            <select
                                id="exam-type"
                                className={selectClassName}
                                value={type}
                                onChange={(event) =>
                                    changeType(event.target.value as ExamKind)
                                }
                            >
                                {options.types.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            id="exam-max"
                            label="Maximum marks"
                            error={errors.max_marks}
                        >
                            <Input
                                id="exam-max"
                                type="number"
                                min={1}
                                max={200}
                                step={1}
                                value={maxMarks}
                                disabled={exam?.max_marks_locked === true}
                                onChange={(event) => {
                                    setMaxTouched(true);
                                    setMaxMarks(event.target.value);
                                }}
                            />
                            {exam?.max_marks_locked === true && (
                                <p className="text-xs text-muted-foreground">
                                    Locked: marks are already entered.
                                </p>
                            )}
                        </Field>
                        <Field
                            id="exam-start"
                            label="Starts"
                            error={errors.starts_on}
                        >
                            <Input
                                id="exam-start"
                                type="date"
                                value={startsOn}
                                onChange={(event) =>
                                    setStartsOn(event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="exam-end"
                            label="Ends"
                            error={errors.ends_on}
                        >
                            <Input
                                id="exam-end"
                                type="date"
                                value={endsOn}
                                onChange={(event) =>
                                    setEndsOn(event.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave empty for a one-day test.
                            </p>
                        </Field>
                    </div>

                    <fieldset>
                        <legend className="mb-2 text-sm font-medium">
                            Classes
                        </legend>
                        <div className="flex flex-wrap gap-x-4 gap-y-2">
                            {options.may_choose_all_classes && (
                                <CheckboxItem
                                    id="exam-all-classes"
                                    label="All classes"
                                    checked={allClassesChecked}
                                    onChange={(on) =>
                                        setClasses(
                                            new Set(on ? allClassUlids : []),
                                        )
                                    }
                                />
                            )}
                            {options.classes.map((item) => (
                                <CheckboxItem
                                    key={item.ulid}
                                    id={`exam-class-${item.ulid}`}
                                    label={item.name.replace(/^Class /, '')}
                                    checked={classes.has(item.ulid)}
                                    onChange={(on) =>
                                        toggle(
                                            classes,
                                            setClasses,
                                            item.ulid,
                                            on,
                                        )
                                    }
                                />
                            ))}
                        </div>
                    </fieldset>

                    {options.campuses.length > 1 && (
                        <fieldset>
                            <legend className="mb-2 text-sm font-medium">
                                Campuses
                            </legend>
                            <div className="flex flex-wrap gap-x-4 gap-y-2">
                                {options.campuses.map((item) => (
                                    <CheckboxItem
                                        key={item.ulid}
                                        id={`exam-campus-${item.ulid}`}
                                        label={item.name}
                                        checked={campuses.has(item.ulid)}
                                        onChange={(on) =>
                                            toggle(
                                                campuses,
                                                setCampuses,
                                                item.ulid,
                                                on,
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>
                    )}

                    <div>
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium">
                                Date sheet
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={fillOnePerDay}
                                    disabled={subjects.length === 0}
                                >
                                    One subject a day
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addRow}
                                    disabled={subjects.length === 0}
                                >
                                    <Plus aria-hidden />
                                    Add subject
                                </Button>
                            </div>
                        </div>

                        {sheet.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No date sheet yet. Add subjects, or fill one
                                subject a day from the start date.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Subject
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Date
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Time
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                <span className="sr-only">
                                                    Remove
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {sheet.map((row, index) => (
                                            <tr key={index}>
                                                <td className="p-2">
                                                    <select
                                                        className={
                                                            selectClassName
                                                        }
                                                        aria-label="Subject"
                                                        value={row.subject_ulid}
                                                        onChange={(event) =>
                                                            updateRow(index, {
                                                                subject_ulid:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    >
                                                        {!subjects.some(
                                                            (s) =>
                                                                s.ulid ===
                                                                row.subject_ulid,
                                                        ) && (
                                                            <option
                                                                value={
                                                                    row.subject_ulid
                                                                }
                                                            >
                                                                {subjectName(
                                                                    row.subject_ulid,
                                                                )}
                                                            </option>
                                                        )}
                                                        {subjects.map((s) => (
                                                            <option
                                                                key={s.ulid}
                                                                value={s.ulid}
                                                            >
                                                                {s.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="date"
                                                        aria-label="Date"
                                                        value={row.held_on}
                                                        onChange={(event) =>
                                                            updateRow(index, {
                                                                held_on:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="time"
                                                        aria-label="Time"
                                                        value={row.begins_at}
                                                        onChange={(event) =>
                                                            updateRow(index, {
                                                                begins_at:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label="Remove row"
                                                        onClick={() =>
                                                            setSheet(
                                                                sheet.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <Field
                        id="exam-instructions"
                        label="Instructions for students and parents (optional)"
                        error={errors.instructions}
                    >
                        <textarea
                            id="exam-instructions"
                            rows={3}
                            maxLength={300}
                            value={instructions}
                            placeholder="Syllabus: chapters 5–7. Bring a geometry box."
                            onChange={(event) =>
                                setInstructions(event.target.value)
                            }
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </Field>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <ToggleRow
                            id="exam-calendar"
                            label="Show on the school calendar"
                            checked={showOnCalendar}
                            onChange={setShowOnCalendar}
                        />
                        <ToggleRow
                            id="exam-notify"
                            label="Tell parents (push, simulated)"
                            checked={notify}
                            onChange={setNotify}
                        />
                    </div>

                    <InputError message={errors.exam} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isNew ? 'Create' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
