import {
    Award,
    BookMarked,
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarClock,
    CalendarX,
    Gift,
    House,
    Images,
    Megaphone,
    MessageCircle,
    Newspaper,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type ParentNavItem = {
    key: string;
    label: string;
    icon: LucideIcon;
    /** Home, Fees, Attendance, Results, Subjects and Homework exist; the rest are shown as "Soon". */
    built: boolean;
};

/** The prototype's parent menu, in its order. */
export const parentNav: ParentNavItem[] = [
    { key: 'home', label: 'Home', icon: House, built: true },
    { key: 'feed', label: 'Feed', icon: Newspaper, built: false },
    { key: 'bus', label: 'Bus tracking', icon: Bus, built: false },
    { key: 'fees', label: 'Fees', icon: Wallet, built: true },
    {
        key: 'attendance',
        label: 'Attendance',
        icon: CalendarCheck,
        built: true,
    },
    { key: 'results', label: 'Results', icon: Award, built: true },
    { key: 'subjects', label: 'Subjects', icon: BookMarked, built: true },
    { key: 'homework', label: 'Homework', icon: BookOpen, built: true },
    { key: 'leave', label: 'Leave', icon: CalendarX, built: true },
    {
        key: 'appointments',
        label: 'Appointments',
        icon: CalendarClock,
        built: false,
    },
    { key: 'gallery', label: 'Gallery', icon: Images, built: false },
    { key: 'greetings', label: 'Greetings', icon: Gift, built: false },
    { key: 'notices', label: 'Notices', icon: Megaphone, built: false },
    { key: 'chat', label: 'Teacher chat', icon: MessageCircle, built: false },
];
