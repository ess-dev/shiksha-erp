# Exams module

Purpose: schedule unit tests and exams (built 2026-10-24 from the owner's prototype screenshots and `assets/js/pages/staff-exams.js`). This slice is the **staff scheduling screen only**. Marks entry, per-subject publishing, report cards and the parent's Results view are not built.

## Screen

`/exams` (sidebar "Exams and tests", needs `exams.schedule`, module `exams` in the plan): four counters (Scheduled, Marking open, Results published, Cancelled), tabs "Upcoming and open", "Published", "Cancelled", and a table with Edit, Open marking, Publish all, Close marking, Cancel, Restore, Delete (trash) and Date sheet per row. "New test or exam" and Edit open one dialog: name, type, maximum marks, start and end, classes, campuses (only when the person reaches more than one), date sheet (add rows, or "One subject a day" fills the examined subjects on school days from the start date), instructions (300 characters), "Show on the school calendar" and "Tell parents". Date sheet opens a read-only dialog with Download CSV (built in the browser from the data already shown).

## Lifecycle

`scheduled` ⇄ `marking` → `published` (final); `scheduled` / `marking` → `cancelled` → `scheduled` (restore). Publishing here only changes the status and tells parents; marks do not exist yet. Delete is allowed for anything that was never published and has no marks (`Exam::hasMarks()` is a stub that returns false until marks entry exists; change it there).

## Who may do what

- `exams.schedule` (new, sensitive, module `exams`; role-template **version 8**: Principal, Vice principal, Coordinator) is scoped. An exam applies to every (class, campus) pair it names; a person **sees** it when it touches a section of the current year inside their scope and **changes** it only when their scope covers every pair (a wing coordinator changes exams inside their wing; wider ones show "set outside your reach" with no buttons). Out-of-reach exams are 404, visible-but-wider ones 403. Only school-wide, campus and wing scopes can schedule (a sections-only scope covers no pair).
- **Publish all** also needs `results.publish` (already in the catalog) over the same pairs.
- "All classes" / "All campuses" are stored as flags (new classes and campuses are included automatically) and offered only to people whose scope covers everything.
- Existing schools see "template update available" and must apply update 8 to each role.

## Rules (checked in `ExamRules`, one place for create and update)

Name 3 to 60 characters, unique among live exams of the year (case-insensitive; a cancelled exam's name can be reused, and restoring fails when a live one has it; a partial unique index backs this). Maximum marks 1 to 200. New exams cannot start in the past; the start must be a school day (weekly days off and holidays from attendance settings); end defaults to start and is not before it. At least one class and campus, and a section of the current year must match. Date sheet: each subject once, an active examined subject (not co-scholastic) taught in a chosen class, dated inside the exam dates on a school day, optional time. Exams belong to the academic year current when created; the screen lists the current year's exams.

## Tables (tenant, `2026_11_06_000000_create_exams_tables`)

`exams` (ulid, year, name, type, `max_marks`, `starts_on`, `ends_on`, status, instructions, all-classes / all-campuses flags, `show_on_calendar`, creator staff, `published_at`; CHECKs on type, status, marks and dates), `exam_school_class`, `campus_exam` (only when not "all"), `exam_date_sheet_entries` (unique per exam and subject; `held_on` date, `begins_at` time).

## Code

`app/Domain/Exams/`: Models `Exam`, `ExamDateSheetEntry`; Enums `ExamType` (default maximum marks per type are **illustrative — verify**), `ExamStatus`; `ExamReach` (the one place that decides who sees and changes what), `ExamRules`, `ExamDetails`, trait `StoresExamParts`; Actions `CreateExam`, `UpdateExam`, `ChangeExamStatus`, `DeleteExam` (audit keys `exams.exam_created/updated/deleted`, `exams.status_changed`); Query `ExamsOverviewQuery`; Data `ExamsOverviewData` and friends; `ExamException` (form-error key `exam`); contract `ExamNotifier` with `LogExamNotifier` bound (logs the exam ulid and event only). Controllers `app/Http/Controllers/Exams/`, requests `app/Http/Requests/Exams/`, page `resources/js/pages/exams/index.tsx`, components `resources/js/components/exams/`.

## Not built

Marks entry and progress ("106/123 entered · 119 published"), per-subject publishing, report cards, the parent Results and exam views, the school calendar entry behind "Show on the school calendar" (the choice is stored), real parent notifications (log driver), exams of earlier academic years on the screen, a teacher view of upcoming exams, a seeder with demo exams.
