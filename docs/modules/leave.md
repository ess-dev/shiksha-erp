# Student leave module

Built 2026-10-26 from the owner's prototype screenshots (parent: Leave; teacher: Student leave) and the owner's answers.

## Purpose

A parent applies for leave for a child; the class teacher approves or rejects short leave; leave longer than the school's limit is recommended by the class teacher and decided by the coordinator or principal. Approved leave shows on the attendance register and the parent's attendance page, and an absence on approved leave sends no alert.

## Screens

| Screen                    | Route                         | Needs                                   | Notes                                                                                                      |
| ------------------------- | ----------------------------- | --------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Parent Leave              | `/parent/leave?child=<ulid>`  | parent sign-in                          | Apply form (dates, reason type, details, optional certificate), school-day count, "Your requests", cancel. |
| Staff Student leave       | `/leave` (`?tab=review\|all`) | `leave.approve` or `leave.approve_long` | To review / All leave, approve, reject, recommend with a note; "on approved leave today" banner.           |
| Approvals > Student leave | `/approvals`                  | `leave.approve_long`                    | Recommended requests and long requests still with the class teacher, over the person's scope.              |
| Leave settings            | `/leave/settings`             | `settings.manage`                       | The class teacher's limit in school days (default 3, 1 to 30).                                             |

## Rules

- **Not a plan module**: leave is core (like students). No `module:` middleware.
- **Applying**: current session only; start date at most 3 days back; end not before start; inside the academic year; at least one school day (weekly days off and holidays); at most 30 days; no overlap with another pending, recommended or approved request of the same child; details 10 to 300 characters; optional photo or PDF up to 5 MB (type read from content, `tenant` disk, never public).
- **Long leave**: `needs_coordinator` is set when school days exceed the school setting `leave.escalation_days` (default 3, illustrative — verify) and is kept on the request, so a later change applies only to new requests.
- **Who decides**: holders of `leave.approve` over the child's section approve or reject short leave; for long leave they recommend (note required) or reject (reason required). Holders of `leave.approve_long` over the section approve or reject any open request, recommended or not. Out-of-scope requests are 404, a decision the person may not take is 403. Settled requests cannot be decided again.
- **Parent**: may cancel while pending or recommended; approved, rejected or cancelled requests stay as history. Past sessions are view only. Requests are never deleted.
- **Attendance**: an approved request marks the student "On approved leave" on the register; Mark all present marks them absent; saving the register tells parents nothing about them (the `AbsenceNotifier` gets no entry). The parent's attendance page shows "Approved leave" on those absences.
- **Attachments**: opened by staff through a short-lived signed link after a scope check; every opening is audited.
- **Notifications**: `LeaveNotifier` (events `applied`, `recommended`, `approved`, `rejected`, `cancelled`) is bound to a log-only driver. No notification row yet.

## Data (tenant)

`student_leave_requests` (status CHECK, reason-type CHECK, dates CHECK; migration `2026_11_12_000000_create_student_leave_tables`). No landlord table change.

## Code

`app/Domain/Leave/`: Actions `ApplyForLeave`, `CancelLeave`, `DecideLeave`, `OpenLeaveAttachment`; Queries `ParentLeaveQuery`, `StaffLeaveQuery`, `ApprovedLeaveQuery`, `LeaveFileQuery`; `LeaveAccess`, `LeaveRules`, `LeaveAttachmentStore`; `LeaveNotifier` + `LogLeaveNotifier`. School: `UpdateLeaveSettings`, `SchoolSettingsQuery::leaveEscalationDays()`. Controllers `Leave\LeaveController`, `LeaveSettingsController`, `LeaveFileController`, `Guardian\LeaveController`. Pages `leave/index.tsx`, `leave/settings.tsx`, `parent/leave.tsx`, component `components/leave/leave-card.tsx`.

## Permissions

`leave.approve` moved from module `leave` to `core` (the module could never exist in a plan). New `leave.approve_long` (core, sensitive). Role templates: Principal, Vice principal, Coordinator **version 13**. Existing schools must apply the template update.

## Audit keys

`student_leave.applied`, `.recommended`, `.approved`, `.rejected`, `.cancelled`, `.attachment_opened`, `settings.leave_updated`.

## Known limitations

