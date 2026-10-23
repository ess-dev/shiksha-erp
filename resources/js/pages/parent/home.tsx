import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    CalendarX,
    CircleCheck,
    CircleX,
    Clock,
    CircleUserRound,
    Gift,
    Hourglass,
    Images,
    MessageCircle,
    Award,
    BookMarked,
    BookOpen,
    MapPin,
    Megaphone,
    Sun,
    UserX,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dayLook } from '@/components/parent/day-state';
import { NotBuiltBadge, PlaceholderCard } from '@/components/parent/not-built';
import { Button } from '@/components/ui/button';
import {
    formatCalendarDay,
    formatClock,
    formatWeekdayShort,
    initials,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { attendance } from '@/routes/parent';
import type {
    ParentChild,
    ParentHome,
    ParentShell,
    StudentDay,
} from '@/types/parent';

type Props = {
    shell: ParentShell;
    home: ParentHome | null;
    session_name: string;
    is_current_session: boolean;
};

const quickActions: { label: string; icon: LucideIcon }[] = [
    { label: 'Apply for leave', icon: CalendarX },
    { label: 'Book a meeting', icon: CalendarClock },
    { label: 'Report card', icon: Award },
    { label: 'Subjects', icon: BookMarked },
    { label: 'Gallery', icon: Images },
    { label: 'Greetings', icon: Gift },
    { label: 'Teacher chat', icon: MessageCircle },
    { label: 'Profile', icon: CircleUserRound },
];

export default function ParentHomePage({
    shell,
    home,
    session_name: sessionName,
    is_current_session: isCurrent,
}: Props) {
    const child =
        shell.children.find((c) => c.ulid === shell.selected_child) ?? null;

    if (child === null || home === null) {
        return (
            <>
                <Head title="Home" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        {isCurrent
                            ? 'No child linked yet'
                            : `No records for ${sessionName}`}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {isCurrent
                            ? 'Ask the school office to give your number access to your child.'
                            : 'Your children were not at this school in that session, or the school has no records for it.'}
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Home" />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                <div className="grid content-start gap-4">
                    {isCurrent && home.today_state.state === 'absent' && (
                        <AbsentAlert
                            child={child}
                            today={home.today_state}
                            timezone={shell.timezone}
                        />
                    )}
                    <ChildCard
                        child={child}
                        home={home}
                        timezone={shell.timezone}
                        attendanceEnabled={shell.attendance_enabled}
                        isCurrent={isCurrent}
                    />
                    {isCurrent && <QuickActions />}
                    {isCurrent && <HomeworkPlaceholder />}
                </div>

                {isCurrent && (
                    <div className="grid content-start gap-4">
                        <FeePlaceholder />
                        <BusPlaceholder />
                        <NoticesPlaceholder />
                    </div>
                )}
            </div>
        </>
    );
}

function AbsentAlert({
    child,
    today,
    timezone,
}: {
    child: ParentChild;
    today: StudentDay;
    timezone: string;
}) {
    return (
        <div
            role="alert"
            className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-950 dark:border-red-900 dark:bg-red-950/30 dark:text-red-100"
        >
            <UserX className="mt-0.5 size-5 shrink-0" aria-hidden />
            <div className="min-w-0 flex-1">
                <p className="font-semibold">
                    {child.first_name} was marked absent today
                </p>
                <p className="mt-0.5 text-sm">
                    {today.marked_by !== null && today.marked_at !== null
                        ? `Marked by ${today.marked_by} at ${formatClock(today.marked_at, timezone)}. `
                        : ''}
                    If {child.first_name} is in school or you already told the
                    school, let the class teacher know.
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        disabled
                        title="Teacher chat is not built yet"
                    >
                        <MessageCircle aria-hidden /> Message class teacher
                    </Button>
                    <NotBuiltBadge text="Chat: soon" />
                </div>
            </div>
        </div>
    );
}

function StatusPill({ day }: { day: StudentDay }) {
    const look = dayLook[day.state];
    const Icon =
        day.state === 'present'
            ? CircleCheck
            : day.state === 'absent'
              ? CircleX
              : day.state === 'late'
                ? Clock
                : day.state === 'holiday' || day.state === 'off'
                  ? Sun
                  : Hourglass;
    const label =
        day.state === 'holiday' || day.state === 'off'
            ? 'No school today'
            : look.label;

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium whitespace-nowrap',
                day.state === 'present' ||
                    day.state === 'absent' ||
                    day.state === 'late'
                    ? look.cell
                    : 'bg-muted text-muted-foreground',
            )}
        >
            <Icon className="size-4" aria-hidden />
            {label}
        </span>
    );
}

