# Homework module

Built 2026-10-26 from the owner's prototype screenshots (teacher: Homework list and detail, New assignment dialog; parent: Homework) and the answers to the questions asked before building.

## Purpose

Teachers set homework for a section and subject they teach, see who handed it in, mark notebook work checked and leave short feedback. Parents see their child's homework by session, upload work done in the app and read the feedback.

## Screens

| Screen                | Route                           | Needs                                                   | Notes                                                                                                                                                     |
| --------------------- | ------------------------------- | ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Teacher Homework      | `/homework`                     | `homework.view` or `homework.assign`, module `homework` | List (newest first, at most 100, optional class filter) and the selected assignment with every student's status. `?section=<ulid>`, `?assignment=<ulid>`. |
| New / Edit assignment | dialog on `/homework`           | `homework.assign` + teaches the section and subject     | Class, subject, title, instructions, due date, submission type, up to 5 attachments (10 MB each).                                                         |
| Parent Homework       | `/parent/homework?child=<ulid>` | parent sign-in, module `homework`                       | Tabs To do / Submitted / Missed / All, attachments, upload, teacher's feedback. Past sessions through the session dropdown (view only).                   |

## Rules

- **Who sets homework**: someone holding `homework.assign` over a section who is that section's chosen subject teacher for the subject, or its class teacher (every subject of their class). Principal, Vice principal and Coordinator hold no teaching pairs, so they only view. Anything else is a 404.
- **Who sees it**: the people whose `homework.view` covers the section, plus the teachers of that section. Class teachers therefore see other teachers' assignments read-only. A subject teacher also sees the other subjects' homework of the sections they teach (assumption, not asked).
- **Who changes it**: only the teacher who set it (edit, delete, mark checked, feedback), while they still hold `homework.assign` over the section. Others get 403.
- **Due date**: after today and a school day (weekly days off and holidays from the attendance calendar). Checked again on edit only when the date changes, so a closed assignment can still be corrected.
- **Editing and deleting at any point.** Delete is a soft delete (homework is on the approved list): parents and teachers stop seeing it, submissions and the audit trail stay. The way of handing in (notebook / upload) cannot change once a submission exists.
- **Submission**: _notebook_ work is handed in by the teacher marking it checked (per student, or "Mark all checked in class"); it counts as submitted and never as late. _Upload_ work is uploaded by a guardian (1 to 5 files); uploads after the due date are accepted and shown as **Late**. One hand-in per child (it is not replaced). A due date that passes with nothing handed in is **Missing** (teacher) / **Missed** (parent). Marking uploaded work checked is a review flag; un-checking leaves the upload.
- **Feedback**: up to 160 characters per student who handed in; parents read it. "To check" counts uploaded work with no feedback and not checked.
- **Files**: stored on the logical `tenant` disk under `tenants/<school>/homework/<assignment>/...` (uploaded work under `.../submissions/<student>/`), never in `public/`. Type is read from the content: photos (JPEG, PNG, WebP), PDF, Word, PowerPoint, Excel and text, up to 10 MB each, 5 files. Opened through 10-minute signed links; access is checked again when opened; non-image/PDF files download. **Opening a child's uploaded work is audited**; teacher attachments are access-checked but not audited (assumption).
- **Photo resizing**: the browser scales photos to at most 1600 px (JPEG) before upload with a small canvas helper (`resources/js/lib/resize-image.ts`); no package added. The server still enforces the limits.
- **Notifications**: `HomeworkNotifier` (events `assigned`, `changed`, `removed`, `feedback`, `submitted`) is bound to a log-only driver. **Not built: a notification row** and delivery; they come with the alerts and notifications module.
- **Parent sessions**: homework belongs to the academic year of its section. Past years are read-only and use the section the child was enrolled in that year. A child who changed section mid-year only sees the current section's homework.

## Data (tenant)

`homework_assignments` (soft deletes), `homework_files`, `homework_submissions` (unique per assignment and student; `submitted_on` and/or `checked_on`, feedback), `homework_submission_files`. Migration `2026_11_11_000000_create_homework_tables`. No landlord change.

## Code

`app/Domain/Homework/`: Actions `CreateHomework`, `UpdateHomework`, `DeleteHomework`, `MarkHomeworkChecked`, `SaveHomeworkFeedback`, `SubmitHomework` (parent), `OpenHomeworkSubmissionFile`; Queries `HomeworkScreenQuery`, `ParentHomeworkQuery`, `HomeworkFileQuery`; helpers `HomeworkAccess`, `HomeworkRules`, `HomeworkFileStore`, `HomeworkFileLinks`; contract `HomeworkNotifier` + `LogHomeworkNotifier`. Controllers `Homework\HomeworkController`, `Homework\HomeworkFileController`, `Guardian\HomeworkController`. Pages `resources/js/pages/homework/index.tsx`, `resources/js/pages/parent/homework.tsx`, components `resources/js/components/homework/`.

## Permissions

No new permissions: `homework.view` and `homework.assign` already existed in the catalog (Class teacher and Subject teacher have both; Coordinator has `homework.view`; Principal and Vice principal have everything). The prototype's `homework.manage` maps to `homework.assign`. `ParentShellData` gained `homework_enabled`.

## Audit keys

`homework.created`, `homework.updated`, `homework.deleted`, `homework.checked`, `homework.unchecked`, `homework.feedback_saved`, `homework.submitted`, `homework.submission_file_opened`.

## Known limitations

- The per-school S3 switch for the `tenant` disk (decision D11) is not built; files use the local `tenant` disk until it is.
- No notification rows or delivery (alerts module next); parents' push for new homework is logged only.
- No re-upload or removal of a parent's hand-in; no due-date-on-holiday warning beyond the server check; no reports or per-student homework history.
- The teacher list is capped at 100 and the parent list at 150 assignments.
