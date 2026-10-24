import { Head } from '@inertiajs/react';
import { CalendarCheck, FileCheck, UserCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ReportCardApprovalsList from '@/components/exams/report-card-approvals';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index } from '@/routes/approvals';
import type { ReportCardApprovals, ReportCardView } from '@/types';

type TabKey = 'profile' | 'leave' | 'reportcards' | 'staff';

function Empty({
    icon,
    title,
    body,
}: {
    icon: ReactNode;
    title: string;
    body?: string;
}) {
    return (
        <Card className="items-center gap-1 px-4 py-12 text-center">
            <span className="mb-1 text-muted-foreground" aria-hidden>
                {icon}
            </span>
            <p className="font-medium">{title}</p>
            {body && <p className="text-sm text-muted-foreground">{body}</p>}
        </Card>
    );
}

/**
 * One inbox for the things that wait for a decision. As in the prototype it
 * has four tabs; only Report cards has data so far. The other three are built
 * with their features (parent profile edits, long student leave, teacher
 * leave) and show their empty state until then.
 */
export default function Approvals({
    report_cards,
    view,
}: {
    report_cards: ReportCardApprovals;
    view?: ReportCardView | null;
}) {
    const [tab, setTab] = useState<TabKey>('reportcards');

    const tabs: { key: TabKey; label: string; count: number }[] = [
        { key: 'profile', label: 'Profile changes', count: 0 },
        { key: 'leave', label: 'Student leave', count: 0 },
        {
            key: 'reportcards',
            label: 'Report cards',
            count: report_cards.awaiting_count,
        },
        { key: 'staff', label: 'Teacher leave', count: 0 },
    ];

    return (
        <>
            <Head title="Approvals" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Approvals
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Changes and requests that need a decision before
                            parents see the result.
                        </p>
                    </div>
                    <div
                        role="group"
                        aria-label="Approval type"
                        className="flex flex-wrap gap-1 rounded-lg border p-1"
                    >
                        {tabs.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                aria-pressed={tab === item.key}
                                onClick={() => setTab(item.key)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                    tab === item.key &&
                                        'bg-primary/10 text-primary hover:text-primary',
                                )}
                            >
                                {item.label}
                                {item.count > 0 && ` (${item.count})`}
                            </button>
                        ))}
                    </div>
                </div>

                {tab === 'profile' && (
                    <Empty
                        icon={<UserCheck className="size-6" />}
                        title="No profile changes waiting"
                        body="When parents edit a child’s profile, the changes wait here until someone approves them."
                    />
                )}
                {tab === 'leave' && (
                    <Empty
                        icon={<CalendarCheck className="size-6" />}
                        title="No long leave waiting"
                        body="Class teachers approve short leave themselves. Longer leave comes here after the class teacher’s recommendation."
                    />
                )}
                {tab === 'reportcards' && (
                    <ReportCardApprovalsList
                        data={report_cards}
                        view={view}
                        url={index.url()}
                        query={{
                            class: report_cards.section_ulid ?? undefined,
                        }}
                    />
                )}
                {tab === 'staff' && (
                    <Empty
                        icon={<FileCheck className="size-6" />}
                        title="No teacher leave waiting"
                    />
                )}
            </div>
        </>
    );
}

Approvals.layout = {
    breadcrumbs: [{ title: 'Approvals', href: index() }],
};
