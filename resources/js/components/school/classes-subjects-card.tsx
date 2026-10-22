import { Form } from '@inertiajs/react';
import SchoolClassController from '@/actions/App/Http/Controllers/School/SchoolClassController';
import SubjectController from '@/actions/App/Http/Controllers/School/SubjectController';
import InputError from '@/components/input-error';
import Field from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { SchoolClass, Subject } from '@/types';

export default function ClassesSubjectsCard({
    classes,
    subjects,
    canManage,
}: {
    classes: SchoolClass[];
    subjects: Subject[];
    canManage: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Classes and subjects</CardTitle>
                <CardDescription>
                    A class&apos;s level orders it and decides which classes a
                    wing covers.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-8 md:grid-cols-2">
                <div className="space-y-4">
                    <h3 className="font-medium">Classes</h3>
                    {classes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No class has been added yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {classes.map((schoolClass) => (
                                <li
                                    key={schoolClass.ulid}
                                    className="flex justify-between py-2"
                                >
                                    <span>{schoolClass.name}</span>
                                    <span className="text-sm text-muted-foreground">
                                        Level {schoolClass.level}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {canManage && (
                        <Form
                            {...SchoolClassController.store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="class-name"
                                        label="Class name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="class-name"
                                            name="name"
                                            placeholder="Class 5"
                                        />
                                    </Field>
                                    <Field
                                        id="class-level"
                                        label="Level"
                                        error={errors.level}
                                    >
                                        <Input
                                            id="class-level"
                                            name="level"
                                            type="number"
                                            min={-5}
                                            max={20}
                                            placeholder="5"
                                        />
                                    </Field>
                                    <InputError message={errors.structure} />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="justify-self-start"
                                    >
                                        Add class
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>

                <div className="space-y-4">
                    <h3 className="font-medium">Subjects</h3>
                    {subjects.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No subject has been added yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {subjects.map((subject) => (
                                <li
                                    key={subject.ulid}
                                    className="flex justify-between py-2"
                                >
                                    <span>{subject.name}</span>
                                    <span className="text-sm text-muted-foreground">
                                        {subject.code}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {canManage && (
                        <Form
                            {...SubjectController.store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="subject-name"
                                        label="Subject name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="subject-name"
                                            name="name"
                                            placeholder="Mathematics"
                                        />
                                    </Field>
                                    <Field
                                        id="subject-code"
                                        label="Code"
                                        error={errors.code}
                                    >
                                        <Input
                                            id="subject-code"
                                            name="code"
                                            placeholder="MAT"
                                        />
                                    </Field>
                                    <InputError message={errors.structure} />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="justify-self-start"
                                    >
                                        Add subject
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
