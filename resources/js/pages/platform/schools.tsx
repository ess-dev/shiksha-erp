import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Download, PlusCircle, SearchX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCalendarDate, formatPaise } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    exportMethod as exportSchools,
    index,
    onboard,
    show,
} from '@/routes/platform/schools';
import type {
    PlatformSchool,
    PlatformSchoolFilters,
    PlatformSchoolList,
    SchoolStatus,
} from '@/types';

type Props = {
    schools: PlatformSchoolList;
    filters: PlatformSchoolFilters;
};

const tabs: { key: 'all' | SchoolStatus; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'active', label: 'Active' },
    { key: 'trial', label: 'Trial' },
    { key: 'suspended', label: 'Suspended' },
    { key: 'expired', label: 'Expired' },
];

const statusStyles: Record<SchoolStatus, string> = {
    active: 'bg-green-100 text-green-800',
    trial: 'bg-indigo-100 text-indigo-800',
    suspended: 'bg-red-100 text-red-800',
    expired: 'bg-slate-100 text-slate-700',
};

function plural(count: number, one: string, many: string): string {
    return `${count.toLocaleString('en-IN')} ${count === 1 ? one : many}`;
}

function dateLabel(school: PlatformSchool): string {
    if (school.status === 'trial' && school.trial_ends_on) {
        return `Trial ends ${formatCalendarDate(school.trial_ends_on)}`;
    }

    if (school.renews_on) {
        return `${school.status === 'expired' ? 'Expired' : 'Renews'} ${formatCalendarDate(school.renews_on)}`;
    }

    return '—';
}

export default function PlatformSchools({ schools, filters }: Props) {
    const [search, setSearch] = useState(filters.q);
    const firstRender = useRef(true);

    function visit(next: Partial<PlatformSchoolFilters> & { page?: number }) {
        const merged = { ...filters, ...next };

        router.get(
            index({
                query: {
                    status: merged.status ?? undefined,
                    plan: merged.plan ?? undefined,
                    q: merged.q || undefined,
                    page: next.page && next.page > 1 ? next.page : undefined,
                },
            }).url,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => visit({ q: search }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const activeTab = filters.status ?? 'all';

    return (
        <>
            <Head title="Schools" />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Schools
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {plural(schools.total_schools, 'school', 'schools')} ·{' '}
                        {plural(schools.total_students, 'student', 'students')}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <a
                            href={
                                exportSchools({
                                    query: {
                                        status: filters.status ?? undefined,
                                        plan: filters.plan ?? undefined,
                                        q: filters.q || undefined,
                                    },
                                }).url
                            }
                        >
                            <Download aria-hidden="true" />
                            Export CSV
                        </a>
                    </Button>
                    <Button asChild>
                        <Link href={onboard()}>
                            <PlusCircle aria-hidden="true" />
                            Onboard a school
                        </Link>
                    </Button>
                </div>
            </div>

            <section className="rounded-xl border border-border bg-card shadow-xs">
                <div className="border-b border-border bg-muted/40 p-3.5">
                    <div
                        role="group"
                        aria-label="Filter by status"
                        className="inline-flex flex-wrap gap-1 rounded-lg border border-border bg-card p-1"
                    >
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                aria-pressed={activeTab === tab.key}
                                onClick={() =>
                                    visit({
                                        status:
                                            tab.key === 'all' ? null : tab.key,
                                        page: 1,
                                    })
                                }
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-muted',
                                    activeTab === tab.key &&
                                        'bg-secondary font-semibold text-primary hover:bg-secondary',
                                )}
                            >
                                {tab.label} ({schools.status_counts[tab.key]})
                            </button>
                        ))}
                    </div>
                </div>

                <form
                    onSubmit={(event) => event.preventDefault()}
                    className="grid gap-3 border-b border-border p-4 sm:grid-cols-[1fr_16rem]"
                >
                    <div className="grid gap-1.5">
                        <label htmlFor="school-search" className="text-sm">
                            Search
                        </label>
                        <Input
                            id="school-search"
                            type="search"
                            value={search}
                            maxLength={100}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="School, city or contact"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <label htmlFor="school-plan" className="text-sm">
                            Plan
                        </label>
                        <select
                            id="school-plan"
                            value={filters.plan ?? ''}
                            onChange={(event) =>
                                visit({
                                    plan: event.target.value || null,
                                    page: 1,
                                })
                            }
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="">All plans</option>
                            {schools.plans.map((plan) => (
                                <option key={plan.ulid} value={plan.ulid}>
                                    {plan.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </form>

                {schools.rows.length === 0 ? (
                    <div className="grid place-items-center gap-2 px-4 py-14 text-center text-slate-600">
                        <SearchX className="size-8" aria-hidden="true" />
                        <p className="font-medium">No schools match</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/40 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        School
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Plan
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Students
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 text-right md:table-cell"
                                    >
                                        MRR
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 md:table-cell"
                                    >
                                        Renews / trial ends
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Open</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {schools.rows.map((school) => (
                                    <tr key={school.ulid}>
                                        <td className="px-4 py-3.5">
                                            <Link
                                                href={show(school.ulid)}
                                                className="font-semibold text-primary hover:underline"
                                            >
                                                {school.name}
                                            </Link>
                                            <div className="text-[13px] text-muted-foreground">
                                                {[
                                                    [school.city, school.state]
                                                        .filter(Boolean)
                                                        .join(', '),
                                                    school.board,
                                                    plural(
                                                        school.campuses,
                                                        'campus',
                                                        'campuses',
                                                    ),
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {school.plan_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <span
                                                className={cn(
                                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize',
                                                    statusStyles[school.status],
                                                )}
                                            >
                                                {school.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3.5 text-right tabular-nums">
                                            {school.students.toLocaleString(
                                                'en-IN',
                                            )}
                                        </td>
                                        <td className="hidden px-4 py-3.5 text-right tabular-nums md:table-cell">
                                            {school.mrr_paise > 0
                                                ? formatPaise(school.mrr_paise)
                                                : '—'}
                                        </td>
                                        <td className="hidden px-4 py-3.5 md:table-cell">
                                            {dateLabel(school)}
                                        </td>
                                        <td className="px-4 py-3.5 text-right">
                                            <Link
                                                href={show(school.ulid)}
                                                aria-label={`Open ${school.name}`}
                                                className="inline-grid size-8 place-items-center rounded-md text-slate-500 hover:bg-muted"
                                            >
                                                <ChevronRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {schools.last_page > 1 && (
                    <nav
                        aria-label="Pagination"
                        className="flex items-center justify-between border-t border-border px-4 py-3 text-sm"
                    >
                        <span className="text-muted-foreground">
                            Page {schools.page} of {schools.last_page} ·{' '}
                            {plural(schools.matching, 'school', 'schools')}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={schools.page <= 1}
                                onClick={() =>
                                    visit({ page: schools.page - 1 })
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={schools.page >= schools.last_page}
                                onClick={() =>
                                    visit({ page: schools.page + 1 })
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </nav>
                )}
            </section>
        </>
    );
}

PlatformSchools.layout = {
    title: 'Schools',
};
