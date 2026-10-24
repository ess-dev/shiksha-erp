# Shiksha ERP — status and what is pending

Quick recall sheet. The detailed record (code map, decisions, verification) is in `docs/PROGRESS.md`; update both when work lands.
Last updated: 2026-10-24.

## Working approach

Screens are built one at a time from prototype screenshots the owner supplies (reference: `school-erp-prototype-v6.zip`, folder `superadmin/` etc.). After each screen the owner reviews, then says which screen is next. Do not start the next screen unprompted.

## Next up

- Owner to pick the next screen. Students slice 1, the roles screen, Admissions and Attendance (teacher's daily register, with Coordinator/Principal oversight) are built; see below.

## To verify later (reminder list for the owner; ask about these in a later session)

Built on 2026-10-22 and covered by automated tests, but not yet tried by the owner with real data:

- **Student documents**: upload, verify and view (PDF/JPEG/PNG up to 5 MB) with real scans; where the files land (`storage/app/tenant/tenants/<school>/students/...`) and later moving the `tenant` disk to S3.
- **Printable transfer certificate**: the layout and wording are a starting point (browser Print / Save as PDF; no signature or seal design); needs the school's format and legal review.
- **CSV import and export** with the school's own spreadsheets (template columns, class names, house names, what warnings are useful).
- **Sibling links** by staff (only when every guardian of both students has portal access) and the one-phone-one-guardian rule on real family data.
- **Campus switcher** in the top bar on the other school pages (today it only narrows the Students list).
- **Placeholders** that need their modules: Attendance, Fees and Marks tabs, class teacher, fee billing on import, cancelling installments on TC, transport.
- **Relaxed fields** to tighten later: blood group "Unknown", house "Not assigned".

Admissions, built 2026-10-23: try Add enquiry, moving cards (arrows and drag), the "Documents still missing" prompt, uploading and viewing scans, Accept and Enroll (the scans should appear on the student's Documents tab), the required-documents page, and the board as a Coordinator and as a Front office user. Role templates are at version 5: each school must apply the update (Roles and access) before non-owner roles see the new permissions.

Attendance, built 2026-10-23: sign in as `teacher@greenfield.test` and mark Class 1 A (Mark all present, change a few, Save); try an earlier day (reason dialog) and a day over 7 days old (locked); as the Principal, set a class teacher on School structure, add a holiday and change working days on `/attendance/settings`, and correct a locked day (needs the role-template update to version 6 on each role: Roles and access). Check the working days (Monday to Friday) and the 7-day window against the school's real rules (illustrative defaults). Guardian absence alerts are **not sent** (no provider yet); the approved-leave badge waits for Student leave.

Subjects, built 2026-10-23: apply role-template update 7 on each role (Roles and access), then try Add subject, Edit (rename with a class using it), Delete (unused only), Change on Subjects by class and choosing subject teachers per section. Automatic means the class teacher until staff have campuses and "subjects taught". The parent's Subjects page (chapters, books, teacher notes) is not built.

Exams and tests, built 2026-10-24 (Principal / permitted staff only; parent view and marks entry come later): apply role-template update 8 on each role, then try New test or exam (one subject a day, a wing coordinator's limited class list), Edit, Open marking, Close marking, Publish all (needs results.publish), Cancel / Restore, Delete and the Date sheet CSV. "Tell parents" only logs (no provider); default maximum marks per type are illustrative.

Teacher's Subjects, built 2026-10-24 (`/teaching/subjects`): apply role-template update (Class teacher 3, Subject teacher 3, Principal/Vice principal/Coordinator 9) on each role. Try the Whole class tab (textbook, chapters, assessment), Section ticks and note, Student notes; choose a subject teacher on the Principal's Subjects screen and sign in as them. Teachers chosen before this update need their choice saved again to get the automatic role. Parent Subjects `/parent/subjects` (built the same day) shows what the teacher saved; try a teacher note and a student note, then open it as that child's parent, and switch to a past session.

Marks, grades and results, built 2026-10-24: apply role-template update 10 on the Principal, Vice principal and Coordinator roles (Roles and access). As `teacher@greenfield.test` open Marks entry (a demo "October Unit Test" with marking open was added locally), type marks, AB and a wrong value, Save draft, Publish; as the Principal or Coordinator open the same sheet and Reopen it with a reason; change the grading scale on Exams and tests → Grading scale (check the CBSE-style default against current board guidance; exams with published marks keep their old scale). As the parent of a child in a published class, open Results and "Open as report card" (print or save as PDF). "Report cards from school" is Coming soon; parents are **not** notified (no provider yet).

Report cards, built 2026-10-24: apply template update 4 on the Class teacher role and 11 on Principal / Vice principal / Coordinator. As `teacher@greenfield.test` open Report cards: Generate (edit the remark), Send for approval, View, Withdraw, Upload a PDF or photo, Generate all missing. Try switching "Report cards need approval" off on Exams and tests → Grading scale. Then, as the Principal or a Coordinator holding Approve report cards, open Approvals (Report cards tab) or Report cards: Preview, Publish, Publish all, Send back with a note, and the Published / Sent back / All filters (built 2026-10-25, not yet checked in the browser). The other three Approvals tabs are empty placeholders. **Known gap**: an owner cannot apply a template update that adds permissions to their own owner role (decision needed).

## School screens: Principal and Teacher

| Screen                                     | Status | Notes                                                                                                                                                                                                                                                 |
| ------------------------------------------ | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Attendance `/attendance` (teacher's view)  | Done   | Class picker (your class / view only), date, chips, strip, Present/Absent/Late rows, Mark all present, Clear, save with reason on earlier days, summary dialog. Coordinator and Principal use it by scope and can correct (new `attendance.correct`). |
| Attendance settings `/attendance/settings` | Done   | Not in the prototype: working days, correction window, holidays. Designed from the guide.                                                                                                                                                             |
| Marks entry `/marks`                       | Done   | Exam / Class / Subject pickers, live % and grade, class summary, grading scale, Save draft, Publish to parents, Reopen (new `marks.reopen`). Coordinator progress page with nudges not built.                                                         |
| Grading scale `/exams/grading`             | Done   | Not in the prototype as a screen: edit grades, starting %, grade points (needs `settings.manage`). Designed from the guide.                                                                                                                           |
| Parent Results `/parent/results`           | Done   | Exam chips, result card, progress chart, How grades work, printable report card. "Report cards from school" is Coming soon.                                                                                                                           |
| Report cards `/report-cards` (teacher)     | Done   | Generate from published marks with an editable remark, Upload a scan, Withdraw, Generate all missing; approval setting on the Grading scale page. Approvers' inbox not built (needs screenshot).                                                      |
| Class teacher on School structure          | Done   | Dropdown per section; gives the automatic Class teacher role over that section.                                                                                                                                                                       |

## School screens: Principal

| Screen                                    | Status         | Notes                                                                                                                                                                     |
| ----------------------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| School structure `/school/structure`      | Done (earlier) | Years, campuses, classes, sections, subjects.                                                                                                                             |
| Students list `/students`                 | Done           | Search, class and status filters, 25 per page, Import, Export CSV, campus switcher; Attendance and Fees show "Not available yet".                                         |
| Student profile `/students/{student}`     | Done           | Overview, Guardians, Enrollment history, Documents built; Attendance, Fees, Marks "Not available yet". Change section, Change roll, Edit details, Issue TC, Link sibling. |
| Houses and numbering `/students/settings` | Done           | Not in the prototype: houses (add, rename, active/inactive, reorder), admission and TC number prefixes.                                                                   |
| New admission                             | Done           | Button links to the Admissions board (needs `admissions.manage`).                                                                                                         |
| Admissions board `/admissions`            | Done           | Enquiry to Enrolled, drag and arrows, Add enquiry, details, documents, decision, Enroll. Fees not created yet. Not yet clicked through in a browser beyond viewing.       |
| Required documents `/admissions/settings` | Done           | Not in the prototype: per-class required documents. Designed from the guide.                                                                                              |

## Super Admin screens (prototype: 9)

| Screen                                             | Status         | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| -------------------------------------------------- | -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sign-in, two-step setup, challenge, recovery codes | Done           | Not in prototype (it has only a role picker). Platform host `admin.shiksha.localhost`.                                                                                                                                                                                                                                                                                                                                                        |
| Dashboard `/overview`                              | Partial        | Done: date, Schools card, Plans card, status doughnut, Super Admin chip. Missing: MRR, Students managed, Trials converting, MRR by plan, Needs attention, Newest schools, Onboard button. These need subscriptions + student counts + school details.                                                                                                                                                                                         |
| Plans `/plans`                                     | Done           | Cards, module matrix, edit dialog, remove-modules confirm, toast. Card footer shows "MRR —" until subscriptions exist.                                                                                                                                                                                                                                                                                                                        |
| Schools (list) `/schools`                          | Done           | Status tabs with counts, search (name/city/board/contact), plan filter, 25 per page, audited CSV export, MRR column. Name is not a link and "Onboard a school" is disabled until School detail and the wizard exist. Only the 2 demo schools exist locally.                                                                                                                                                                                   |
| School detail `/schools/{school}`                  | Partial        | Done: plan change (modules reset to plan), per-school module switches with In plan / Add-on / Turned off badges, suspend (with reason) and reactivate, contact, recent platform actions. Not built: Extend trial, Convert to paid, Renew and activate (expired), Open as principal (support access), usage figures (active users, SMS, storage, last active) and billing invoices (shown as empty states).                                    |
| Onboard a school `/schools/onboard`                | Done (stage A) | 8-step wizard: Organization (with web address), Subscription, Campus, Academic year, Classes and sections (optional quick start, skippable), Invite staff, Import students (only "Do it later" until Students exists), Activate modules. Fees step removed: the accountant sets fee groups in the Fees module. Draft kept in this browser. One audited create at the end; success page shows set-password links once (no email provider yet). |
| Revenue                                            | Pending        | Invoices, GST (tax treatment is an open decision).                                                                                                                                                                                                                                                                                                                                                                                            |
| Roles and permissions                              | Pending        | Backend (catalog, templates) exists; no Super Admin screen.                                                                                                                                                                                                                                                                                                                                                                                   |
| Activity logs                                      | Pending        | `platform_audit_events` is written already; no viewer.                                                                                                                                                                                                                                                                                                                                                                                        |
| My profile                                         | Pending        |                                                                                                                                                                                                                                                                                                                                                                                                                                               |

## Pending backend for Super Admin

- Subscriptions (trial end, renewal date, status, billing), assigning a plan to a school (`tenants.plan_id` column exists, unused), school details (city, board, contact, student count).
- MRR/ARR calculation (needs accountant-approved rounding; do not invent rules).
- Super Admin: password reset/change, lost-authenticator recovery, add/disable Super Admins.
- Owner transfer, support access to a school, platform audit viewer.
- Mapping of plan module keys to the permission catalog modules (`students`, `leave` are not plan modules).

## Other roles (all pending, not started)

Principal/School Admin (22 screens), Coordinator (22), Teacher (17), Accountant (11), Parent (17). Only the school structure screen (`/school/structure`) exists so far. Suggested order is in `docs/PROGRESS.md` ("Next steps").

## Open decisions (do not decide in code)

Production platform host name, tax/GST treatment on plan prices (prices are currently tax-exclusive and illustrative), whether to add a chart library (the dashboard uses plain SVG; `recharts` was proposed, not approved), payment gateway, SMS/email/WhatsApp providers, MVP build order, retention periods, board grading rules.

## Standing rule: school deletion must stay complete

Super Admin can permanently delete a school (`DeleteSchool` action, typed confirmation `delete <school name>`): the school is set to `deleting` (all access blocked), then a queued job `PurgeSchoolData` runs the `PurgeSchool` action, which is retryable from the Super Admin UI if it fails. It drops the school database, removes its landlord rows (`memberships`, `tenant_modules`, `tenant_domains`, identities left with no membership), the `tenants/<ulid>` folder on the `tenant` disk, and keeps an append-only `platform.school_deleted` audit row (the `platform_audit_events.tenant_id` foreign key was dropped so history outlives the school).

**Whenever you add anything new that is owned by a school and lives outside its own database** (a landlord table with `tenant_id`, files outside `tenants/<ulid>`, per-school cache/queue entries, external resources such as S3 buckets or per-school DB roles), add its removal to `app/Domain/Platform/Actions/PurgeSchool.php` and extend `tests/Feature/Platform/DeleteSchoolTest.php`. Data inside the school database needs nothing.

## Housekeeping / known gaps

- No translation keys yet (English literals, like the starter kit pages).
- Only `docs/modules/students.md` exists; the other modules still need one each.
- Pest browser tests not set up; full test suite takes about 4–5 minutes (285 tests passing at last run; one Audit test can fail once with a Postgres autovacuum permission error, passes when re-run). Never run two Pest processes at once.
- If tests ever behave oddly, check `bootstrap/cache/config.php`: a stale cached config once made the tests run against the dev database and wipe it. `php artisan config:clear` fixes it; `php artisan db:seed` rebuilds demo data.
- Orphaned old school databases from that incident remain in PostgreSQL (harmless, can be dropped by hand).
- Apache vhost for `admin.shiksha.localhost` not verified (only `php artisan serve --port=8000`).

## Local quick start

- Demo Super Admin: `superadmin@shiksha.test` / `password` (local only). It has no authenticator until you enrol one at first sign-in: open `http://admin.shiksha.localhost:8000/sign-in`.
- Demo schools: `greenfield.shiksha.localhost`, `riverside.shiksha.localhost` (`principal@greenfield.test` / `password`). The principal must set up two-step first; `coordinator@greenfield.test` / `password` (Classes 1 to 3, Main campus) does not, so it is the quick way to look at the Students screens.
- Run: `php artisan serve --port=8000`, then `npm run build` (or `npm run dev`). New Super Admin: `php artisan platform:create-user "Name" email`.
- Quality gates: `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pest --compact`, `npm run types:check`, `npx vp check`, `npm run build`. After route changes: `php artisan wayfinder:generate --with-form`.

## Session-wise students (2026-10-22)

Built: session switcher + `students.view_past_sessions` permission, read-only past-session list, profile and export. To verify: past sessions with real enrolment history. Pending: parent view, rollover/promotion, per-session attendance/fees/marks.
