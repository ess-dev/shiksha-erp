import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    MessageCircle,
    PartyPopper,
    TriangleAlert,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import { dayLook } from '@/components/parent/day-state';
import { NotBuiltBadge } from '@/components/parent/not-built';
import { Button } from '@/components/ui/button';
import {
    formatCalendarDay,
    formatClock,
    formatMonthLong,
    formatWeekdayShort,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { attendance as attendanceRoute } from '@/routes/parent';
import type { ParentAttendance, ParentShell, StudentDay } from '@/types/parent';

type Props = {
    shell: ParentShell;
    attendance: ParentAttendance | null;
    class_teacher_name: string | null;
    child_first_name: string | null;
    session_name: string;
};

const WEEK = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function ParentAttendancePage({
    shell,
    attendance,
    class_teacher_name: teacher,
    child_first_name: childName,
    session_name: sessionName,
}: Props) {
    if (attendance === null) {
        return (
            <>
                <Head title="Attendance" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No attendance records for {sessionName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Registers are shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Attendance" />
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                <CalendarCard
                    key={`${shell.selected_child}-${attendance.month}`}
                    shell={shell}
                    data={attendance}
                />
                <div className="grid content-start gap-4">
                    <MonthSummary data={attendance} />
                    <YearSummary data={attendance} />
                    <Absences
                        data={attendance}
                        shell={shell}
                        teacher={teacher}
                        childName={childName}
                    />
                </div>
            </div>
        </>
    );
}

function CalendarCard({
    shell,
    data,
}: {
    shell: ParentShell;
    data: ParentAttendance;
}) {
    const [selected, setSelected] = useState<string | null>(
        data.today.startsWith(data.month) ? data.today : null,
    );

    const go = (month: string | null) => {
        if (month === null) {
            return;
        }

        router.get(
            attendanceRoute.url(),
            { child: shell.selected_child ?? undefined, month },
            { preserveScroll: true, preserveState: false },
        );
    };

    const first = data.days[0];
    const lead =
        first === undefined ? 0 : WEEK.indexOf(formatWeekdayShort(first.date));
    const detail = data.days.find((d) => d.date === selected) ?? null;

    return (
        <section
            aria-label="Monthly calendar"
            className="rounded-xl border bg-card p-4 shadow-xs"
        >
            <div className="mb-3 flex items-center justify-between">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Previous month"
                    disabled={data.previous_month === null}
                    onClick={() => go(data.previous_month)}
                >
                    <ChevronLeft aria-hidden />
                </Button>
                <h2 className="text-base font-semibold">
                    {formatMonthLong(data.month)}
                </h2>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Next month"
                    disabled={data.next_month === null}
                    onClick={() => go(data.next_month)}
                >
                    <ChevronRight aria-hidden />
                </Button>
            </div>

            <div className="grid grid-cols-7 gap-1.5 text-center sm:gap-2">
                {WEEK.map((name) => (
                    <div
                        key={name}
                        aria-hidden
                        className="py-1 text-xs font-medium text-muted-foreground"
                    >
                        {name}
                    </div>
                ))}
                {Array.from({ length: lead }, (_, i) => (
                    <div key={`blank-${i}`} aria-hidden />
                ))}
                {data.days.map((day) => {
                    const look = dayLook[day.state];
                    const date = Number(day.date.slice(8));

                    return (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => setSelected(day.date)}
                            aria-pressed={selected === day.date}
                            aria-label={`${formatCalendarDay(day.date)}: ${look.label}${day.note ? ` (${day.note})` : ''}`}
                            className={cn(
                                'flex aspect-square min-h-11 flex-col items-center justify-center rounded-lg text-sm font-semibold',
                                look.cell,
                                day.date === data.today &&
                                    'ring-2 ring-primary',
                                selected === day.date &&
                                    'outline-2 outline-offset-1 outline-primary',
                            )}
                        >
                            {date}
                            {look.short !== '' && (
                                <small className="text-[10px] font-normal">
                                    {look.short}
                                </small>
                            )}
                        </button>
                    );
                })}
            </div>

            <p
                className="mt-4 rounded-lg bg-muted/60 p-3 text-sm"
                aria-live="polite"
            >
                {detail === null ? (
                    <span className="text-muted-foreground">
                        Tap a day to see details.
                    </span>
                ) : (
                    <DayDetail
                        day={detail}
                        today={data.today}
                        timezone={shell.timezone}
                    />
                )}
            </p>

            <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {(
                    [
                        'present',
                        'absent',
                        'late',
                        'holiday',
                        'unmarked',
                    ] as const
                ).map((state) => (
                    <li key={state} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className={cn(
                                'size-3 rounded-sm',
                                dayLook[state].cell,
                            )}
                        />
                        {dayLook[state].label}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function DayDetail({
    day,
    today,
    timezone,
}: {
    day: StudentDay;
    today: string;
    timezone: string;
}) {
    const look = dayLook[day.state];

    return (
        <>
            <strong>{formatCalendarDay(day.date)}:</strong> {look.label}
            {day.note !== null && ` — ${day.note}`}
            {day.marked_by !== null &&
                day.marked_at !== null &&
                ` · marked by ${day.marked_by} at ${formatClock(day.marked_at, timezone)}`}
            {day.reason !== null && ` · corrected: “${day.reason}”`}
            {day.leave_reason !== null &&
                ` · approved leave (${day.leave_reason.toLowerCase()})`}
            {day.state === 'unmarked' &&
                day.date === today &&
                ' · the class teacher marks attendance each school day'}
        </>
    );
}

function percentText(tenths: number | null, digits = 0): string {
    return tenths === null ? '—' : `${(tenths / 10).toFixed(digits)}%`;
}

function MonthSummary({ data }: { data: ParentAttendance }) {
    const t = data.month_tally;
    const tone =
        t.percent_tenths === null
            ? ''
            : t.percent_tenths >= 900
              ? 'bg-green-100 text-green-900 dark:bg-green-950/50 dark:text-green-100'
              : t.percent_tenths >= data.minimum_percent * 10
                ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100'
                : 'bg-red-100 text-red-900 dark:bg-red-950/50 dark:text-red-100';

    return (
        <section className="rounded-xl border bg-card p-4 shadow-xs">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-[15px] font-semibold">
                    {formatMonthLong(data.month).split(' ')[0]} summary
                </h2>
                {t.percent_tenths !== null && (
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            tone,
                        )}
                    >
                        {percentText(t.percent_tenths)}
                    </span>
                )}
            </div>
            {t.school_days === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No school days recorded this month. It may be a holiday or
                    vacation, or the register has not been saved yet.
                </p>
            ) : (
                <>
                    <dl className="grid grid-cols-3 gap-2">
                        <Tile label="Present" value={t.present} />
                        <Tile label="Late" value={t.late} />
                        <Tile label="Absent" value={t.absent} danger />
                    </dl>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t.school_days}{' '}
                        {t.school_days === 1 ? 'school day' : 'school days'}{' '}
                        recorded this month. Late counts as present.
                    </p>
                </>
            )}
        </section>
    );
}

function Tile({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: number;
    danger?: boolean;
}) {
    return (
        <div className="rounded-lg bg-muted/60 px-3 py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-lg font-semibold',
                    danger && value > 0 && 'text-red-700 dark:text-red-400',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function YearSummary({ data }: { data: ParentAttendance }) {
    const t = data.year_tally;
    const ok =
        t.percent_tenths === null ||
        t.percent_tenths >= data.minimum_percent * 10;
    const attended = t.present + t.late;

    return (
        <section className="rounded-xl border bg-card p-4 shadow-xs">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-[15px] font-semibold">
                    {data.is_current_session ? 'This academic year' : 'Session'}
                </h2>
                <span className="text-sm text-muted-foreground">
                    {data.session_name}
                </span>
            </div>
            <div className="flex items-center justify-between gap-3">
                <p className="text-3xl font-bold tracking-tight">
                    {percentText(t.percent_tenths, 1)}
                </p>
                <span
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium',
                        ok
                            ? 'bg-green-100 text-green-900 dark:bg-green-950/50 dark:text-green-100'
                            : 'bg-red-100 text-red-900 dark:bg-red-950/50 dark:text-red-100',
                    )}
                >
                    {ok ? (
                        <CircleCheck className="size-4" aria-hidden />
                    ) : (
                        <TriangleAlert className="size-4" aria-hidden />
                    )}
                    {ok ? 'On track' : `Below ${data.minimum_percent}%`}
                </span>
            </div>
            <div
                className="mt-3 h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                <div
                    className={cn('h-full', ok ? 'bg-green-600' : 'bg-red-600')}
                    style={{ width: `${(t.percent_tenths ?? 0) / 10}%` }}
                />
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {attended} of {t.school_days} school days attended · {t.absent}{' '}
                {t.absent === 1 ? 'absence' : 'absences'}. The school expects at
                least {data.minimum_percent}% attendance.
            </p>
        </section>
    );
}

function Absences({
    data,
    shell,
    teacher,
    childName,
}: {
    data: ParentAttendance;
    shell: ParentShell;
    teacher: string | null;
    childName: string | null;
}) {
    return (
        <section className="rounded-xl border bg-card p-4 shadow-xs">
            <h2 className="mb-3 text-[15px] font-semibold">
                Absences in {formatMonthLong(data.month).split(' ')[0]}
            </h2>
            {data.absences.length === 0 ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <PartyPopper className="size-4" aria-hidden />
                    No absences this month
                    {childName !== null ? ` for ${childName}` : ''}.
                </p>
            ) : (
                <ul className="divide-y">
                    {data.absences.map((day) => (
                        <li
                            key={day.date}
                            className="flex items-center gap-3 py-2.5"
                        >
                            <span className="flex size-10 items-center justify-center rounded-lg bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200">
                                <UserX className="size-5" aria-hidden />
                            </span>
                            <div>
                                <p className="font-semibold">
                                    {formatCalendarDay(day.date)}
                                    {day.leave_reason !== null && (
                                        <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                            Approved leave
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {day.marked_by !== null &&
                                    day.marked_at !== null
                                        ? `Marked by ${day.marked_by} at ${formatClock(day.marked_at, shell.timezone)}`
                                        : 'Marked by the school'}
                                    {day.reason !== null && ' · corrected'}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    className="flex-1"
                    disabled
                    title="Teacher chat is not built yet"
                >
                    <MessageCircle aria-hidden />
                    Message {teacher ?? 'the class teacher'}
                </Button>
                <NotBuiltBadge text="Chat: soon" />
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                Think a day is marked wrongly? The class teacher can correct it
                within {data.correction_window_days} days. Applying for leave
                from this app is coming soon.
            </p>
        </section>
    );
}
