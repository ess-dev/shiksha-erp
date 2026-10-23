# Attendance module

Purpose: the daily register of each class (built 2026-10-23 from the prototype's `teacher/attendance.html` and `assets/js/pages/teacher-attendance.js`). One register per section per school day; every student is marked Present, Absent or Late. Late counts as present for the percentage.

## Screens

- `/attendance` (sidebar "Attendance"): class picker (own class first, "your class" / "view only"), date picker (academic-year start to today), count chips, a status strip, the roster with Present / Absent / Late toggles, Mark all present, Clear, and a sticky save bar ("3 of 14 marked"). A reason dialog for earlier days, a "discard unsaved marks?" prompt when switching class or day, and a summary dialog after saving. Principal and Coordinator use the same page, with a picker limited to their scope.
- `/attendance/settings` (needs `settings.manage`; not in the prototype, designed from `docs/DESIGN.md`): weekly working days, the correction window, and the holiday list.
- School structure (`/school/structure`) gained a **Class teacher** column on sections (a dropdown for people with `structure.manage`).

## Who may do what (decided with the owner, 2026-10-23)

- **See** a class: `attendance.view` or `attendance.mark` over its scope.
- **Mark and edit** a register: only the section's own **class teacher** (with `attendance.mark` over it). Today's register is free to change; on an earlier day inside the correction window (default 7 days, a school setting) a reason is needed; after the window the register is locked for them.
- **Correct** a register on any day, always with a reason: `attendance.correct` over the class (new, sensitive; Principal, Vice principal, Coordinator in role-template **version 6**). This is also how a Coordinator or Principal covers an absent class teacher.
- Everyone else (including a subject teacher who only has `attendance.mark` over a section they teach) sees the class as "view only". Out-of-reach classes are 404, not 403.
- Parents and students have no attendance access yet (Parent sign-in is not built).

## Class teacher and the automatic role

`sections.class_teacher_staff_id` (nullable). `SetClassTeacher` (School) names or clears it and calls `SyncClassTeacherRole` (Access), which keeps **one** automatic assignment (source `auto`) of the school's "Class teacher" role per teacher, scoped to the sections they are class teacher of; it is created, widened, narrowed or removed with each change. Automatic assignments cannot be removed on the Roles screen (the message points back to School structure).

## Tables (tenant, `2026_11_02_000000_create_attendance_tables`)

`attendance_registers` (ulid, section, `attendance_on` date, `saved_by_staff_id`, first/last saved at, last reason; unique per section and day), `attendance_marks` (register, student, status with CHECK; unique per student and register), `school_holidays` (ulid, `holiday_on` unique, name), and the new nullable `sections.class_teacher_staff_id`. Registers and marks are corrected in place with a reason and an audit event, never deleted. Settings keys in `school_settings`: `attendance.working_days` (ISO weekdays, default Monday to Friday) and `attendance.correction_window_days` (default 7). Both defaults are **illustrative — verify**.

## Code

`app/Domain/Attendance/`: Models `AttendanceRegister`, `AttendanceMark`; Enums `AttendanceStatus`, `RegisterState`; `AttendanceAccess` (see / mark / correct rules, one place for screen and Action) and `EditRights`; Action `SaveRegister` (checks the class is open to the person, the date, the school day, every student marked, no-op refusal, reason; writes `attendance.register_saved` or `attendance.register_corrected` with the marks that changed); Queries `RegisterQuery`, `RosterQuery`; Data `RegisterData`, `RosterStudentData`, `SectionChoiceData`, `SavedRegisterData`; `AttendanceException` (form-error key `register`); contract `AbsenceNotifier` with `LogAbsenceNotifier` bound (logs counts only).
School: `Holiday`, Actions `CreateHoliday`, `RemoveHoliday`, `UpdateAttendanceCalendar`, `SetClassTeacher`, Queries `SchoolDayQuery` (weekly days off + holidays), `AttendanceCalendarQuery`, `CalendarException` (form-error key `calendar`). Access: `StaffRefQuery`, `ActiveStaffQuery`, `SyncClassTeacherRole`. Controllers `app/Http/Controllers/Attendance/`, requests `app/Http/Requests/Attendance/`, pages `resources/js/pages/attendance/`, components `resources/js/components/attendance/`.

## Roster rule

A student is on a section's register on a day when enrolled there that day (enrolment start to end, the last day before a transfer still counts). On the day of a section change the student is on the **new** section's register only. A student who already has a mark on a saved register stays on it, so history survives later moves.

## Not built / limits

- **Guardian absence alerts**: `AbsenceNotifier` is the seam; the log driver tells nobody, and the summary dialog says so. Needs Communication and a provider (open decision).
- **Approved-leave badge and "mark all present" skipping students on leave**: needs the Student leave module.
- **Sibling names on a row, period-wise attendance, staff attendance, parent attendance view, monthly reports/percentages, dashboard "pending classes" card, notifying the office of unmarked classes**: not built.
- A holiday applies to the whole school (not per campus); removing one deletes the list entry (the audit trail keeps it).
- Registers are only for the current academic year's sections.
- Browser-checked: see `docs/PROGRESS.md`.
