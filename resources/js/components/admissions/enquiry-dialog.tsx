import { useForm, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/admissions';
import type { AdmissionOptions } from '@/types/admissions';
import type { CampusSwitcher } from '@/types/students';

export default function EnquiryDialog({
    options,
    today,
    onClose,
}: {
    options: AdmissionOptions;
    today: string;
    onClose: () => void;
}) {
    const switcher = usePage<{ campusSwitcher: CampusSwitcher | null }>().props
        .campusSwitcher;

    const offeredAt = (campus: string) =>
        options.classes.filter((schoolClass) =>
            options.offered.some(
                (place) =>
                    place.campus_ulid === campus &&
                    place.class_ulid === schoolClass.ulid,
            ),
        );

    const startCampus =
        options.campuses.find((campus) => campus.value === switcher?.selected)
            ?.value ??
        options.campuses[0]?.value ??
        '';

    const form = useForm({
        first_name: '',
        last_name: '',
        gender: options.genders.find((g) => g.value === 'female')?.value ?? '',
        born_on: '',
        campus: startCampus,
        class: offeredAt(startCampus)[0]?.ulid ?? '',
        parent_name: '',
        relation: options.relations[0]?.value ?? 'Mother',
        phone: '',
        source: options.sources[0]?.value ?? 'walk_in',
        notes: '',
    });

    const errors = form.errors as Record<string, string>;
    const classes = offeredAt(form.data.campus);

    // A child must be at least 2 years old (the server checks too).
    const latestBirth = `${Number(today.slice(0, 4)) - 2}${today.slice(4)}`;

    const chooseCampus = (campus: string) => {
        const available = offeredAt(campus);

        form.setData((data) => ({
            ...data,
            campus,
            class: available.some((c) => c.ulid === data.class)
                ? data.class
                : (available[0]?.ulid ?? ''),
        }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add enquiry</DialogTitle>
                    <DialogDescription>
                        Only collect what is needed at enquiry. Documents come
                        later in the application.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-2"
                    noValidate
                >
                    <Field
                        id="enq-first"
                        label="Child’s first name"
                        error={form.errors.first_name}
                    >
                        <Input
                            id="enq-first"
                            autoComplete="off"
                            autoFocus
                            value={form.data.first_name}
                            onChange={(e) =>
                                form.setData('first_name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="enq-last"
                        label="Last name"
                        error={form.errors.last_name}
                    >
                        <Input
                            id="enq-last"
                            autoComplete="off"
                            value={form.data.last_name}
                            onChange={(e) =>
                                form.setData('last_name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="enq-gender"
                        label="Gender"
                        error={form.errors.gender}
                    >
                        <select
                            id="enq-gender"
                            className={selectClassName}
                            value={form.data.gender}
                            onChange={(e) =>
                                form.setData('gender', e.target.value)
                            }
                        >
                            {options.genders.map((gender) => (
                                <option key={gender.value} value={gender.value}>
                                    {gender.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="enq-dob"
                        label="Date of birth"
                        error={form.errors.born_on}
                    >
                        <Input
                            id="enq-dob"
                            type="date"
                            max={latestBirth}
                            value={form.data.born_on}
                            onChange={(e) =>
                                form.setData('born_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="enq-campus"
                        label="Campus"
                        error={form.errors.campus}
                    >
                        <select
                            id="enq-campus"
                            className={selectClassName}
                            value={form.data.campus}
                            onChange={(e) => chooseCampus(e.target.value)}
                        >
                            {options.campuses.map((campus) => (
                                <option key={campus.value} value={campus.value}>
                                    {campus.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="enq-class"
                        label="Class applying for"
                        error={form.errors.class}
                    >
                        <select
                            id="enq-class"
                            className={selectClassName}
                            value={form.data.class}
                            onChange={(e) =>
                                form.setData('class', e.target.value)
                            }
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
                        id="enq-parent"
                        label="Parent’s name"
                        error={form.errors.parent_name}
                    >
                        <Input
                            id="enq-parent"
                            autoComplete="off"
                            value={form.data.parent_name}
                            onChange={(e) =>
                                form.setData('parent_name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="enq-relation"
                        label="Relation"
                        error={form.errors.relation}
                    >
                        <select
                            id="enq-relation"
                            className={selectClassName}
                            value={form.data.relation}
                            onChange={(e) =>
                                form.setData('relation', e.target.value)
                            }
                        >
                            {options.relations.map((relation) => (
                                <option
                                    key={relation.value}
                                    value={relation.value}
                                >
                                    {relation.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="enq-phone"
                        label="Mobile number"
                        error={form.errors.phone}
                    >
                        <Input
                            id="enq-phone"
                            inputMode="numeric"
                            maxLength={10}
                            placeholder="98765 43210"
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData(
                                    'phone',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                        />
                    </Field>
                    <Field
                        id="enq-source"
                        label="How they heard"
                        error={form.errors.source}
                    >
                        <select
                            id="enq-source"
                            className={selectClassName}
                            value={form.data.source}
                            onChange={(e) =>
                                form.setData('source', e.target.value)
                            }
                        >
                            {options.sources.map((source) => (
                                <option key={source.value} value={source.value}>
                                    {source.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="sm:col-span-2">
                        <Field
                            id="enq-notes"
                            label="Notes"
                            error={form.errors.notes}
                        >
                            <Input
                                id="enq-notes"
                                placeholder="Wants transport from Kharar"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <div className="sm:col-span-2">
                        <InputError message={errors.application} />
                    </div>

                    <DialogFooter className="sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || classes.length === 0}
                        >
                            {form.processing && <Spinner />}
                            Add enquiry
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
