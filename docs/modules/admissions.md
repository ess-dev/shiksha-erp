# Admissions module

Purpose: the admissions pipeline for a school (built 2026-10-23 from the prototype's `admin/admissions.html`). An application goes Enquiry, Application, Documents verified, Decision, Enrolled; enrolling creates the student through the Students module.

## Screens

- `/admissions`: board with 4 totals, 5 stage columns, drag and drop (HTML5, no library) plus arrow buttons. Dialogs: Add enquiry, application details (details form, documents, decision, notes, history), Enroll, Enrolled, "Documents still missing". `?open=<ulid>` opens one application; `?closed=1` shows refused ones.
- `/admissions/settings`: required documents by class (not in the prototype). Read-only unless `settings.manage`.
- Students list: the "New admission" button now links to the board (needs `admissions.manage`).

## Tables (tenant, `2026_11_01_000000_create_admissions_tables`)

`admission_applications` (ulid, child and parent details, class, campus, academic year, stage, decision, `is_closed`, `student_id`), `admission_application_events` (append-only History), `admission_documents` (one row per kind ticked or uploaded; files on the `tenant` disk under `tenants/<school>/admissions/<application>/`). Applications are never deleted; "Not admitted" closes them.

## Actions (all audited)

`AddEnquiry`, `MoveApplication`, `UpdateApplicationDetails`, `SaveApplicationNotes`, `SetDocumentReceived`, `UploadAdmissionDocument`, `OpenAdmissionDocument`, `RecordDecision`, `EnrollApplicant` (calls Students `EnrollFromAdmission`, which calls `CreateStudent` and copies the scans onto the student's record as verified documents, source `admission`). School: `UpdateAdmissionDocumentRules`. Queries: `AdmissionBoardQuery`, `AdmissionDetailQuery`, `AdmissionOptionsQuery`, `DocumentSettingsQuery`.

## Permissions

`admissions.view` (board), `admissions.manage` (add, move, edit, tick, decide), new `admissions.enroll` (sensitive; enrol), plus existing `students.documents` (upload scans), `students.view_sensitive` (full phone, open scans) and `settings.manage` (required-documents rules). Role-template version 5: Principal and Vice principal all; Coordinator and Admission officer view + manage + enroll; Front office adds manage (no enroll). Schools see "template update available". Scope is the person's campus or wing (class level range); a scope of specific sections reaches no applications (an application has no section yet). Out-of-scope applications are 404.

## Rules decided with the owner (2026-10-23)

- Date of birth, blood group and address must be filled in before "Documents verified" (the Enroll dialog asks for nothing but the section).
- Required documents are a school setting per document kind: every class or chosen classes. Until a school saves its own, the defaults apply: birth certificate, Aadhaar, photo for all classes, transfer certificate for every class except the lowest (illustrative, verify).
- Fees are not created yet (Fees module not built); the Enroll dialog says so.
- Moving to "Documents verified" with required documents unticked asks first ("Move anyway"), the server needs `accept_missing_documents`. Moving back from Decision clears the decision. Waitlisted can be decided again; "Not admitted" closes the application (no reopen). Enrolling happens only from Decision with "Accepted", through the Enroll dialog (dragging to Enrolled opens it).
- Child must be at least 2 years old (illustrative, verify). The campus must run a section of the class this year.
- Enrolment goes through `CreateStudent`, so the one-phone-one-guardian rule applies (a conflict is shown in the Enroll dialog and nothing is saved).

## Not built / limits

Fees on enrolment, applying for a future academic year (applications belong to the year current when they were added), reopening a closed application, parent-facing application status, enquiry sources as a school setting, Excel/CSV export of the board, and a Pest browser test.