- The exam-overlap warning of the prototype is not built.
- The parent form names "the class teacher" but not the coordinator (no way yet to name who covers a section).
- "Ask principal" escalation and the sidebar count badge are not built.
- No notification rows or delivery; no sick-day reminders.

# Staff leave

Built 2026-10-26 from the owner's screenshots (Principal: Staff and leave; Teacher: My leave) and answers.

## Screens

| Screen          | Route                             | Needs                                     | Notes                                                                                                                                       |
| --------------- | --------------------------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| My leave        | `/my-leave`                       | an active staff record                    | Balances by type, apply form (type, full days or half day, dates, reason, suggested cover, certificate), My requests, cancel while pending. |
| Staff and leave | `/staff-leave` (`?tab=directory`) | `staff.leave_approve` and/or `staff.view` | Leave requests (Approve with cover choice, Reject with a note) and a staff directory (name, roles, class, In school / On leave).            |
| Leave settings  | `/leave/settings`                 | `settings.manage`                         | Student limit as before, plus yearly allowance and certificate limit per staff leave type.                                                  |

## Rules

- **Applying**: current session; start at most 3 days back; end not before start; inside the academic year; at least one school day; at most 30 days; no overlap with own pending or approved leave; reason 5 to 300 characters; optional photo or PDF up to 5 MB (same store as student leave). Half day = one date, counts 0.5. Stored as `half_days` (2 per day).
- **Balance**: per type from the school setting `leave.staff_policy` (illustrative defaults: casual 12, sick 10 with a certificate beyond 2 days, earned 15, duty as needed — verify). Approved plus pending leave of the session counts; a request that does not fit is refused.
- **Who decides**: holders of `staff.leave_approve`. A class teacher's request is decided by someone whose scope covers that class (coordinator: their wing); anyone else's (no class) only by a school-wide holder. Nobody decides their own leave. Out of scope is 404; own leave is 403. Rejecting needs a note of at least 5 characters.
- **Register cover**: approving a class teacher's leave needs a cover. They must be an active staff member who is not the applicant and not on approved leave on those days. The cover may open and mark that class's register on the leave dates only, with the class teacher's rules (free on the day; a reason on later days inside the correction window), and may still open it until the correction window after the last day closes. It is worked out from the approved request: cancelling is only possible while pending, rejecting never grants it, and nothing is stored apart from the request. The Attendance class picker marks it "covering".
- **Attachments**: opened through a short-lived signed link by the applicant or an approver in scope; every opening is audited.
- **Notifications**: `StaffLeaveNotifier` (events `applied`, `approved`, `rejected`, `cancelled`) is bound to a log-only driver.

## Data (tenant)

`staff_leave_requests` (status, type and date CHECKs; a half day must be a single date; migration `2026_11_13_000000_create_staff_leave_tables`).

## Code

`app/Domain/Leave/`: Actions `ApplyForStaffLeave`, `CancelStaffLeave`, `DecideStaffLeave`, `OpenStaffLeaveAttachment`; Queries `MyLeaveQuery`, `StaffLeaveRequestsQuery`, `StaffLeaveItemBuilder`, `StaffLeaveFileQuery`, `StaffLeaveCoverQuery`; `StaffLeaveAccess`, `StaffLeaveBalances`, `StaffLeavePolicy`; `StaffLeaveNotifier` + `LogStaffLeaveNotifier`. School: `UpdateStaffLeavePolicy`, `SchoolSettingsQuery::staffLeavePolicy()`. Attendance: `AttendanceAccess` (cover rules). Controllers `Leave\MyLeaveController`, `StaffLeaveController`, `StaffLeaveFileController`. Pages `leave/my-leave.tsx`, `leave/staff.tsx`, component `components/leave/staff-leave-row.tsx`.

## Permissions

New `staff.leave_approve` (core, sensitive). Role templates Principal, Vice principal, Coordinator **version 14**. Existing schools must apply the template update.

## Audit keys

`staff_leave.applied`, `.approved`, `.rejected`, `.cancelled`, `.attachment_opened`, `settings.staff_leave_policy_updated`.

## Known limitations

- No staff profile fields (joined date, department, campus, subjects, contact): the directory shows name, roles, class and today's status only.
- The "Goes to ..." line on My leave is generic; the approver is not looked up.
- No period-wise cover for subject teachers, no exam or meeting overlap warnings, no notification rows or delivery.
- A principal's own leave stays pending until another school-wide holder of `staff.leave_approve` exists.