function ChildCard({
    child,
    home,
    timezone,
    attendanceEnabled,
    isCurrent,
}: {
    child: ParentChild;
    home: ParentHome;
    timezone: string;
    attendanceEnabled: boolean;
    isCurrent: boolean;
}) {
    const attended = home.month.present + home.month.late;
    const percent = (tenths: number | null) =>
        tenths === null ? '—' : `${Math.round(tenths / 10)}%`;

    return (
        <section
            aria-label={`${child.name}, today`}
            className="rounded-xl border bg-card p-4 shadow-xs"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-12 items-center justify-center rounded-full bg-accent text-base font-semibold text-accent-foreground">
                    {initials(child.name)}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-base font-semibold">
                        {child.name}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {child.section_label}
                        {child.roll_number !== null &&
                            ` · Roll ${child.roll_number}`}{' '}
                        · {child.campus_name}
                    </p>
                    {child.class_teacher_name !== null && (
                        <p className="text-sm text-muted-foreground">
                            Class teacher: {child.class_teacher_name}
                        </p>
                    )}
                </div>
            </div>

            {isCurrent ? (
                <>
                    <div className="mt-4 flex items-center justify-between gap-3">
                        <div className="text-sm text-muted-foreground">
                            <p>{formatCalendarDay(home.today)}</p>
                            <p>
                                {home.today_state.marked_at !== null
                                    ? `Marked at ${formatClock(home.today_state.marked_at, timezone)}`
                                    : home.today_state.state === 'unmarked'
                                      ? 'The class teacher marks attendance each school day'
                                      : ''}
                            </p>
                        </div>
                        <StatusPill day={home.today_state} />
                    </div>

                    <ul
                        className="mt-4 grid grid-cols-5 gap-2"
                        aria-label="This week's attendance"
                    >
                        {home.week.map((day) => {
                            const look = dayLook[day.state];
                            const isToday = day.date === home.today;
                            const letter =
                                day.state === 'late'
                                    ? 'L'
                                    : day.state === 'holiday'
                                      ? 'H'
                                      : day.state === 'unmarked'
                                        ? '–'
                                        : day.state === 'off' ||
                                            day.state === 'upcoming'
                                          ? '·'
                                          : look.short;

                            return (
                                <li
                                    key={day.date}
                                    aria-label={`${formatCalendarDay(day.date)}: ${look.label}`}
                                    className={cn(
                                        'rounded-lg px-1 py-2 text-center text-xs',
                                        look.cell,
                                        day.state === 'upcoming' &&
                                            'bg-muted/50',
                                        isToday && 'ring-2 ring-primary',
                                    )}
                                >
                                    {formatWeekdayShort(day.date)}
                                    <b className="mt-0.5 block text-base">
                                        {letter}
                                    </b>
                                </li>
                            );
                        })}
                    </ul>

                    <dl className="mt-4 grid grid-cols-3 gap-2">
                        <Stat label="Present" value={String(attended)} />
                        <Stat
                            label="Absent"
                            value={String(home.month.absent)}
                            danger={home.month.absent > 0}
                        />
                        <Stat
                            label={`${home.month_label.slice(0, 3)} attendance`}
                            value={percent(home.month.percent_tenths)}
                        />
                    </dl>

                    {attendanceEnabled ? (
                        <Button asChild variant="ghost" className="mt-3 w-full">
                            <Link
                                href={attendance.url({
                                    query: { child: child.ulid },
                                })}
                            >
                                <CalendarDays aria-hidden /> Monthly calendar
                            </Link>
                        </Button>
                    ) : null}
                </>
            ) : (
                <dl className="mt-4 grid grid-cols-3 gap-2">
                    <Stat
                        label="Attended"
                        value={String(home.year.present + home.year.late)}
                    />
                    <Stat label="Absent" value={String(home.year.absent)} />
                    <Stat
                        label={`${home.session_name} attendance`}
                        value={percent(home.year.percent_tenths)}
                    />
                </dl>
            )}
        </section>
    );
}

