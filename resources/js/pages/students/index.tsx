import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Settings, UserPlus, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { selectClassName } from '@/components/school/field';
import ImportDialog from '@/components/students/import-dialog';
import ImportResultDialog from '@/components/students/import-result-dialog';
import PastSessionBanner from '@/components/students/past-session-banner';
import StudentsTable from '@/components/students/students-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import {
    exportMethod,
    index,
    settings as studentSettings,
} from '@/routes/students';
import type { CampusSwitcher } from '@/types/students';
import type {
    ImportResult,
    SectionOption,
    StudentFilters,
    StudentList,
} from '@/types/students';

type Props = {
    students: StudentList;
    filters: StudentFilters;
    classes: SectionOption[];
    session: { name: string } | null;
};

export default function StudentsIndex({
    students,
    filters,
    classes,
    session,
}: Props) {
    const isPast = session !== null;
    const page = usePage<{ campusSwitcher: CampusSwitcher | null }>();
    const canImport = useCan('students.import');
    const canExport = useCan('students.export');
    const [importing, setImporting] = useState(false);
    const [dismissed, setDismissed] = useState<ImportResult | null>(null);
    const [search, setSearch] = useState(filters.q);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const flashed = (page.flash as { import_result?: ImportResult } | undefined)
        ?.import_result;
    const [result, setResult] = useState<ImportResult | null>(null);

    useEffect(() => {
        if (flashed !== undefined && flashed !== dismissed) {
            setResult(flashed);
        }
    }, [flashed, dismissed]);

    const visit = (changes: Partial<StudentFilters> & { page?: number }) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url(),
            {
                q: next.q === '' ? undefined : next.q,
                class: next.class ?? undefined,
                status: next.status === 'current' ? undefined : next.status,
                page: changes.page === undefined ? undefined : changes.page,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const onSearch = (value: string) => {
        setSearch(value);

        if (searchTimer.current !== null) {
            clearTimeout(searchTimer.current);
        }

        searchTimer.current = setTimeout(() => visit({ q: value }), 300);
    };

    const campus = page.props.campusSwitcher;
    const campusName =
        campus?.campuses.find((option) => option.ulid === campus.selected)
            ?.name ?? 'all campuses';
    const exportQuery = {
        q: filters.q === '' ? undefined : filters.q,
        class: filters.class ?? undefined,
        status: filters.status,
    };

    return (
        <>
            <Head title="Students" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Students
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {students.current_students_total}{' '}
                            {isPast ? `in ${session.name}` : 'current'}{' '}
                            {students.current_students_total === 1
                                ? 'student'
                                : 'students'}{' '}
                            · {campusName}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={studentSettings()}>
                                <Settings /> Houses and numbering
                            </Link>
                        </Button>
                        {canImport && !isPast && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setImporting(true)}
                            >
                                <Upload /> Import students
                            </Button>
                        )}
                        {canExport && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={exportMethod.url({
                                        query: exportQuery,
                                    })}
                                >
                                    <Download /> Export CSV
                                </a>
                            </Button>
                        )}
                        <Button
                            size="sm"
                            disabled
                            title="New admission is pending: it arrives with the Admissions module"
                        >
                            <UserPlus /> New admission
                            <Badge variant="secondary" className="ml-1">
                                Pending
                            </Badge>
                        </Button>
                    </div>
                </div>

                {isPast && <PastSessionBanner name={session.name} />}

                <Card className="gap-0 overflow-hidden p-0">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="student-search"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Search
                            </label>
                            <Input
                                id="student-search"
                                value={search}
                                onChange={(event) =>
                                    onSearch(event.target.value)
                                }
                                placeholder="Name or admission number"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="student-class"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Class
                            </label>
                            <select
                                id="student-class"
                                className={selectClassName}
                                value={filters.class ?? ''}
                                onChange={(event) =>
                                    visit({
                                        class:
                                            event.target.value === ''
                                                ? null
                                                : event.target.value,
                                    })
                                }
                            >
                                <option value="">All classes</option>
                                {classes.map((section) => (
                                    <option
                                        key={section.ulid}
                                        value={section.ulid}
                                    >
                                        {section.label} · {section.campus_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="student-fees"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Fees
                            </label>
                            <select
                                id="student-fees"
                                className={selectClassName}
                                disabled
                                aria-describedby="student-fees-note"
                            >
                                <option>Not available yet</option>
                            </select>
                            <span id="student-fees-note" className="sr-only">
                                The fees filter arrives with the Fees module
                            </span>
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="student-status"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Status
                            </label>
                            <select
                                id="student-status"
                                className={selectClassName}
                                value={filters.status}
                                onChange={(event) =>
                                    visit({
                                        status: event.target
                                            .value as StudentFilters['status'],
                                    })
                                }
                            >
                                <option value="current">
                                    Current students
                                </option>
                                <option value="left">Left the school</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>

                    {students.rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="font-medium">No students match</p>
                            <p className="text-sm text-muted-foreground">
                                Try another search or class.
                            </p>
                        </div>
                    ) : (
                        <StudentsTable rows={students.rows} />
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            Showing {students.from}–{students.to} of{' '}
                            {students.total}
                        </span>
                        <span className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={students.current_page <= 1}
                                onClick={() =>
                                    visit({ page: students.current_page - 1 })
                                }
                            >
                                Previous
                            </Button>
                            <span>
                                Page {students.current_page} of{' '}
                                {students.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    students.current_page >= students.last_page
                                }
                                onClick={() =>
                                    visit({ page: students.current_page + 1 })
                                }
                            >
                                Next
                            </Button>
                        </span>
                    </div>
                </Card>
            </div>

            {importing && <ImportDialog onClose={() => setImporting(false)} />}
            {result !== null && (
                <ImportResultDialog
                    result={result}
                    onClose={() => {
                        setDismissed(result);
                        setResult(null);
                    }}
                />
            )}
        </>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Students', href: index() }],
};
