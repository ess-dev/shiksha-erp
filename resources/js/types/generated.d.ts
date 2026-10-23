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
}
namespace Enums {
export type AttendanceStatus = 'present' | 'absent' | 'late';
export type RegisterState = 'closed' | 'view_only' | 'locked' | 'saved' | 'pending' | 'missed';
}
}
namespace Audit {
namespace Enums {
export type AuditActorType = 'user' | 'staff' | 'guardian' | 'platform' | 'system';
export type AuditScope = 'tenant' | 'platform';
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
export type ClassLevelOptionData = {
level: number,
name: string,
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
export type SessionOptionData = {
ulid: string,
name: string,
is_current: boolean,
};
export type SessionSwitcherData = {
sessions: App.Domain.School.Data.SessionOptionData[],
selected: string,
};
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