function Stat({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <div className="rounded-lg bg-muted/60 px-3 py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-lg font-semibold',
                    danger && 'text-red-700 dark:text-red-400',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function QuickActions() {
    return (
        <section aria-labelledby="quick-actions">
            <div className="mb-2 flex items-center gap-2">
                <h2 id="quick-actions" className="text-[15px] font-semibold">
                    Quick actions
                </h2>
                <NotBuiltBadge text="Not built yet" />
            </div>
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {quickActions.map(({ label, icon: Icon }) => (
                    <li key={label}>
                        <button
                            type="button"
                            disabled
                            title={`${label} is not built yet`}
                            className="flex min-h-24 w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed bg-card p-3 text-sm font-medium opacity-70"
                        >
                            <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                                <Icon className="size-5" aria-hidden />
                            </span>
                            {label}
                            <span className="text-[10px] font-normal text-muted-foreground">
                                Soon
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function HomeworkPlaceholder() {
    return (
        <PlaceholderCard title="Homework due">
            <ul className="divide-y">
                {[
                    [
                        'Mathematics',
                        'Fractions — Exercise 7.3, Q1–12',
                        'Due tomorrow',
                    ],
                    ['Science', 'Draw and label a plant cell', 'Submitted'],
                ].map(([subject, task, badge]) => (
                    <li key={subject} className="flex items-start gap-3 py-3">
                        <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                            <BookOpen className="size-5" aria-hidden />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">{subject}</p>
                            <p className="text-sm">{task}</p>
                        </div>
                        <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium">
                            {badge}
                        </span>
                    </li>
                ))}
            </ul>
        </PlaceholderCard>
    );
}

function FeePlaceholder() {
    return (
        <PlaceholderCard title="Fee due">
            <p className="text-3xl font-bold tracking-tight">₹7,750</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Term 3 (Oct–Dec) · due 20 Oct
            </p>
            <div
                className="mt-3 h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                <div className="h-full w-2/5 bg-green-600" />
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
                ₹5,000 of ₹12,750 paid · ₹7,750 left
            </p>
            <Button className="mt-4 w-full" disabled>
                <Wallet aria-hidden /> Pay now
            </Button>
        </PlaceholderCard>
    );
}

function BusPlaceholder() {
    return (
        <PlaceholderCard title="School bus">
            <p className="font-semibold">Morning pickup completed</p>
            <p className="text-sm text-muted-foreground">
                Next: afternoon drop from 1:45 pm
            </p>
            <Button variant="outline" className="mt-3 w-full" disabled>
                <MapPin aria-hidden /> Track bus
            </Button>
        </PlaceholderCard>
    );
}

function NoticesPlaceholder() {
    return (
        <PlaceholderCard title="From school">
            <ul className="divide-y">
                {[
                    [
                        'Parent–teacher meeting on Saturday',
                        'PTM for Classes 1–10 from 9:00 am to 12:30 pm.',
                    ],
                    [
                        'Holiday notice',
                        'School will remain closed on account of a festival.',
                    ],
                ].map(([title, body]) => (
                    <li key={title} className="flex items-start gap-3 py-3">
                        <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                            <Megaphone className="size-5" aria-hidden />
                        </span>
                        <div className="min-w-0">
                            <p className="font-semibold">{title}</p>
                            <p className="text-sm text-muted-foreground">
                                {body}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
        </PlaceholderCard>
    );
}
