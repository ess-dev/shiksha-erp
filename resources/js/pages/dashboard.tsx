import { Head } from '@inertiajs/react';
import {
    CalendarCheck,
    ChartColumn,
    IndianRupee,
    Megaphone,
    Receipt,
    UserPlus,
} from 'lucide-react';
import AbsentToday from '@/components/dashboard/absent-today';
import AdmissionsFunnel from '@/components/dashboard/admissions-funnel';
import AttendanceTrend from '@/components/dashboard/attendance-trend';
import {
    ComingSoonBadge,
    DashboardCard,
} from '@/components/dashboard/dashboard-card';
import FeeCollectionSample from '@/components/dashboard/fee-collection-sample';
import KpiCard from '@/components/dashboard/kpi-card';
import NeedsAttention from '@/components/dashboard/needs-attention';
import UpcomingEvents from '@/components/dashboard/upcoming-events';
import { Button } from '@/components/ui/button';
import { formatCalendarDay } from '@/lib/format';
import { dashboard as dashboardRoute } from '@/routes';
import type { SchoolDashboard } from '@/types';

function percent(tenths: number | null): string {
    return tenths === null ? '—' : `${(tenths / 10).toFixed(1)}%`;
}

/**
 * The staff dashboard (the Principal's view from the prototype). Real figures
 * for attendance, admissions and holidays, each limited to the person's own
 * scope; fees, approvals and other events are not built yet and show the
 * prototype's sample values, faded, with a "Coming soon" badge.
 */
export default function Dashboard({
    dashboard,
}: {
    dashboard: SchoolDashboard;
}) {
    const { attendance, admissions } = dashboard;
    const campusLabel = dashboard.campus_name ?? 'All campuses';
    const showCampus = dashboard.campus_name === null;

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Good {dashboard.part_of_day}
                            {dashboard.staff_name !== null &&
                                `, ${dashboard.staff_name}`}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {formatCalendarDay(dashboard.today)} · {campusLabel}
                            {dashboard.academic_year_name !== null &&
                                ` · Academic year ${dashboard.academic_year_name}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled
                            title="Reports are not built yet"
                        >
                            <ChartColumn aria-hidden />
                            Reports
                            <ComingSoonBadge />
                        </Button>
                        <Button
                            size="sm"
                            disabled
                            title="Notices are not built yet"
                        >
                            <Megaphone aria-hidden />
                            Send notice
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {attendance !== null && (
                        <KpiCard
                            label="Attendance today"
                            icon={CalendarCheck}
                            tone={
                                attendance.percent_tenths !== null &&
                                attendance.percent_tenths < 900
                                    ? 'warning'
                                    : 'success'
                            }
                            value={percent(attendance.percent_tenths)}
                            bar={
                                attendance.percent_tenths === null
                                    ? undefined
                                    : attendance.percent_tenths / 10
                            }
                        >
                            {attendance.closed_reason !== null ? (
                                <>No school today: {attendance.closed_reason}</>
                            ) : (
                                <>
                                    <strong>{attendance.absent}</strong> absent
                                    · {attendance.sections_marked} of{' '}
                                    {attendance.sections_total} sections marked
                                </>
                            )}
                        </KpiCard>
                    )}
                    <KpiCard
                        label="Fees collected"
                        icon={IndianRupee}
                        value="₹54.85 L"
                        bar={74}
                        comingSoon
                    >
                        <strong>74%</strong> of ₹73.63 L billed to date
                    </KpiCard>
                    <KpiCard
                        label="Overdue fees"
                        icon={Receipt}
                        tone="danger"
                        value="₹3.68 L"
                        comingSoon
                    >
                        <strong>30 students</strong> past due date
                    </KpiCard>
                    {admissions !== null && (
                        <KpiCard
                            label="Open admissions"
                            icon={UserPlus}
                            value={String(admissions.open_applications)}
                        >
                            <strong>{admissions.new_this_week} new</strong>{' '}
                            enquiries this week
                        </KpiCard>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {attendance !== null && (
                            <DashboardCard
                                title="Attendance trend"
                                subtitle={`Last ${attendance.frequent_absence_days} school days${attendance.closed_reason === null ? ' and today' : ''}`}
                            >
                                <AttendanceTrend
                                    points={attendance.trend}
                                    today={attendance.today}
                                />
                            </DashboardCard>
                        )}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <FeeCollectionSample />
                            {admissions !== null && (
                                <AdmissionsFunnel funnel={admissions} />
                            )}
                        </div>
                    </div>
                    <div className="flex flex-col gap-4">
                        <NeedsAttention attendance={attendance} />
                        <UpcomingEvents holidays={dashboard.holidays} />
                    </div>
                </div>

                {attendance !== null && (
                    <AbsentToday
                        attendance={attendance}
                        campusLabel={campusLabel}
                        showCampus={showCampus}
                        timezone={dashboard.timezone}
                    />
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboardRoute(),
        },
    ],
};
