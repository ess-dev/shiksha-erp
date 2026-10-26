import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
import { update } from '@/routes/leave/settings';
import type { LeaveSettings as LeaveSettingsData } from '@/types';

export default function LeaveSettings({
    settings,
}: {
    settings: LeaveSettingsData;
}) {
    const form = useForm({ escalation_days: settings.escalation_days });

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
