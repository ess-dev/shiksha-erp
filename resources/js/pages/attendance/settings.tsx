import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { formatCalendarDay } from '@/lib/format';
import { index, settings } from '@/routes/attendance';
import holidays from '@/routes/attendance/holidays';
import settingsRoutes from '@/routes/attendance/settings';
import type { AttendanceCalendar } from '@/types';

const WEEKDAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
];

export default function AttendanceSettings({
    calendar,
}: {
    calendar: AttendanceCalendar;
}) {
    const days = useForm<{
        working_days: number[];
        correction_window_days: number;
    }>({
        working_days: calendar.working_days,
        correction_window_days: calendar.correction_window_days,
    });
    const holiday = useForm({ date: '', name: '' });
    const [removing, setRemoving] = useState<string | null>(null);

    const errors = { ...days.errors, ...holiday.errors } as Record<
        string,
        string
    >;

    const toggleDay = (value: number, checked: boolean) =>
        days.setData(
            'working_days',
            checked
                ? [...days.data.working_days, value].sort((a, b) => a - b)
                : days.data.working_days.filter((day) => day !== value),
        );

    return (
        <>
            <Head title="Attendance settings" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div>
                    <Button asChild variant="link" size="sm" className="-ml-3">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden /> Back to attendance
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Attendance settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Which days the school is open, and how long a class
                        teacher can change a register.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Working days and corrections</CardTitle>
                        <CardDescription>
                            No register is taken on a weekly day off. Monday to
                            Friday is only a starting point (illustrative —
                            verify).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                days.put(settingsRoutes.update.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Working days
                                </legend>
                                <div className="flex flex-wrap gap-x-5 gap-y-2">
                                    {WEEKDAYS.map((weekday) => (
                                        <label
                                            key={weekday.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-primary"
                                                checked={days.data.working_days.includes(
                                                    weekday.value,
                                                )}
                                                onChange={(event) =>
                                                    toggleDay(
                                                        weekday.value,
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            {weekday.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError
                                    message={
                                        errors.working_days ?? errors.calendar
                                    }
                                />
                            </fieldset>

                            <Field
                                id="correction-window"
                                label="Days a class teacher can still change a register"
                                error={errors.correction_window_days}
                            >
                                <Input
                                    id="correction-window"
                                    type="number"
                                    min={0}
                                    max={60}
                                    className="w-28"
                                    value={days.data.correction_window_days}
                                    onChange={(event) =>
                                        days.setData(
                                            'correction_window_days',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <p className="text-xs text-muted-foreground">
                                Today’s register is free to change. On later
                                days inside this window a reason is needed.
                                After it, only someone who can correct
                                attendance may change it (illustrative — verify:
                                7 days).
                            </p>
                            <div>
                                <Button
                                    type="submit"
                                    disabled={days.processing}
                                >
                                    Save settings
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Holidays</CardTitle>
                        <CardDescription>
                            Days the school is closed. No register is taken on
                            these days.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        {calendar.holidays.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No holidays added yet. Add the ones you know
                                below.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {calendar.holidays.map((item) => (
                                    <li
                                        key={item.ulid}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {item.name}
                                            </span>
                                            <span className="ml-2 text-muted-foreground">
                                                {formatCalendarDay(
                                                    item.holiday_on,
                                                )}{' '}
                                                {item.holiday_on.slice(0, 4)}
                                            </span>
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            aria-label={`Remove ${item.name}`}
                                            disabled={removing === item.ulid}
                                            onClick={() =>
                                                router.delete(
                                                    holidays.destroy.url(
                                                        item.ulid,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                        onStart: () =>
                                                            setRemoving(
                                                                item.ulid,
                                                            ),
                                                        onFinish: () =>
                                                            setRemoving(null),
                                                    },
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden /> Remove
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <form
                            className="grid gap-4 border-t pt-6 sm:grid-cols-[12rem_1fr_auto] sm:items-end"
                            onSubmit={(event) => {
                                event.preventDefault();
                                holiday.post(holidays.store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () => holiday.reset(),
                                });
                            }}
                        >
                            <Field
                                id="holiday-date"
                                label="Date"
                                error={errors.date}
                            >
                                <Input
                                    id="holiday-date"
                                    type="date"
                                    value={holiday.data.date}
                                    onChange={(event) =>
                                        holiday.setData(
                                            'date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="holiday-name"
                                label="Name"
                                error={errors.name}
                            >
                                <Input
                                    id="holiday-name"
                                    value={holiday.data.name}
                                    maxLength={100}
                                    placeholder="Diwali"
                                    onChange={(event) =>
                                        holiday.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Button type="submit" disabled={holiday.processing}>
                                Add holiday
                            </Button>
                            <div className="sm:col-span-3">
                                <InputError message={errors.calendar} />
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AttendanceSettings.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: index() },
        { title: 'Settings', href: settings() },
    ],
};
