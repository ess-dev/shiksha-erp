import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    CalendarCheck,
    ClipboardCheck,
    FolderGit2,
    Library,
    LayoutGrid,
    School,
    ShieldCheck,
    UserPlus,
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
import { index as classes } from '@/routes/school/classes';
import { index as roles } from '@/routes/school/roles';
import { index as structure } from '@/routes/school/structure';
import { index as subjects } from '@/routes/school/subjects';
import { index as syllabus } from '@/routes/school/syllabus';
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
    const canUpdateSubjects = useCan('subjects.update');
    const canViewAdmissions = useCan('admissions.view');
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
