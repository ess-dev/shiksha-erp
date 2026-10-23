import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    LogOut,
    Pencil,
    Printer,
} from 'lucide-react';
import { useState } from 'react';
import ChangeSectionDialog from '@/components/students/change-section-dialog';
import DocumentsTab from '@/components/students/documents-tab';
import EditStudentDialog from '@/components/students/edit-student-dialog';
import GuardiansTab from '@/components/students/guardians-tab';
import HistoryTab from '@/components/students/history-tab';
import InitialsBadge from '@/components/students/initials-badge';
import IssueTcDialog from '@/components/students/issue-tc-dialog';
import LinkSiblingDialog from '@/components/students/link-sibling-dialog';
import NotAvailableTab from '@/components/students/not-available-tab';
import OverviewTab from '@/components/students/overview-tab';
import PastSessionBanner from '@/components/students/past-session-banner';
import RollNumberDialog from '@/components/students/roll-number-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/students';
import tc from '@/routes/students/tc';
import type { StudentOptions, StudentProfile } from '@/types/students';

const TABS = [
    ['overview', 'Overview'],
    ['guardians', 'Guardians'],
    ['history', 'Enrollment history'],
    ['attendance', 'Attendance'],
    ['fees', 'Fees'],
    ['marks', 'Marks'],
    ['documents', 'Documents'],
] as const;

type TabKey = (typeof TABS)[number][0];
type Dialogue = 'section' | 'tc' | 'edit' | 'roll' | 'sibling' | null;

type Props = {
    student: StudentProfile;
    options: StudentOptions;
    today: string;
    tab: TabKey;
};

export default function StudentShow({ student, options, today, tab }: Props) {
    const [active, setActive] = useState<TabKey>(tab);
    const [dialogue, setDialogue] = useState<Dialogue>(null);
    const close = () => setDialogue(null);

    const choose = (key: TabKey) => {
        setActive(key);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        window.history.replaceState(window.history.state, '', url);
    };

    const abilities = student.abilities;

    return (
        <>
            <Head title={student.name} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                >
                    <ArrowLeft className="size-4" /> All students
                </Link>

                {student.session_name !== null && (
                    <PastSessionBanner name={student.session_name} />
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-4">
                        <InitialsBadge
                            name={student.name}
                            className="size-14 text-base"
                        />
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                {student.name}
                                {!student.is_current && (
                                    <Badge variant="secondary" className="ml-2">
                                        {student.status === 'transferred'
                                            ? 'Transferred'
                                            : 'Left'}
                                    </Badge>
                                )}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {student.class_label ?? 'No class'}
                                {student.roll_number !== null &&
                                    ` · Roll ${student.roll_number}`}{' '}
                                · {student.admission_number}
                                {student.campus_name &&
                                    ` · ${student.campus_name}`}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {student.is_current && abilities.manage && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setDialogue('edit')}
                            >
                                <Pencil /> Edit details
                            </Button>
                        )}
                        {student.is_current && abilities.manage && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setDialogue('section')}
                            >
                                <ArrowLeftRight /> Change section
                            </Button>
                        )}
                        {student.is_current && abilities.issue_tc && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setDialogue('tc')}
                            >
                                <LogOut /> Issue TC
                            </Button>
                        )}
                        {student.tc_number !== null && abilities.issue_tc && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={tc.show(student.ulid)}>
                                    <Printer /> View TC
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div
                    role="tablist"
                    aria-label="Student details"
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            id={`tab-${key}`}
                            aria-selected={active === key}
                            aria-controls="student-panel"
                            tabIndex={active === key ? 0 : -1}
                            onClick={() => choose(key)}
                            onKeyDown={(event) => {
                                if (
                                    event.key !== 'ArrowRight' &&
                                    event.key !== 'ArrowLeft'
                                ) {
                                    return;
                                }

                                const position = TABS.findIndex(
                                    ([tabKey]) => tabKey === active,
                                );
                                const next =
                                    (position +
                                        (event.key === 'ArrowRight'
                                            ? 1
                                            : TABS.length - 1)) %
                                    TABS.length;

                                choose(TABS[next][0]);
                                document
                                    .getElementById(`tab-${TABS[next][0]}`)
                                    ?.focus();
                            }}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap',
                                active === key
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div
                    id="student-panel"
                    role="tabpanel"
                    aria-labelledby={`tab-${active}`}
                >
                    {active === 'overview' && (
                        <OverviewTab
                            student={student}
                            onChangeRoll={() => setDialogue('roll')}
                            onLinkSibling={() => setDialogue('sibling')}
                        />
                    )}
                    {active === 'guardians' && (
                        <GuardiansTab student={student} />
                    )}
                    {active === 'history' && <HistoryTab student={student} />}
                    {active === 'attendance' && (
                        <NotAvailableTab
                            title="Attendance"
                            module="Attendance"
                        />
                    )}
                    {active === 'fees' && (
                        <NotAvailableTab title="Fees" module="Fees" />
                    )}
                    {active === 'marks' && (
                        <NotAvailableTab title="Marks" module="Exams" />
                    )}
                    {active === 'documents' && (
                        <DocumentsTab student={student} options={options} />
                    )}
                </div>
            </div>

            {dialogue === 'edit' && (
                <EditStudentDialog
                    student={student}
                    options={options}
                    onClose={close}
                />
            )}
            {dialogue === 'section' && (
                <ChangeSectionDialog student={student} onClose={close} />
            )}
            {dialogue === 'roll' && (
                <RollNumberDialog student={student} onClose={close} />
            )}
            {dialogue === 'tc' && (
                <IssueTcDialog
                    student={student}
                    options={options}
                    today={today}
                    onClose={close}
                />
            )}
            {dialogue === 'sibling' && (
                <LinkSiblingDialog student={student} onClose={close} />
            )}
        </>
    );
}

StudentShow.layout = {
    breadcrumbs: [{ title: 'Students', href: index() }],
};
