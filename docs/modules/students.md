# Students module

Purpose: the school's students and their guardians, from enrolment to leaving, as the Principal and Coordinator manage them. Slice 1 (2026-10-22). New admission, attendance, fees and marks plug in later.

## Entities (tenant database)

- `students`: admission number, names, gender (male/female/other), date of birth, blood group (incl. unknown), house (nullable = Not assigned), address, status (`enrolled` / `left` / `transferred` / `alumni`), admitted/left dates, leave reason, TC number.
- `guardians`: name, phone (E.164, **unique**), email, occupation, `identity_ulid` (set when the guardian accepts the parent-app invite).
- `guardian_student`: relation, primary contact, portal access.
- `enrollments`: one row per stay in a section; a section change closes the current row (history kept); partial unique indexes keep one current row per student and one roll number per section.
- `student_sibling_links`: staff-made links between students whose guardians differ.
- `student_documents`: scans on the `tenant` disk; replaced documents are marked superseded, never deleted.
- School module: `houses`, `school_settings` (admission and TC prefixes), `number_sequences` (row-locked counters).

## Actions (each writes one audit event)

`CreateStudent`, `UpdateStudentDetails`, `ChangeSection`, `SetRollNumber`, `IssueTransferCertificate`, `LinkSiblings`, `UnlinkSiblings`, `UploadStudentDocument`, `VerifyStudentDocument`, `OpenStudentDocument`, `ImportStudents`, `ExportStudents`; School: `CreateHouse`, `UpdateHouse`, `ReorderHouses`, `UpdateSchoolSettings`.

## Permissions

`students.view`, `students.manage`, `students.view_sensitive` (full guardian phones, documents), `students.issue_tc` (critical), `students.import`, `students.export`, `students.documents`, `students.link_siblings`, `houses.manage`; `settings.manage` for the number prefixes. All module `core`. Defaults come from role-template version 3 (version 4 adds `students.view_past_sessions`) (Principal: all; Coordinator: all but `students.issue_tc`; Admission officer: manage, view_sensitive, import, documents). Schools can change them from the roles screen.

## Rules worth knowing

- One phone is one guardian per school: a matching phone and name links the child to the existing guardian, a different name is refused.
- Siblings share a guardian, or are linked by staff (only when every guardian of both has portal access). Viewers see names only for children inside their scope.
- Out-of-scope students return 404. Phones are masked without `students.view_sensitive`.
- Change section: same class, not full, and inside the person's scope for both sections.

## Settings and jobs

Prefixes for admission and TC numbers (`/students/settings`). No jobs yet; document retention is pending legal review.

## Known limitations

See "Students gaps" in `docs/PROGRESS.md`: New admission, fee billing on import, attendance/fees/marks tabs, transport, parent-app status, parent access to documents.

## Academic sessions (2026-10-22)

- Student identity, guardians and documents are single records; class, section and roll are per session through `enrollments.academic_year_id`. A top-bar session switcher (`sessionSwitcher` Inertia prop, `PUT school/session`, browser-session key `school.session`) picks the session the Students list, profile and CSV export show.
- Permission `students.view_past_sessions` (core, sensitive, role-template version 4: Principal, Vice principal, Coordinator). Without it the switcher is not shown, a forged choice is rejected, and a stale value in the browser session is ignored (current session shown).
- Past sessions are read only: the list shows everyone enrolled that year in the section they had then (status filter ignored); the profile shows that year's class and roll with edit, TC, documents and sibling actions off; a student not enrolled that year is a 404. Scope checks use the section of that session.
- Not built: parent view of past sessions (no parent screens yet; the same `StudentSession` resolver should be used, with years limited to those the child was enrolled in), promotion/rollover to the next session, and per-session attendance, fees and marks (to be year-tied when those modules are built).

- New admission (2026-10-23): the Students list's "New admission" button links to the Admissions board. Enrolment from an accepted application goes through `EnrollFromAdmission` (Actions) with permission `admissions.enroll` over the section; see `docs/modules/admissions.md`.
