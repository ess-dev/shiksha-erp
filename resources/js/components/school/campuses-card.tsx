import { Form } from '@inertiajs/react';
import CampusController from '@/actions/App/Http/Controllers/School/CampusController';
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
import type { Campus } from '@/types';

function CampusRow({
    campus,
    canManage,
}: {
    campus: Campus;
    canManage: boolean;
}) {
    if (!canManage) {
        return (
            <li className="flex items-center justify-between gap-2 py-2">
                <span className="font-medium">
                    {campus.name}{' '}
                    <span className="text-sm font-normal text-muted-foreground">
                        {campus.code}
                    </span>
                </span>
                <Badge
                    variant={
                        campus.status === 'active' ? 'default' : 'secondary'
                    }
                >
                    {campus.status === 'active' ? 'Active' : 'Inactive'}
                </Badge>
            </li>
        );
    }

    return (
        <li className="py-3">
            <Form
                {...CampusController.update.form({ campus: campus.ulid })}
                options={{ preserveScroll: true }}
                className="grid items-end gap-3 sm:grid-cols-[1fr_10rem_auto]"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id={`campus-name-${campus.ulid}`}
                            label={`Name (${campus.code})`}
                            error={errors.name}
                        >
                            <Input
                                id={`campus-name-${campus.ulid}`}
                                name="name"
                                defaultValue={campus.name}
                            />
                        </Field>
                        <Field
                            id={`campus-status-${campus.ulid}`}
                            label="Status"
                            error={errors.status}
                        >
                            <select
                                id={`campus-status-${campus.ulid}`}
                                name="status"
                                defaultValue={campus.status}
                                className={selectClassName}
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </Field>
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

export default function CampusesCard({
    campuses,
    canManage,
}: {
    campuses: Campus[];
    canManage: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Campuses</CardTitle>
                <CardDescription>
                    Campuses are never deleted; mark one inactive instead.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {campuses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No campus has been added yet.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {campuses.map((campus) => (
                            <CampusRow
                                key={campus.ulid}
                                campus={campus}
                                canManage={canManage}
                            />
                        ))}
                    </ul>
                )}

                {canManage && (
                    <Form
                        {...CampusController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="campus-new-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="campus-new-name"
                                        name="name"
                                        placeholder="Main Campus"
                                    />
                                </Field>
                                <Field
                                    id="campus-new-code"
                                    label="Code"
                                    error={errors.code}
                                >
                                    <Input
                                        id="campus-new-code"
                                        name="code"
                                        placeholder="MAIN"
                                    />
                                </Field>
                                <div className="sm:col-span-2">
                                    <InputError message={errors.structure} />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-2"
                                    >
                                        Add campus
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
