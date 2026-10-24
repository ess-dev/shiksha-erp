/**
 * Short names for the generated parent portal types (resources/js/types/generated.d.ts).
 * The shapes themselves come from the PHP Data objects; never edit them by hand.
 */
export type ParentShell = App.Domain.Students.Data.ParentShellData;
export type ParentChild = App.Domain.Students.Data.ParentChildData;
export type ParentHome = App.Domain.Students.Data.ParentHomeData;
export type ParentAttendance = App.Domain.Students.Data.ParentAttendanceData;
export type StudentDay = App.Domain.Attendance.Data.StudentDayData;
export type StudentTally = App.Domain.Attendance.Data.StudentTallyData;
export type DayState = App.Domain.Attendance.Enums.DayState;
export type SchoolContext = App.Domain.Identity.Data.SchoolContextData;
export type ParentSubject = App.Domain.School.Data.ParentSubjectData;
export type ParentChapter = App.Domain.School.Data.ParentChapterData;
export type StudentResults = App.Domain.Exams.Data.StudentResultsData;
export type StudentExamResult = App.Domain.Exams.Data.StudentExamResultData;
export type StudentSubjectResult =
    App.Domain.Exams.Data.StudentSubjectResultData;
export type ReportCard = App.Domain.Students.Data.ReportCardData;
export type SchoolReportCard = App.Domain.Exams.Data.SchoolReportCardData;
