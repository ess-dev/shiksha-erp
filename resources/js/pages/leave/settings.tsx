import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
import { index, settings as settingsRoute } from '@/routes/leave';
import { staff, update } from '@/routes/leave/settings';
import type { LeaveSettings as LeaveSettingsData } from '@/types';

export default function LeaveSettings({
    settings,
}: {
    settings: LeaveSettingsData;
}) {
    const form = useForm({ escalation_days: settings.escalation_days });
    const policy = useForm({
        policy: Object.fromEntries(
            settings.staff_policy.map((row) => [
                row.type,
                {
                    days: row.days === null ? '' : String(row.days),
                    document_after_days:
                        row.document_after_days === null
                            ? ''
                            : String(row.document_after_days),
                },
            ]),
        ) as Record<string, { days: string; document_after_days: string }>,
    });
    const policyErrors = policy.errors as Record<string, string | undefined>;
    const setPolicy = (
        type: string,
        key: 'days' | 'document_after_days',
        value: string,
    ) =>
        policy.setData('policy', {
            ...policy.data.policy,
            [type]: { ...policy.data.policy[type], [key]: value },
        });

    return (
        <>
            <Head title="Leave settings" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div>
                    <Button asChild variant="link" size="sm" className="-ml-3">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden /> Back to student leave
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Leave settings
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Class teacher’s approval limit</CardTitle>
                        <CardDescription>
                            Leave of up to this many school days is approved by
                            the class teacher. Longer leave also goes to the
                            coordinator or principal. A change applies to
                            requests made after it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid max-w-xs gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put(update.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Field
                                id="escalation_days"
                                label="School days"
                                error={form.errors.escalation_days}
                            >
                                <Input
                                    id="escalation_days"
                                    type="number"
                                    min={1}
                                    max={30}
                                    value={form.data.escalation_days}
                                    onChange={(e) =>
                                        form.setData(
                                            'escalation_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Button type="submit" disabled={form.processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Staff leave allowance</CardTitle>
                        <CardDescription>
                            School days each member of staff may take per
                            academic session. Leave the days empty for "as
                            needed". A certificate or letter is required when a
                            request is longer than the limit in the last column.
                            A change applies to requests made after it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                policy.put(staff.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-3">
                                {settings.staff_policy.map((row) => (
                                    <div
                                        key={row.type}
                                        className="grid items-end gap-3 sm:grid-cols-[1fr_9rem_9rem]"
                                    >
                                        <span className="text-sm font-medium">
                                            {row.label}
                                        </span>
                                        <Field
                                            id={`days-${row.type}`}
                                            label="Days a year"
                                            error={
                                                policyErrors[
                                                    `policy.${row.type}.days`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`days-${row.type}`}
                                                type="number"
                                                min={1}
                                                max={365}
                                                placeholder="As needed"
                                                value={
                                                    policy.data.policy[row.type]
                                                        .days
                                                }
                                                onChange={(e) =>
                                                    setPolicy(
                                                        row.type,
                                                        'days',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            id={`doc-${row.type}`}
                                            label="Certificate after"
                                            error={
                                                policyErrors[
                                                    `policy.${row.type}.document_after_days`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`doc-${row.type}`}
                                                type="number"
                                                min={0}
                                                max={30}
                                                placeholder="Never"
                                                value={
                                                    policy.data.policy[row.type]
                                                        .document_after_days
                                                }
                                                onChange={(e) =>
                                                    setPolicy(
                                                        row.type,
                                                        'document_after_days',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                ))}
                            </div>
                            <InputError message={policyErrors.leave} />
                            <Button
                                type="submit"
                                className="w-fit"
                                disabled={policy.processing}
                            >
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LeaveSettings.layout = {
    breadcrumbs: [
        { title: 'Student leave', href: index() },
        { title: 'Leave settings', href: settingsRoute() },
    ],
};
