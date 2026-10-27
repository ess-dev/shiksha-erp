import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    CalendarCheck,
    CalendarDays,
    CalendarX,
    ClipboardCheck,
    ClipboardPen,
    CreditCard,
    FileBadge,
    FolderGit2,
    HandCoins,
    Inbox,
    Landmark,
    ReceiptIndianRupee,
    Library,
    RotateCcw,
    LayoutGrid,
    NotebookPen,
    School,
    ShieldCheck,
    UserPlus,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { index as admissions } from '@/routes/admissions';
import { index as attendance } from '@/routes/attendance';
import { index as exams } from '@/routes/exams';
import { index as homework } from '@/routes/homework';
import { index as leave } from '@/routes/leave';
import { index as myLeave } from '@/routes/my-leave';
import { index as staffLeave } from '@/routes/staff-leave';
import { index as collectFee } from '@/routes/fees/collect';
import { index as feeDues } from '@/routes/fees/dues';
import { index as feePayments } from '@/routes/fees/payments';
import { index as feeRefunds } from '@/routes/fees/refunds';
import { index as feeStructure } from '@/routes/fees/structure';
import { index as classes } from '@/routes/school/classes';
import { index as roles } from '@/routes/school/roles';
import { index as structure } from '@/routes/school/structure';
import { index as subjects } from '@/routes/school/subjects';
import { index as syllabus } from '@/routes/school/syllabus';
import { index as marks } from '@/routes/marks';
import { index as approvals } from '@/routes/approvals';
import { index as reportCards } from '@/routes/report-cards';
import { index as students } from '@/routes/students';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const canViewStructure = useCan('structure.view');
    const canViewStudents = useCan('students.view');
    const canViewRoles = useCan('roles.view');
    const canScheduleExams = useCan('exams.schedule');
    const canEnter = useCan('marks.enter');
    const canReopen = useCan('marks.reopen');
    const canEnterMarks = canEnter || canReopen;
    const canPrepareReportCards =
        useCan('reportcards.prepare') || useCan('reportcards.approve');
    const canApprove =
        useCan('reportcards.approve') || useCan('leave.approve_long');
    const canViewLeave =
        useCan('leave.approve') || useCan('leave.approve_long');
    const canViewStaffLeave =
        useCan('staff.leave_approve') || useCan('staff.view');
    const canViewHomework =
        useCan('homework.view') || useCan('homework.assign');
    const canUpdateSubjects = useCan('subjects.update');
    const canViewAdmissions = useCan('admissions.view');
    const canViewFees = useCan('fees.view');
    const canRefundFees = useCan('fees.refund');
    const canCollectFees = useCan('fees.collect');
    const canViewAttendance =
        useCan('attendance.view') || useCan('attendance.mark');

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(canViewStudents
            ? [
                  {
                      title: 'Students',
                      href: students(),
                      icon: Users,
                  },
              ]
            : []),
        ...(canViewStructure
            ? [
                  {
                      title: 'Classes',
                      href: classes(),
                      icon: School,
                  },
                  {
                      title: 'Subjects',
                      href: subjects(),
                      icon: Library,
                  },
              ]
            : []),
        ...(canUpdateSubjects
            ? [
                  {
                      title: canViewStructure
                          ? 'Syllabus and notes'
                          : 'Subjects',
                      href: syllabus(),
                      icon: BookOpen,
                  },
              ]
            : []),
        ...(canViewAdmissions
            ? [
                  {
                      title: 'Admissions',
                      href: admissions(),
                      icon: UserPlus,
                  },
              ]
            : []),
        ...(canViewAttendance
            ? [
                  {
                      title: 'Attendance',
                      href: attendance(),
                      icon: CalendarCheck,
                  },
              ]
            : []),
        ...(canScheduleExams
            ? [
                  {
                      title: 'Exams and tests',
                      href: exams(),
                      icon: ClipboardCheck,
                  },
              ]
            : []),
        ...(canEnterMarks
            ? [
                  {
                      title: 'Marks entry',
                      href: marks(),
                      icon: ClipboardPen,
                  },
              ]
            : []),
        ...(canPrepareReportCards
            ? [
                  {
                      title: 'Report cards',
                      href: reportCards(),
                      icon: FileBadge,
                  },
              ]
            : []),
        ...(canApprove
            ? [
                  {
                      title: 'Approvals',
                      href: approvals(),
                      icon: Inbox,
                  },
              ]
            : []),
        ...(canViewLeave
            ? [
                  {
                      title: 'Student leave',
                      href: leave(),
                      icon: CalendarX,
                  },
              ]
            : []),
        ...(canViewStaffLeave
            ? [
                  {
                      title: 'Staff and leave',
                      href: staffLeave(),
                      icon: UserCog,
                  },
              ]
            : []),
        {
            title: 'My leave',
            href: myLeave(),
            icon: CalendarDays,
        },
        ...(canViewHomework
            ? [
                  {
                      title: 'Homework',
                      href: homework(),
                      icon: NotebookPen,
                  },
              ]
            : []),
        ...(canCollectFees
            ? [
                  {
                      title: 'Collect fee',
                      href: collectFee(),
                      icon: HandCoins,
                  },
              ]
            : []),
        ...(canViewFees
            ? [
                  {
                      title: 'Dues',
                      href: feeDues(),
                      icon: ReceiptIndianRupee,
                  },
                  {
                      title: 'Payments',
                      href: feePayments(),
                      icon: CreditCard,
                  },
                  {
                      title: 'Fee structure',
                      href: feeStructure(),
                      icon: Landmark,
                  },
              ]
            : []),
        ...(canRefundFees
            ? [
                  {
                      title: 'Refunds',
                      href: feeRefunds(),
                      icon: RotateCcw,
                  },
              ]
            : []),
        ...(canViewRoles
            ? [
                  {
                      title: 'Roles and access',
                      href: roles(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
        ...(canViewStructure
            ? [
                  {
                      title: 'School structure',
                      href: structure(),
                      icon: Building2,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
