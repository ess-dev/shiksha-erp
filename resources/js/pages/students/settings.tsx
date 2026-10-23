import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp } from 'lucide-react';
import HouseController from '@/actions/App/Http/Controllers/Students/HouseController';
import StudentSettingsController from '@/actions/App/Http/Controllers/Students/StudentSettingsController';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
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
import { useCan } from '@/hooks/use-can';
import { index, settings } from '@/routes/students';
import houseRoutes from '@/routes/students/houses';
import type { House, NumberingSettings } from '@/types/students';

type Props = {
    houses: House[];
    numbering: NumberingSettings;
};

function HouseRow({
    house,
    position,
    total,
    canManage,
    move,
}: {
    house: House;
    position: number;
    total: number;
    canManage: boolean;
    move: (from: number, to: number) => void;
}) {
    if (!canManage) {
        return (
            <li className="flex items-center justify-between gap-2 py-2">
                <span className="font-medium">
                    {house.name}{' '}
                    <span className="text-sm font-normal text-muted-foreground">
                        {house.student_count} students
                    </span>
                </span>
                <Badge
                    variant={
                        house.status === 'active' ? 'default' : 'secondary'
                    }
                >
                    {house.status === 'active' ? 'Active' : 'Inactive'}
                </Badge>
            </li>
        );
    }

    return (
        <li className="py-3">
            <Form
                {...HouseController.update.form({ house: house.ulid })}
                options={{ preserveScroll: true }}
                className="grid items-end gap-3 sm:grid-cols-[1fr_10rem_auto_auto]"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id={`house-name-${house.ulid}`}
                            label={`Name (${house.student_count} students)`}
                            error={errors.name ?? errors.house}
                        >
                            <Input
                                id={`house-name-${house.ulid}`}
                                name="name"
                                defaultValue={house.name}
                            />
                        </Field>
                        <Field
                            id={`house-status-${house.ulid}`}
                            label="Status"
                            error={errors.status}
                        >
                            <select
                                id={`house-status-${house.ulid}`}
                                name="status"
                                defaultValue={house.status}
                                className={selectClassName}
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </Field>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Move ${house.name} up`}
                                disabled={position === 0}
                                onClick={() => move(position, position - 1)}
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Move ${house.name} down`}
                                disabled={position === total - 1}
                                onClick={() => move(position, position + 1)}
                            >
                                <ArrowDown />
                            </Button>
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            Save
                        </Button>
                    </>
                )}
            </Form>
        </li>
    );
}

export default function StudentSettings({ houses, numbering }: Props) {
    const canManageHouses = useCan('houses.manage');
    const canManageSettings = useCan('settings.manage');

    const move = (from: number, to: number) => {
        const order = houses.map((house) => house.ulid);
        const [moved] = order.splice(from, 1);

        if (moved === undefined) {
            return;
        }

        order.splice(to, 0, moved);
        router.put(
            houseRoutes.reorder.url(),
            { order },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Houses and numbering" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div>
                    <Link
                        href={index()}
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        <ArrowLeft className="size-4" /> All students
                    </Link>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        Houses and numbering
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Houses</CardTitle>
                        <CardDescription>
                            Houses are never deleted; mark one inactive instead.
                            A student without a house shows as &ldquo;Not
                            assigned&rdquo;.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {houses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No house has been added yet. Students can still
                                be admitted as &ldquo;Not assigned&rdquo;.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {houses.map((house, position) => (
                                    <HouseRow
                                        key={house.ulid}
                                        house={house}
                                        position={position}
                                        total={houses.length}
                                        canManage={canManageHouses}
                                        move={move}
                                    />
                                ))}
                            </ul>
                        )}

                        {canManageHouses && (
                            <Form
                                {...HouseController.store.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid items-end gap-3 sm:grid-cols-[1fr_auto]"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Field
                                            id="house-new-name"
                                            label="New house"
                                            error={errors.name ?? errors.house}
                                        >
                                            <Input
                                                id="house-new-name"
                                                name="name"
                                                placeholder="Raman"
                                            />
                                        </Field>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Add house
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Admission and TC numbers</CardTitle>
                        <CardDescription>
                            Numbers look like PREFIX/2026/0001 and count up each
                            academic year. Changing a prefix only affects
                            numbers issued from now on.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...StudentSettingsController.updateNumbering.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="admission-prefix"
                                        label="Admission number prefix"
                                        error={errors.admission_prefix}
                                    >
                                        <Input
                                            id="admission-prefix"
                                            name="admission_prefix"
                                            defaultValue={
                                                numbering.admission_prefix
                                            }
                                            disabled={!canManageSettings}
                                        />
                                    </Field>
                                    <Field
                                        id="tc-prefix"
                                        label="Transfer certificate prefix"
                                        error={errors.tc_prefix}
                                    >
                                        <Input
                                            id="tc-prefix"
                                            name="tc_prefix"
                                            defaultValue={numbering.tc_prefix}
                                            disabled={!canManageSettings}
                                        />
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <InputError
                                            message={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                ).house
                                            }
                                        />
                                        {canManageSettings && (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save prefixes
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

StudentSettings.layout = {
    breadcrumbs: [
        { title: 'Students', href: index() },
        { title: 'Houses and numbering', href: settings() },
    ],
};
