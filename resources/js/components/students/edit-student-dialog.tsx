import { useForm } from '@inertiajs/react';
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
import { update } from '@/routes/students';
import type { StudentOptions, StudentProfile } from '@/types/students';

type GuardianForm = {
    ulid: string;
    name: string;
    relation: string;
    phone: string;
    email: string;
    occupation: string;
    is_primary: boolean;
    has_portal_access: boolean;
};

export default function EditStudentDialog({
    student,
    options,
    onClose,
}: {
    student: StudentProfile;
    options: StudentOptions;
    onClose: () => void;
}) {
    const edit = student.edit;

    const form = useForm({
        first_name: edit?.first_name ?? '',
        last_name: edit?.last_name ?? '',
        gender: edit?.gender ?? 'male',
        born_on: edit?.born_on ?? '',
        blood_group: edit?.blood_group ?? 'unknown',
        house: edit?.house_ulid ?? '',
        address: edit?.address ?? '',
        guardians: (edit?.guardians ?? []) as GuardianForm[],
    });

    const errors = form.errors as Record<string, string>;
    const currentHouseMissing =
        form.data.house !== '' &&
        !options.houses.some((house) => house.value === form.data.house);

    const setGuardian = (
        index: number,
        changes: Partial<GuardianForm>,
    ): void => {
        form.setData(
            'guardians',
            form.data.guardians.map((guardian, position) =>
                position === index ? { ...guardian, ...changes } : guardian,
            ),
        );
    };

    const setPrimary = (index: number): void => {
        form.setData(
            'guardians',
            form.data.guardians.map((guardian, position) => ({
                ...guardian,
                is_primary: position === index,
            })),
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch(update.url(student.ulid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-2xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Edit {student.name}</DialogTitle>
                    <DialogDescription>
                        A guardian is shared by all their children, so changing
                        a guardian&apos;s name, phone or email changes it for
                        each child. A phone number belongs to one guardian only.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    noValidate
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="edit-first-name"
                                label="First name"
                                error={form.errors.first_name}
                            >
                                <Input
                                    id="edit-first-name"
                                    value={form.data.first_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'first_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="edit-last-name"
                                label="Last name"
                                error={form.errors.last_name}
                            >
                                <Input
                                    id="edit-last-name"
                                    value={form.data.last_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'last_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="edit-gender"
                                label="Gender"
                                error={form.errors.gender}
                            >
                                <select
                                    id="edit-gender"
                                    className={selectClassName}
                                    value={form.data.gender}
                                    onChange={(event) =>
                                        form.setData(
                                            'gender',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {options.genders.map((gender) => (
                                        <option
                                            key={gender.value}
                                            value={gender.value}
                                        >
                                            {gender.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                id="edit-born-on"
                                label="Date of birth"
                                error={form.errors.born_on}
                            >
                                <Input
                                    id="edit-born-on"
                                    type="date"
                                    value={form.data.born_on}
                                    onChange={(event) =>
                                        form.setData(
                                            'born_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="edit-blood-group"
                                label="Blood group"
                                error={form.errors.blood_group}
                            >
                                <select
                                    id="edit-blood-group"
                                    className={selectClassName}
                                    value={form.data.blood_group}
                                    onChange={(event) =>
                                        form.setData(
                                            'blood_group',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {options.blood_groups.map((group) => (
                                        <option
                                            key={group.value}
                                            value={group.value}
                                        >
                                            {group.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                id="edit-house"
                                label="House"
                                error={form.errors.house}
                            >
                                <select
                                    id="edit-house"
                                    className={selectClassName}
                                    value={form.data.house}
                                    onChange={(event) =>
                                        form.setData(
                                            'house',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Not assigned</option>
                                    {currentHouseMissing && (
                                        <option value={form.data.house}>
                                            {student.house_name} (inactive)
                                        </option>
                                    )}
                                    {options.houses.map((house) => (
                                        <option
                                            key={house.value}
                                            value={house.value}
                                        >
                                            {house.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <div className="sm:col-span-2">
                                <Field
                                    id="edit-address"
                                    label="Address"
                                    error={form.errors.address}
                                >
                                    <Input
                                        id="edit-address"
                                        value={form.data.address}
                                        onChange={(event) =>
                                            form.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Guardians</h3>
                            {form.data.guardians.map((guardian, index) => (
                                <fieldset
                                    key={guardian.ulid}
                                    className="grid gap-3 rounded-md border p-4 sm:grid-cols-2"
                                >
                                    <legend className="px-1 text-xs text-muted-foreground">
                                        Guardian {index + 1}
                                    </legend>
                                    <Field
                                        id={`g-name-${index}`}
                                        label="Name"
                                        error={
                                            errors[`guardians.${index}.name`]
                                        }
                                    >
                                        <Input
                                            id={`g-name-${index}`}
                                            value={guardian.name}
                                            onChange={(event) =>
                                                setGuardian(index, {
                                                    name: event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id={`g-relation-${index}`}
                                        label="Relation"
                                        error={
                                            errors[
                                                `guardians.${index}.relation`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`g-relation-${index}`}
                                            value={guardian.relation}
                                            onChange={(event) =>
                                                setGuardian(index, {
                                                    relation:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id={`g-phone-${index}`}
                                        label="Phone"
                                        error={
                                            errors[`guardians.${index}.phone`]
                                        }
                                    >
                                        <Input
                                            id={`g-phone-${index}`}
                                            value={guardian.phone}
                                            onChange={(event) =>
                                                setGuardian(index, {
                                                    phone: event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id={`g-email-${index}`}
                                        label="Email"
                                        error={
                                            errors[`guardians.${index}.email`]
                                        }
                                    >
                                        <Input
                                            id={`g-email-${index}`}
                                            type="email"
                                            value={guardian.email}
                                            onChange={(event) =>
                                                setGuardian(index, {
                                                    email: event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id={`g-occupation-${index}`}
                                        label="Occupation"
                                        error={
                                            errors[
                                                `guardians.${index}.occupation`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`g-occupation-${index}`}
                                            value={guardian.occupation}
                                            onChange={(event) =>
                                                setGuardian(index, {
                                                    occupation:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                    <div className="flex flex-col justify-end gap-2 text-sm">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="primary-guardian"
                                                checked={guardian.is_primary}
                                                onChange={() =>
                                                    setPrimary(index)
                                                }
                                            />
                                            Primary contact
                                        </label>
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    guardian.has_portal_access
                                                }
                                                onChange={(event) =>
                                                    setGuardian(index, {
                                                        has_portal_access:
                                                            event.target
                                                                .checked,
                                                    })
                                                }
                                            />
                                            Has portal and app access
                                        </label>
                                    </div>
                                </fieldset>
                            ))}
                            <InputError message={errors.student} />
                            <InputError message={errors.guardians} />
                        </div>
                    </div>

                    <DialogFooter className="border-t px-6 py-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
