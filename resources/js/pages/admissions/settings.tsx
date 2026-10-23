import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { index, settings } from '@/routes/admissions';
import settingsRoutes from '@/routes/admissions/settings';
import type { AdmissionAbilities, DocumentSettings } from '@/types/admissions';

type RuleForm = {
    required: boolean;
    all_classes: boolean;
    classes: string[];
};

export default function AdmissionSettings({
    documents,
    abilities,
}: {
    documents: DocumentSettings;
    abilities: AdmissionAbilities;
}) {
    const form = useForm<{ rules: Record<string, RuleForm> }>({
        rules: Object.fromEntries(
            documents.rules.map((rule) => [
                rule.kind,
                {
                    required: rule.is_required,
                    all_classes: rule.all_classes,
                    classes: rule.class_ulids,
                },
            ]),
        ),
    });

    const errors = form.errors as Record<string, string>;
    const editable = abilities.settings;

    const change = (kind: string, changes: Partial<RuleForm>) =>
        form.setData('rules', {
            ...form.data.rules,
            [kind]: { ...form.data.rules[kind], ...changes },
        });

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(settingsRoutes.documents.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Required documents" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div>
                    <Button variant="ghost" size="sm" asChild className="-ml-3">
                        <Link href={index()}>
                            <ArrowLeft /> Admissions
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Required documents
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Which documents an application needs, and for which
                        classes. A missing required document is flagged on the
                        card and asked about before “Documents verified”.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Documents by class</CardTitle>
                        <CardDescription>
                            {documents.is_default
                                ? 'Showing the starting rules: the transfer certificate is not asked for the lowest class. Save to make them your own.'
                                : 'Your school’s rules.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={save} className="grid gap-5" noValidate>
                            {documents.rules.map((rule) => {
                                const row = form.data.rules[rule.kind];

                                return (
                                    <fieldset
                                        key={rule.kind}
                                        className="grid gap-2 border-b pb-4 last:border-b-0 last:pb-0"
                                    >
                                        <legend className="sr-only">
                                            {rule.label}
                                        </legend>
                                        <label className="flex items-center gap-3 text-sm font-medium">
                                            <Checkbox
                                                checked={row.required}
                                                disabled={!editable}
                                                onCheckedChange={(checked) =>
                                                    change(rule.kind, {
                                                        required:
                                                            checked === true,
                                                        all_classes:
                                                            checked === true
                                                                ? row.all_classes ||
                                                                  row.classes
                                                                      .length ===
                                                                      0
                                                                : false,
                                                    })
                                                }
                                            />
                                            {rule.label}
                                            {!row.required && (
                                                <Badge variant="secondary">
                                                    Not required
                                                </Badge>
                                            )}
                                        </label>

                                        {row.required && (
                                            <div className="ml-7 grid gap-2">
                                                <label className="flex items-center gap-3 text-sm">
                                                    <Checkbox
                                                        checked={
                                                            row.all_classes
                                                        }
                                                        disabled={!editable}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            change(rule.kind, {
                                                                all_classes:
                                                                    checked ===
                                                                    true,
                                                            })
                                                        }
                                                    />
                                                    Every class
                                                </label>
                                                {!row.all_classes && (
                                                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                                                        {documents.classes.map(
                                                            (schoolClass) => (
                                                                <label
                                                                    key={
                                                                        schoolClass.ulid
                                                                    }
                                                                    className="flex items-center gap-2 text-sm"
                                                                >
                                                                    <Checkbox
                                                                        checked={row.classes.includes(
                                                                            schoolClass.ulid,
                                                                        )}
                                                                        disabled={
                                                                            !editable
                                                                        }
                                                                        onCheckedChange={(
                                                                            checked,
                                                                        ) =>
                                                                            change(
                                                                                rule.kind,
                                                                                {
                                                                                    classes:
                                                                                        checked ===
                                                                                        true
                                                                                            ? [
                                                                                                  ...row.classes,
                                                                                                  schoolClass.ulid,
                                                                                              ]
                                                                                            : row.classes.filter(
                                                                                                  (
                                                                                                      ulid,
                                                                                                  ) =>
                                                                                                      ulid !==
                                                                                                      schoolClass.ulid,
                                                                                              ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                    {
                                                                        schoolClass.name
                                                                    }
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </fieldset>
                                );
                            })}

                            <InputError message={errors.house} />

                            {editable ? (
                                <div>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing && <Spinner />}
                                        Save required documents
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    Only staff who can change school settings
                                    can edit these rules.
                                </p>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdmissionSettings.layout = {
    breadcrumbs: [
        { title: 'Admissions', href: index() },
        { title: 'Required documents', href: settings() },
    ],
};
