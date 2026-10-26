import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, CalendarX, Settings } from 'lucide-react';
import LeaveCard from '@/components/leave/leave-card';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index, settings } from '@/routes/leave';
import type { StaffLeaveScreen } from '@/types';

export default function StudentLeave({ screen }: { screen: StaffLeaveScreen }) {
    const tabs = [
        {
            key: 'review',
            label: `To review${screen.review_count > 0 ? ` (${screen.review_count})` : ''}`,
        },
        { key: 'all', label: 'All leave' },
    ];

    return (
        <>
            <Head title="Student leave" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Student leave
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Leave applied by parents of your classes.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {screen.can_configure && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={settings()}>
                                    <Settings aria-hidden /> Leave settings
                                </Link>
                            </Button>
                        )}
                        <div
                            role="group"
                            aria-label="Show"
                            className="flex gap-1 rounded-lg border p-1"
                        >
                            {tabs.map((tab) => (
                                <Link
                                    key={tab.key}
                                    href={index({
                                        query:
                                            tab.key === 'all'
                                                ? { tab: 'all' }
                                                : {},
                                    })}
                                    preserveScroll
                                    aria-current={
                                        screen.tab === tab.key
                                            ? 'page'
                                            : undefined
                                    }
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground',
                                        screen.tab === tab.key &&
                                            'bg-primary/10 text-primary hover:text-primary',
                                    )}
                                >
                                    {tab.label}
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>

                {screen.on_leave_today.length > 0 && (
                    <div className="flex items-center gap-2 rounded-lg bg-primary/10 px-4 py-3 text-sm text-primary">
                        <CalendarX className="size-4 shrink-0" aria-hidden />
                        On approved leave today:{' '}
                        {screen.on_leave_today.join(', ')}. They’re marked
                        absent without an alert to parents.
                    </div>
                )}

                <Card className="gap-0 p-0">
                    {screen.items.length === 0 ? (
                        <div className="flex flex-col items-center gap-1 px-4 py-12 text-center">
                            <CalendarCheck
                                className="size-6 text-muted-foreground"
                                aria-hidden
                            />
                            <p className="font-medium">
                                {screen.tab === 'review'
                                    ? 'Nothing to review'
                                    : 'No leave applications yet'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Parents apply from the parent app; requests land
                                here.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {screen.items.map((item) => (
                                <LeaveCard
                                    key={item.ulid}
                                    item={item}
                                    escalationDays={screen.escalation_days}
                                />
                            ))}
                        </ul>
                    )}
                </Card>

                <p className="text-sm text-muted-foreground">
                    You approve leave of up to {screen.escalation_days} school{' '}
                    {screen.escalation_days === 1 ? 'day' : 'days'}. Longer
                    leave goes to the coordinator with your recommendation.
                </p>
            </div>
        </>
    );
}

StudentLeave.layout = {
    breadcrumbs: [{ title: 'Student leave', href: index() }],
};
