export type AcademicYear = {
    ulid: string;
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
};

export type Campus = {
    ulid: string;
    name: string;
    code: string;
    status: 'active' | 'inactive';
};

export type SchoolClass = {
    ulid: string;
    name: string;
    level: number;
};

export type ClassLadderStep = {
    name: string;
    level: number;
};

export type Subject = {
    ulid: string;
    name: string;
    code: string;
    status: string;
};

export type Section = {
    ulid: string;
    name: string;
    capacity: number | null;
    status: string;
    academic_year_ulid: string;
    campus: { ulid: string; name: string };
    class: { ulid: string; name: string; level: number };
    class_teacher: { ulid: string; name: string } | null;
};

export type PaginatedSections = {
    data: Section[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type StructureFilters = {
    year: string | null;
    campus: string | null;
    class: string | null;
};

export type SubjectsOverview = App.Domain.School.Data.SubjectsOverviewData;
export type SubjectClass = App.Domain.School.Data.SubjectClassData;
export type SubjectRow = App.Domain.School.Data.SubjectRowData;
export type SubjectKind = App.Domain.School.Enums.SubjectType;
export type SectionTeachers = App.Domain.School.Data.SectionTeachersData;

export type ClassesOverview = App.Domain.School.Data.ClassesOverviewData;
export type ClassSection = App.Domain.School.Data.ClassSectionData;
export type ClassCampus = App.Domain.School.Data.ClassCampusData;

export type SchoolDashboard = App.Domain.School.Data.SchoolDashboardData;
export type UpcomingHoliday = App.Domain.School.Data.UpcomingHolidayData;
export type AttendanceDashboard =
    App.Domain.Attendance.Data.AttendanceDashboardData;
export type AttendanceTrendPoint =
    App.Domain.Attendance.Data.AttendanceTrendPointData;
export type AbsentStudent = App.Domain.Attendance.Data.AbsentStudentData;
export type AdmissionFunnel = App.Domain.Admissions.Data.AdmissionFunnelData;

export type SyllabusScreen = App.Domain.School.Data.SyllabusScreenData;
export type SyllabusChoice = App.Domain.School.Data.SyllabusChoiceData;
export type SyllabusDetail = App.Domain.School.Data.SyllabusDetailData;
