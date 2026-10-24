declare namespace App {
namespace Domain {
namespace Access {
namespace Data {
export type AssignmentData = {
ulid: string,
role_ulid: string,
role_name: string,
is_owner_role: boolean,
scope_type: App.Domain.Access.Enums.ScopeType,
campus_ulids: string[],
section_ulids: string[],
from_level: number | null,
to_level: number | null,
};
export type PermissionOptionData = {
key: string,
module: string,
risk: App.Domain.Access.Enums.PermissionRisk,
is_owner_only: boolean,
description: string,
held_by_you: boolean,
};
export type RoleSummaryData = {
ulid: string,
name: string,
template_name: string | null,
template_version: number | null,
is_owner: boolean,
permissions: string[],
assignee_count: number,
update_pending: boolean,
};
export type RoleUpdateData = {
role_ulid: string,
role_name: string,
from_version: number | null,
to_version: number,
added: string[],
removed: string[],
adds_critical: boolean,
};
export type StaffAccessData = {
ulid: string,
full_name: string,
status: string,
assignments: App.Domain.Access.Data.AssignmentData[],
};
export type StaffOptionData = {
ulid: string,
name: string,
};
}
namespace Enums {
export type PermissionRisk = 'normal' | 'sensitive' | 'critical';
export type ScopeType = 'school' | 'campus' | 'wing' | 'sections';
}
}
namespace Admissions {
namespace Data {
export type AdmissionAbilitiesData = {
manage: boolean,
enroll: boolean,
documents: boolean,
view_sensitive: boolean,
settings: boolean,
};
export type AdmissionFunnelData = {
open_applications: number,
new_this_week: number,
conversion_percent: number,
stages: App.Domain.Admissions.Data.StageData[],
};
export type AdmissionOptionsData = {
genders: App.Domain.Students.Data.OptionData[],
blood_groups: App.Domain.Students.Data.OptionData[],
houses: App.Domain.Students.Data.OptionData[],
sources: App.Domain.Students.Data.OptionData[],
relations: App.Domain.Students.Data.OptionData[],
campuses: App.Domain.Students.Data.OptionData[],
classes: App.Domain.Admissions.Data.ClassOptionData[],
offered: App.Domain.Admissions.Data.AdmissionPlaceData[],
};
export type AdmissionPlaceData = {
campus_ulid: string,
class_ulid: string,
};
export type ApplicationCardData = {
ulid: string,
name: string,
class_name: string,
campus_name: string,
source: string,
parent_name: string,
stage: string,
decision: string | null,
is_closed: boolean,
is_locked: boolean,
days: number,
missing_documents: number,
student_ulid: string | null,
};
export type ApplicationDetailData = {
ulid: string,
name: string,
stage: string,
stage_label: string,
next_stage: string | null,
next_stage_label: string | null,
decision: string | null,
decision_label: string | null,
is_closed: boolean,
is_locked: boolean,
class_name: string,
campus_name: string,
gender: string,
gender_label: string,
born_on: string | null,
blood_group: string | null,
address: string | null,
house_ulid: string | null,
parent_name: string,
parent_relation: string,
phone: string,
email: string | null,
source_label: string,
enquired_on: string,
notes: string | null,
details_complete: boolean,
missing_documents: number,
student_ulid: string | null,
documents: App.Domain.Admissions.Data.DocumentRowData[],
history: App.Domain.Admissions.Data.HistoryRowData[],
};
export type BoardData = {
summary: App.Domain.Admissions.Data.BoardSummaryData,
stages: App.Domain.Admissions.Data.StageData[],
cards: App.Domain.Admissions.Data.ApplicationCardData[],
};
export type BoardSummaryData = {
open_applications: number,
new_this_week: number,
enrolled: number,
conversion_percent: number,
};
export type ClassOptionData = {
ulid: string,
name: string,
level: number,
};
export type DocumentRowData = {
kind: string,
label: string,
is_required: boolean,
is_received: boolean,
file_name: string | null,
uploaded_on: string | null,
view_url: string | null,
};
export type DocumentRuleData = {
kind: string,
label: string,
is_required: boolean,
all_classes: boolean,
class_ulids: string[],
};
export type DocumentSettingsData = {
rules: App.Domain.Admissions.Data.DocumentRuleData[],
classes: App.Domain.Admissions.Data.ClassOptionData[],
is_default: boolean,
};
export type EnrolledApplicantData = {
student_ulid: string,
name: string,
section_label: string,
admission_number: string,
roll_number: number,
};
export type HistoryRowData = {
label: string,
happened_on: string,
note: string | null,
};
export type StageData = {
stage: string,
label: string,
count: number,
};
}
namespace Enums {
export type AdmissionDecision = 'accepted' | 'waitlisted' | 'rejected';
export type AdmissionDocumentKind = 'birth_certificate' | 'aadhaar' | 'transfer_certificate' | 'photo';
export type AdmissionSource = 'walk_in' | 'website' | 'referral' | 'facebook_ad' | 'newspaper';
export type AdmissionStage = 'enquiry' | 'application' | 'verified' | 'decision' | 'enrolled';
}
}
namespace Attendance {
namespace Data {
export type AbsentStudentData = {
student_ulid: string,
name: string,
roll_number: number | null,
admission_number: string,
section_label: string,
campus_name: string,
guardian_name: string | null,
guardian_relation: string | null,
guardian_phone: string | null,
days_in_a_row: number,
marked_by: string | null,
marked_at: string,
};
export type AttendanceDashboardData = {
today: string,
closed_reason: string | null,
percent_tenths: number | null,
absent: number,
sections_marked: number,
sections_total: number,
trend: App.Domain.Attendance.Data.AttendanceTrendPointData[],
unmarked_sections: string[],
frequent_absent_count: number,
frequent_absentees: string[],
frequent_absence_threshold: number,
frequent_absence_days: number,
absentees: App.Domain.Attendance.Data.AbsentStudentData[],
absentees_total: number,
};
export type AttendanceTrendPointData = {
date: string,
percent_tenths: number | null,
};
export type RegisterData = {
sections: App.Domain.Attendance.Data.SectionChoiceData[],
section: string | null,
date: string,
today: string,
min_date: string,
timezone: string,
state: App.Domain.Attendance.Enums.RegisterState,
closed_reason: string | null,
can_edit: boolean,
needs_reason: boolean,
age_days: number,
correction_window_days: number,
class_teacher_name: string | null,
saved: App.Domain.Attendance.Data.SavedRegisterData | null,
students: App.Domain.Attendance.Data.RosterStudentData[],
can_manage_settings: boolean,
};
export type RosterStudentData = {
ulid: string,
name: string,
admission_number: string,
roll_number: number | null,
status: App.Domain.Attendance.Enums.AttendanceStatus | null,
recent_absences: number,
};
export type SavedRegisterData = {
saved_by: string | null,
saved_at: string,
reason: string | null,
};
export type SectionChoiceData = {
ulid: string,
label: string,
campus_name: string,
relation: string,
};
export type StudentDayData = {
date: string,
state: App.Domain.Attendance.Enums.DayState,
note: string | null,
marked_by: string | null,
marked_at: string | null,
reason: string | null,
};
export type StudentTallyData = {
present: number,
late: number,
absent: number,
school_days: number,
percent_tenths: number | null,
};
}
namespace Enums {
export type AttendanceStatus = 'present' | 'absent' | 'late';
export type DayState = 'present' | 'absent' | 'late' | 'holiday' | 'off' | 'upcoming' | 'unmarked';
export type RegisterState = 'closed' | 'view_only' | 'locked' | 'saved' | 'pending' | 'missed';
}
}
namespace Audit {
namespace Enums {
export type AuditActorType = 'user' | 'staff' | 'guardian' | 'platform' | 'system';
export type AuditScope = 'tenant' | 'platform';
}
}
namespace Exams {
namespace Data {
export type ApprovalCardData = {
ulid: string,
student_ulid: string,
student_name: string,
admission_number: string,
roll_number: number | null,
kind: App.Domain.Exams.Enums.ReportCardKind,
status: App.Domain.Exams.Enums.ReportCardStatus,
prepared_on: string,
file_name: string | null,
return_note: string | null,
};
export type ApprovalGroupData = {
section_ulid: string,
section_label: string,
campus_name: string,
exam_ulid: string,
exam_name: string,
class_teacher_name: string | null,
awaiting_count: number,
published_count: number,
student_count: number,
cards: App.Domain.Exams.Data.ApprovalCardData[],
};
export type ExamCampusOptionData = {
ulid: string,
name: string,
};
export type ExamClassOptionData = {
ulid: string,
name: string,
};
export type ExamClassSubjectsData = {
class_ulid: string,
subjects: App.Domain.Exams.Data.ExamSubjectOptionData[],
};
export type ExamClosedDayData = {
date: string,
name: string,
};
export type ExamDateSheetRowData = {
subject_ulid: string,
subject_name: string,
held_on: string,
begins_at: string | null,
};
export type ExamFormOptionsData = {
types: App.Domain.Exams.Data.ExamTypeOptionData[],
classes: App.Domain.Exams.Data.ExamClassOptionData[],
campuses: App.Domain.Exams.Data.ExamCampusOptionData[],
may_choose_all_classes: boolean,
may_choose_all_campuses: boolean,
subjects_by_class: App.Domain.Exams.Data.ExamClassSubjectsData[],
working_days: number[],
holidays: App.Domain.Exams.Data.ExamClosedDayData[],
today: string,
default_starts_on: string,
};
export type ExamMarksProgressData = {
sheets: number,
entered: number,
published: number,
};
export type ExamRowData = {
ulid: string,
name: string,
type: App.Domain.Exams.Enums.ExamType,
type_label: string,
created_by: string | null,
starts_on: string,
ends_on: string,
status: App.Domain.Exams.Enums.ExamStatus,
max_marks: number,
max_marks_locked: boolean,
instructions: string | null,
show_on_calendar: boolean,
all_classes: boolean,
class_ulids: string[],
all_campuses: boolean,
campus_ulids: string[],
classes_label: string,
date_sheet: App.Domain.Exams.Data.ExamDateSheetRowData[],
can_change: boolean,
can_publish: boolean,
can_delete: boolean,
marks_progress: App.Domain.Exams.Data.ExamMarksProgressData | null,
};
export type ExamSubjectOptionData = {
ulid: string,
name: string,
};
export type ExamTypeOptionData = {
value: App.Domain.Exams.Enums.ExamType,
label: string,
default_max_marks: number,
};
export type ExamsOverviewData = {
scheduled_count: number,
marking_count: number,
published_count: number,
cancelled_count: number,
exams: App.Domain.Exams.Data.ExamRowData[],
form: App.Domain.Exams.Data.ExamFormOptionsData,
};
export type GradeBandData = {
grade: string,
min_percent: number,
max_percent: number,
grade_point: number,
is_lowest: boolean,
};
export type GradeScaleScreenData = {
grades: App.Domain.Exams.Data.GradeBandData[],
is_saved: boolean,
default_grades: App.Domain.Exams.Data.GradeBandData[],
frozen_exam_count: number,
report_card_approval: boolean,
};
export type MarkRowData = {
student_ulid: string,
roll_number: number | null,
name: string,
value: string,
};
export type MarksExamOptionData = {
ulid: string,
name: string,
max_marks: number,
status: App.Domain.Exams.Enums.ExamStatus,
};
export type MarksScreenData = {
exams: App.Domain.Exams.Data.MarksExamOptionData[],
sections: App.Domain.Exams.Data.MarksSectionOptionData[],
subjects: App.Domain.Exams.Data.MarksSubjectOptionData[],
sheet: App.Domain.Exams.Data.MarksSheetData | null,
scale: App.Domain.Exams.Data.GradeBandData[],
pass_percent: number,
can_change_scale: boolean,
};
export type MarksSectionOptionData = {
ulid: string,
label: string,
campus_name: string,
};
export type MarksSheetData = {
exam_ulid: string,
section_ulid: string,
subject_ulid: string,
exam_name: string,
exam_status: App.Domain.Exams.Enums.ExamStatus,
section_label: string,
subject_name: string,
max_marks: number,
state: App.Domain.Exams.Enums.MarkSheetState,
teacher_name: string | null,
published_on: string | null,
published_by: string | null,
reopened_by: string | null,
reopen_reason: string | null,
can_reopen: boolean,
rows: App.Domain.Exams.Data.MarkRowData[],
};
export type MarksSubjectOptionData = {
ulid: string,
name: string,
is_mine: boolean,
may_enter: boolean,
};
export type ReportCardApprovalsData = {
status: App.Domain.Exams.Enums.ReportCardStatus | null,
sections: App.Domain.Exams.Data.MarksSectionOptionData[],
section_ulid: string | null,
awaiting_count: number,
published_count: number,
sent_back_count: number,
groups: App.Domain.Exams.Data.ApprovalGroupData[],
};
export type ReportCardRowData = {
student_ulid: string,
roll_number: number | null,
name: string,
marks: App.Domain.Exams.Enums.MarksReadiness,
missing_subjects: string[],
card: App.Domain.Exams.Data.ReportCardSummaryData | null,
};
export type ReportCardSummaryData = {
ulid: string,
kind: App.Domain.Exams.Enums.ReportCardKind,
status: App.Domain.Exams.Enums.ReportCardStatus,
prepared_on: string,
file_name: string | null,
return_note: string | null,
};
export type ReportCardViewData = {
ulid: string,
kind: App.Domain.Exams.Enums.ReportCardKind,
status: App.Domain.Exams.Enums.ReportCardStatus,
exam_name: string,
session_name: string,
prepared_by: string,
prepared_on: string,
card: App.Domain.Students.Data.ReportCardData | null,
file_url: string | null,
file_name: string | null,
is_image: boolean,
};
export type ReportCardsScreenData = {
sections: App.Domain.Exams.Data.MarksSectionOptionData[],
section_ulid: string | null,
exams: App.Domain.Exams.Data.MarksExamOptionData[],
exam_ulid: string | null,
exam_name: string | null,
approval_required: boolean,
approvers: string | null,
not_started_count: number,
sent_back_count: number,
awaiting_count: number,
published_count: number,
rows: App.Domain.Exams.Data.ReportCardRowData[],
};
export type SchoolReportCardData = {
ulid: string,
exam_name: string,
kind: App.Domain.Exams.Enums.ReportCardKind,
prepared_by: string,
prepared_on: string,
view_url: string,
};
export type StudentExamResultData = {
ulid: string,
name: string,
held_on: string,
max_marks: number,
pending_count: number,
subjects: App.Domain.Exams.Data.StudentSubjectResultData[],
total_marks: string | null,
total_max: number | null,
percent_tenths: number | null,
grade: string | null,
is_lowest_grade: boolean,
};
export type StudentResultsData = {
exams: App.Domain.Exams.Data.StudentExamResultData[],
scale: App.Domain.Exams.Data.GradeBandData[],
};
export type StudentSubjectResultData = {
subject_name: string,
state: App.Domain.Exams.Enums.SubjectResultState,
marks: string | null,
percent: number | null,
grade: string | null,
grade_point: number | null,
is_lowest_grade: boolean,
};
}
namespace Enums {
export type ExamStatus = 'scheduled' | 'marking' | 'published' | 'cancelled';
export type ExamType = 'unit_test' | 'periodic_test' | 'half_yearly' | 'annual' | 'practical' | 'other';
export type MarkSheetState = 'editable' | 'view_only' | 'published' | 'closed';
export type MarksReadiness = 'missing' | 'unpublished' | 'ready';
export type ReportCardKind = 'generated' | 'file';
export type ReportCardStatus = 'submitted' | 'returned' | 'published';
export type SubjectResultState = 'published' | 'absent' | 'not_entered' | 'awaiting';
}
}
namespace Identity {
namespace Data {
export type SchoolContextData = {
is_parent_view: boolean,
can_open_parent_view: boolean,
can_open_staff_view: boolean,
};
}
}
namespace Platform {
namespace Enums {
export type SuspensionReason = 'invoice_overdue' | 'requested_by_school' | 'terms_review';
}
}
namespace School {
namespace Data {
export type AttendanceCalendarData = {
working_days: number[],
correction_window_days: number,
holidays: App.Domain.School.Data.HolidayData[],
};
export type CampusOptionData = {
ulid: string,
name: string,
};
export type CampusSwitcherData = {
campuses: App.Domain.School.Data.CampusOptionData[],
selected: string | null,
};
export type ClassCampusData = {
ulid: string,
name: string,
addable_classes: App.Domain.School.Data.ClassLevelOptionData[],
};
export type ClassLevelOptionData = {
level: number,
name: string,
};
export type ClassSectionData = {
ulid: string,
name: string,
class_ulid: string,
class_name: string,
class_level: number,
campus_ulid: string,
room: string | null,
capacity: number | null,
students: number,
girls: number,
class_teacher_name: string | null,
register_saved_at: string | null,
register_absent: number,
can_manage: boolean,
next_section_name: string | null,
};
export type ClassesOverviewData = {
academic_year_ulid: string | null,
academic_year_name: string | null,
campus_name: string | null,
timezone: string,
has_attendance: boolean,
can_manage: boolean,
campuses: App.Domain.School.Data.ClassCampusData[],
sections: App.Domain.School.Data.ClassSectionData[],
free_teachers: App.Domain.Access.Data.StaffOptionData[],
class_ulids: Record<string, string>,
};
export type HolidayData = {
ulid: string,
holiday_on: string,
name: string,
};
export type HouseData = {
ulid: string,
name: string,
status: string,
student_count: number,
};
export type ParentChapterData = {
number: number,
title: string,
state: App.Domain.School.Enums.ChapterState,
};
export type ParentSubjectData = {
name: string,
book: string | null,
teacher_name: string | null,
periods_per_week: number | null,
percent_done: number | null,
current_chapter: string | null,
teacher_note: string | null,
student_note: string | null,
assessment: string | null,
chapters: App.Domain.School.Data.ParentChapterData[],
};
export type SchoolDashboardData = {
staff_name: string | null,
part_of_day: string,
today: string,
campus_name: string | null,
academic_year_name: string | null,
timezone: string,
attendance: App.Domain.Attendance.Data.AttendanceDashboardData | null,
admissions: App.Domain.Admissions.Data.AdmissionFunnelData | null,
holidays: App.Domain.School.Data.UpcomingHolidayData[],
};
export type SchoolSettingsData = {
admission_prefix: string,
tc_prefix: string,
};
export type ScopeOptionsData = {
campuses: App.Domain.School.Data.CampusOptionData[],
sections: App.Domain.School.Data.SectionOptionData[],
levels: App.Domain.School.Data.ClassLevelOptionData[],
};
export type SectionOptionData = {
ulid: string,
label: string,
campus_name: string,
campus_ulid: string,
};
export type SectionTeachersData = {
ulid: string,
name: string,
class_teacher_name: string | null,
can_change: boolean,
rows: App.Domain.School.Data.SubjectTeacherRowData[],
};
export type SessionOptionData = {
ulid: string,
name: string,
is_current: boolean,
};
export type SessionSwitcherData = {
sessions: App.Domain.School.Data.SessionOptionData[],
selected: string,
};
export type SubjectClassData = {
ulid: string,
name: string,
level: number,
section_count: number,
subject_ulids: string[],
can_change: boolean,
};
export type SubjectRowData = {
ulid: string,
name: string,
code: string,
type: App.Domain.School.Enums.SubjectType,
taught_in: string[],
can_change: boolean,
can_delete: boolean,
};
export type SubjectSectionOptionData = {
ulid: string,
name: string,
campus_name: string,
};
export type SubjectTeacherRowData = {
subject_ulid: string,
subject_name: string,
teacher_ulid: string | null,
automatic_name: string | null,
};
export type SubjectsOverviewData = {
can_configure: boolean,
campus_name: string | null,
classes: App.Domain.School.Data.SubjectClassData[],
subjects: App.Domain.School.Data.SubjectRowData[],
sections: App.Domain.School.Data.SubjectSectionOptionData[],
selected_section: App.Domain.School.Data.SectionTeachersData | null,
staff: App.Domain.Access.Data.StaffOptionData[],
};
export type SyllabusChoiceData = {
section_ulid: string,
subject_ulid: string,
section_name: string,
subject_name: string,
campus_name: string,
via_class_teacher: boolean,
};
export type SyllabusClassData = {
book: string | null,
chapters: string[],
assessment: string | null,
is_saved: boolean,
copied_from: string | null,
updated_on: string | null,
updated_by: string | null,
};
export type SyllabusDetailData = {
section_ulid: string,
subject_ulid: string,
section_name: string,
class_name: string,
subject_name: string,
section_count: number,
can_edit_class: boolean,
class_syllabus: App.Domain.School.Data.SyllabusClassData,
progress: App.Domain.School.Data.SyllabusProgressData,
students: App.Domain.School.Data.SyllabusStudentNoteData[],
};
export type SyllabusProgressData = {
done_chapters: number[],
current_chapter: number | null,
periods_per_week: number | null,
note: string,
updated_on: string | null,
updated_by: string | null,
};
export type SyllabusScreenData = {
year_name: string,
choices: App.Domain.School.Data.SyllabusChoiceData[],
selected: App.Domain.School.Data.SyllabusDetailData | null,
};
export type SyllabusStudentNoteData = {
ulid: string,
roll_number: number | null,
name: string,
note: string,
};
export type UpcomingHolidayData = {
name: string,
starts_on: string,
ends_on: string | null,
days_away: number,
};
}
namespace Enums {
export type ChapterState = 'done' | 'current' | 'upcoming';
export type SubjectType = 'core' | 'elective' | 'co_scholastic';
}
}
namespace Students {
namespace Data {
export type EnrollmentHistoryData = {
academic_year: string,
class_label: string,
campus_name: string,
result: string,
is_current: boolean,
note: string | null,
started_on: string,
ended_on: string | null,
};
export type GuardianData = {
ulid: string,
name: string,
relation: string,
is_primary: boolean,
phone: string,
email: string | null,
occupation: string | null,
has_portal_access: boolean,
parent_app: App.Domain.Students.Enums.ParentAppStatus,
other_children: string[],
hidden_other_children: number,
};
export type GuardianEditData = {
ulid: string,
name: string,
relation: string,
phone: string,
email: string,
occupation: string,
is_primary: boolean,
has_portal_access: boolean,
};
export type ImportPreviewData = {
file_name: string,
missing_columns: string[],
rows: App.Domain.Students.Data.ImportRowData[],
ready: number,
with_warnings: number,
need_fixing: number,
};
export type ImportResultData = {
created: number,
skipped: number,
families_linked: number,
students: App.Domain.Students.Data.ImportedStudentData[],
};
export type ImportRowData = {
line: number,
student_name: string,
details: string | null,
class_label: string | null,
guardian: string,
status: App.Domain.Students.Enums.ImportRowStatus,
errors: string[],
warnings: string[],
};
export type ImportedStudentData = {
ulid: string,
name: string,
class_label: string,
admission_number: string,
};
export type OptionData = {
value: string,
label: string,
};
export type ParentAttendanceData = {
today: string,
month: string,
previous_month: string | null,
next_month: string | null,
days: App.Domain.Attendance.Data.StudentDayData[],
month_tally: App.Domain.Attendance.Data.StudentTallyData,
year_tally: App.Domain.Attendance.Data.StudentTallyData,
session_name: string,
is_current_session: boolean,
minimum_percent: number,
correction_window_days: number,
absences: App.Domain.Attendance.Data.StudentDayData[],
};
export type ParentChildData = {
ulid: string,
name: string,
first_name: string,
relation: string,
section_label: string,
campus_name: string,
roll_number: number | null,
class_teacher_name: string | null,
};
export type ParentHomeData = {
today: string,
today_state: App.Domain.Attendance.Data.StudentDayData,
week: App.Domain.Attendance.Data.StudentDayData[],
month: App.Domain.Attendance.Data.StudentTallyData,
month_label: string,
year: App.Domain.Attendance.Data.StudentTallyData,
session_name: string,
is_current_session: boolean,
};
export type ParentShellData = {
school_name: string,
guardian_name: string,
guardian_first_name: string,
children: App.Domain.Students.Data.ParentChildData[],
selected_child: string | null,
sessions: App.Domain.School.Data.SessionSwitcherData | null,
has_staff_context: boolean,
attendance_enabled: boolean,
exams_enabled: boolean,
timezone: string,
};
export type ReportCardData = {
school_name: string,
board: string | null,
campus_name: string,
campus_address: string | null,
session_name: string,
student_name: string,
admission_number: string,
born_on: string,
class_label: string,
class_teacher_name: string | null,
attendance: App.Domain.Attendance.Data.StudentTallyData | null,
result: App.Domain.Exams.Data.StudentExamResultData,
remark: string | null,
};
export type SectionOptionData = {
ulid: string,
label: string,
campus_name: string,
enrolled: number,
capacity: number | null,
is_full: boolean,
};
export type SiblingData = {
ulid: string,
name: string,
class_label: string | null,
campus_name: string | null,
via: App.Domain.Students.Enums.SiblingVia,
};
export type StudentAbilitiesData = {
manage: boolean,
issue_tc: boolean,
documents: boolean,
link_siblings: boolean,
view_sensitive: boolean,
};
export type StudentDocumentData = {
ulid: string,
kind: string,
label: string,
original_name: string,
is_verified: boolean,
verified_by: string | null,
uploaded_on: string,
source: string,
view_url: string | null,
};
export type StudentEditData = {
first_name: string,
last_name: string,
gender: string,
born_on: string,
blood_group: string,
house_ulid: string,
address: string,
roll_number: number | null,
guardians: App.Domain.Students.Data.GuardianEditData[],
};
export type StudentListData = {
rows: App.Domain.Students.Data.StudentRowData[],
total: number,
from: number,
to: number,
current_page: number,
last_page: number,
current_students_total: number,
};
export type StudentOptionsData = {
genders: App.Domain.Students.Data.OptionData[],
blood_groups: App.Domain.Students.Data.OptionData[],
houses: App.Domain.Students.Data.OptionData[],
transfer_reasons: App.Domain.Students.Data.OptionData[],
document_kinds: App.Domain.Students.Data.OptionData[],
};
export type StudentProfileData = {
ulid: string,
name: string,
admission_number: string,
status: string,
is_current: boolean,
class_label: string | null,
roll_number: number | null,
campus_name: string | null,
gender: string,
born_on: string,
blood_group: string,
house_name: string | null,
address: string,
admitted_on: string,
left_on: string | null,
leave_reason: string | null,
tc_number: string | null,
siblings: App.Domain.Students.Data.SiblingData[],
hidden_siblings: number,
guardians: App.Domain.Students.Data.GuardianData[],
enrollments: App.Domain.Students.Data.EnrollmentHistoryData[],
documents: App.Domain.Students.Data.StudentDocumentData[] | null,
abilities: App.Domain.Students.Data.StudentAbilitiesData,
edit: App.Domain.Students.Data.StudentEditData | null,
session_name: string | null,
section_options: App.Domain.Students.Data.SectionOptionData[],
};
export type StudentRowData = {
ulid: string,
name: string,
admission_number: string,
status: string,
class_label: string | null,
roll_number: number | null,
campus_name: string | null,
guardian_name: string | null,
guardian_relation: string | null,
guardian_phone: string | null,
has_sibling: boolean,
is_new: boolean,
};
export type TransferCertificateData = {
school_name: string,
tc_number: string,
student_name: string,
admission_number: string,
born_on: string,
gender: string,
class_label: string | null,
admitted_on: string,
last_day: string,
reason: string,
guardian_names: string[],
};
}
namespace Enums {
export type BloodGroup = 'A+' | 'A-' | 'B+' | 'B-' | 'AB+' | 'AB-' | 'O+' | 'O-' | 'unknown';
export type DocumentKind = 'birth_certificate' | 'aadhaar' | 'transfer_certificate' | 'photo' | 'medical' | 'category_certificate' | 'other';
export type EnrollmentResult = 'in_progress' | 'promoted' | 'moved' | 'left';
export type Gender = 'male' | 'female' | 'other';
export type ImportRowStatus = 'ready' | 'warning' | 'fix';
export type ParentAppStatus = 'not_invited' | 'invited' | 'active';
export type SiblingVia = 'guardian' | 'staff';
export type StudentStatus = 'enrolled' | 'left' | 'transferred' | 'alumni';
export type TransferReason = 'family_relocating' | 'changing_school' | 'other';
}
}
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
