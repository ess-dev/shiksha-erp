# Student leave module

Built 2026-10-26 from the owner's prototype screenshots (parent: Leave; teacher: Student leave) and the owner's answers.

## Purpose

A parent applies for leave for a child; the class teacher approves or rejects short leave; leave longer than the school's limit is recommended by the class teacher and decided by the coordinator or principal. Approved leave shows on the attendance register and the parent's attendance page, and an absence on approved leave sends no alert.

## Screens

| Screen                 | Route                          | Needs                                                  | Notes                                                                                                         |
| ---------------------- | ------------------------------ | ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------- |
| Parent Leave           | `/parent/leave?child=<ulid>`   | parent sign-in                                         | Apply form (dates, reason type, details, optional certificate), school-day count, "Your requests", cancel.    |
| Staff Student leave    | `/leave` (`?tab=review\|all`)  | `leave.approve` or `leave.approve_long`                | To review / All leave, approve, reject, recommend with a note; "on approved leave today" banner.              |
| Approvals > Student leave | `/approvals`                | `leave.approve_long`                                   | Recommended requests and long requests still with the class teacher, over the person's scope.                |
| Leave settings         | `/leave/settings`              | `settings.manage`                                      | The class teacher's limit in school days (default 3, 1 to 30).                                                |

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
- "Ask principal" escalation, teacher (staff) leave and the sidebar count badge are not built.
- No notification rows or delivery; no sick-day reminders.
