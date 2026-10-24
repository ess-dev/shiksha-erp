# Exams module

Purpose: schedule unit tests and exams, enter marks, grade them and show results to parents (built 2026-10-24 from the owner's prototype screenshots and `staff-exams.js`, `teacher-marks.js`, `parent-report-card.js`, `reportcard.js`, `coordinator-marks.js`). Two slices: the scheduling screen, then marks entry, the grading scale and the parent Results page (below).

## Screen

`/exams` (sidebar "Exams and tests", needs `exams.schedule`, module `exams` in the plan): four counters (Scheduled, Marking open, Results published, Cancelled), tabs "Upcoming and open", "Published", "Cancelled", and a table with Edit, Open marking, Publish all, Close marking, Cancel, Restore, Delete (trash) and Date sheet per row. "New test or exam" and Edit open one dialog: name, type, maximum marks, start and end, classes, campuses (only when the person reaches more than one), date sheet (add rows, or "One subject a day" fills the examined subjects on school days from the start date), instructions (300 characters), "Show on the school calendar" and "Tell parents". Date sheet opens a read-only dialog with Download CSV (built in the browser from the data already shown).

## Lifecycle

`scheduled` ⇄ `marking` → `published` (final); `scheduled` / `marking` → `cancelled` → `scheduled` (restore). Publish all shows every subject's marks to parents (also subjects whose teacher never published), makes them final, and freezes the grading scale on the exam. Delete is allowed for anything that was never published and has no marks (`Exam::hasMarks()`); marks also lock maximum marks.

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

## Marks entry `/marks` (second slice)

Sidebar "Marks entry" for `marks.enter` or `marks.reopen`. Pickers Exam (marking open or published, current year, for a section the person reaches), Class (sections the exam is for), Subject ("· yours" / "· view only"); a strip (draft / view only / published / marking closed); one row per student on the section's roster today with a marks box, live % and grade; Class summary (entered, average, below the pass mark, highest/lowest, grade spread); Grading scale card (Change link for `settings.manage`); Save draft and Publish to parents (confirm dialog); Reopen marks (reason dialog) for overseers. Enter and the arrow keys move between boxes; leaving with unsaved marks asks first.

- **Who**: a subject's marks in a section are entered by its chosen subject teacher, or by the class teacher when none is chosen ("Automatic"), holding `marks.enter` over the section. Anyone holding the new `marks.reopen` over the section (Principal, Vice principal, Coordinator, by scope; owner's answer 2026-10-24) may enter or correct any subject there, publish it, and reopen published marks. The class teacher, overseers and holders of `exams.view` over the section see its other subjects view only. Co-scholastic subjects have no marks. Out-of-reach sections, subjects and students are 404; a view-only subject is 403. The rules live in `MarksAccess`.
- **Values**: blank, `AB`, or 0 to the maximum in halves; stored as whole half-marks (`half_marks`, 12.5 is 25) so no fractional type is needed. Drafts may be partial; publishing needs a value for every student on the roster.
- **Per (exam, section, subject)** (`exam_mark_sheets`): draft, published (teacher or overseer), reopened by an overseer with a reason while marking is open, published again. Every save audits each changed student's old and new value (`marks.saved`); also `marks.published`, `marks.reopened`. Publish all on the Exams screen makes everything final (no reopening after that).
- **Parents are told** through `MarksNotifier` (`LogMarksNotifier` bound: logs ulids only; provider still open).
- **Exams list** now shows "x/y entered · z published" per exam (`ExamMarksProgressQuery`: active sections the exam is for times examined subjects; "entered" means marks for as many students as are enrolled today).

## Grading (second slice)

`GradeScale` (value object): grades from the highest down, each with the whole percentage it starts at and a grade point; the last starts at 0% and is the failing grade; the pass mark is where the next grade starts. A score gets the highest grade whose minimum it reaches, compared as exact fractions with integers (80% is B1 on the default scale; 90.5% stays A2). Percentages are integers rounded half-up (`Percent::whole`, `Percent::tenths`); no floats. Default: the prototype's CBSE-style A1 91 ... E 0 with points 10 ... 0, **illustrative - verify** against current board guidance.

- **School setting** `exams.grade_scale` in `school_settings` (read by `SchoolSettingsQuery::gradeScale()`, applied by `GradeScaleQuery`). Screen `/exams/grading` ("Grading scale" button on the Exams page, "Change" on the marks screen), needs `settings.manage`: edit rows, add or remove (2 to 12 grades), "Start from the default"; audit `settings.grade_scale_updated`.
- **Snapshot**: an exam's scale is frozen in `exams.grade_scale` when its first marks are published (a teacher's publish or Publish all); later changes to the school's scale apply only to exams with nothing published yet.

## Parent Results `/parent/results` (second slice)

Menu "Results" (and a bottom tab) when the exams module is in the plan; header title "Report card" as in the prototype. Exam chips (exams of the viewed session for the child's class and campus with marking open or published), the exam card (Final or "n awaiting", child, overall % and grade, Subject / Marks / % / Grade, "Awaiting results", AB, Total of published marks), "Progress across exams" bars (plain HTML, no chart library), "How grades work" chips from the school's scale, and **"Report cards from school" as a Coming soon card** (owner: the class teacher's upload and approval flow comes later). "Open as report card" (`/parent/results/{exam}/report-card`, print layout) generates a printable report card from published marks: school, campus address, board, student, class, admission number, date of birth, attendance up to the exam's last day (when Attendance is in the plan), class teacher, subject rows with grade points, overall, a blank remarks line and signature lines. Another family's child, or an exam with nothing published for the child, is 404.

- Totals count subjects with published marks only (absent and awaiting subjects are left out, as in the prototype); overall % in tenths, half-up; overall grade from the exam's scale. In a past session only subjects with marks are listed.

## Code (second slice)

Tenant migration `2026_11_08_000000_create_exam_marks_tables` (`exam_marks`, `exam_mark_sheets`, `exams.grade_scale`). Models `ExamMark`, `ExamMarkSheet`; `GradeScale`, `GradeBand`, `MarkValue`, `Percent`, `MarksAccess`; Actions `SaveMarks`, `ReopenMarkSheet`, `UpdateGradeScale` (and `ChangeExamStatus` freezes the scale); Queries `MarksScreenQuery`, `GradeScaleQuery`, `GradeScaleScreenQuery`, `StudentResultsQuery`, `ExamMarksProgressQuery`; Students `ParentResultsQuery` (results, admission number, report card). Exceptions `MarksException` (form key `marks`), `GradeScaleException` (`grades`). Controllers `Exams\MarksController`, `Exams\GradeScaleController`, `Guardian\ResultsController`; pages `exams/marks.tsx`, `exams/grading.tsx`, `parent/results.tsx`, `parent/report-card.tsx`; components in `components/exams/` (`grading.ts` mirrors the server's parsing and grading for live display only).

## Not built

The class teacher's report-card screen (generate or upload a scan, coordinator approval) and the parent "Report cards from school" list; class teacher's remarks and the promotion result on the report card; the coordinator's marks-progress page with nudges; a staff view of a student's report card and the Students profile Marks tab; marks of students who left the section after marks were entered (the sheet shows today's roster only); co-scholastic grading; term or annual aggregation across exams (board-specific, open); the school calendar entry behind "Show on the school calendar" (the choice is stored); real parent notifications (log drivers); exams of earlier academic years on the staff screens; a teacher view of upcoming exams; a seeder with demo exams.
