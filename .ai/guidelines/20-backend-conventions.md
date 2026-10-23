# Backend conventions (Laravel)

## Folder layout
```
app/Domain/<Module>/{Models,Actions,Queries,Data,Enums,Events,Jobs,Policies,Exceptions}
app/Domain/Shared/        Money, dates, ULID helpers, base classes
app/Http/Web/<Module>/    Inertia controllers + Form Requests
app/Http/Api/V1/<Module>/ JSON controllers (mobile; not before P4.3 is decided)
app/Tenancy/              tenant model, finder, switch tasks, provisioning
routes/web.php (school) · routes/platform.php (Super Admin) · routes/api_v1.php
```
Create a module folder only when building that module. Modules: Platform, Identity, Access, School, Students, Admissions, Attendance, Leave, Fees, Exams, Communication, Homework, Audit, Shared.

## Where code goes
- **Controllers**: authorize → validate (Form Request) → call one Action or Query → return an Inertia page or API response. No business logic, no `DB::` calls, no Eloquent queries beyond route-model binding.
- **Actions** (verb + noun, e.g. `RecordPayment`): the **only** place that writes data. Each runs in a DB transaction and writes exactly one audit event (more only when it performs several distinct business actions). Reused by web, API, jobs, imports and commands.
- **Queries** (`…Query`): reads with filtering, scoping and pagination. Apply the caller's permission scope (campus/wing/sections/own children) inside the query, not in the controller.
- **Policies**: permission + scope + object-level checks. Every controller action is authorized.
- **Data objects** (`…Data`, spatie/laravel-data): the only shape sent to Inertia or the API. Never pass an Eloquent model or collection to the frontend.
- Domain exceptions are typed and translated into user-facing messages at the HTTP layer.

## IDs
- Every table: `bigint` `id` primary key, used only internally.
- Tables whose records appear in URLs or the API also have a unique `ulid` column; route binding uses `ulid`.
- **`id` never leaves the server.** Data objects expose `ulid` only.
- `bigint` IDs repeat across school databases; anything copied to the landlord DB carries `tenant_id`.

## Money
- Integer **paise** in `bigint` columns ending `_paise`. Never floats, never `decimal` for amounts, in PHP or TypeScript.
- All arithmetic through the `Money` value object in `Domain/Shared` (half-up to the paisa for percentages; whole-rupee rounding only where a school setting asks). Rounding sign-off by an accountant is pending — don't add new rounding rules.
- Financial writes are idempotent (idempotency key on payments and webhooks) and run in transactions with row locks where balances change.

## Dates and time
- Instants: `timestamptz`, app timezone UTC, displayed in the school's timezone setting (default `Asia/Kolkata`).
- Calendar dates (attendance day, due date, date of birth, holidays, leave days, academic-year bounds) use the `date` type and are **never** converted through UTC.
- "Today" for business rules comes from the shared school-date helper, never `now()->toDateString()` or `new Date()`.

## Naming
- Tables plural snake_case (`fee_invoices`); pivots alphabetical singular (`guardian_student`).
- Columns snake_case; FKs `<entity>_id`; booleans `is_`/`has_`; instants `_at`; dates `_on` (`due_on`, `born_on`); money `_paise`.
- Enums: PHP backed enums, stored as text with a CHECK constraint.
- Permission keys `area.action` (`fees.refund`).
- Events past tense (`PaymentRecorded`). Jobs describe work (`ProvisionTenantDatabase`).
- URLs kebab-case; route names dot.case (`fees.structures.index`).

## Deleting records (P7.4 = B, locked)
- **Lifecycle uses status fields, not deletion**: student `enrolled/left/transferred/alumni`, staff `active/inactive`, admission `withdrawn`, etc.
- **Financial records are never deleted or rewritten**: invoices, payments, receipts, refunds, concessions applied, payroll lines. Corrections are void or reversal entries that reference the original.
- **Audit records are never updated or deleted** (the DB role has no UPDATE/DELETE on audit tables).
- Marks, attendance and grades are corrected with a new value + reason + audit entry, never silently overwritten history; published results keep a snapshot of the grading rules used.
- **Soft deletes only on an approved short list** of user-generated content (e.g. notices, posts, homework, events, albums). Adding `SoftDeletes` to any other table needs explicit approval in the task. Unique constraints on soft-deleting tables use partial indexes (`WHERE deleted_at IS NULL`).
- Hard deletion of personal data happens only through retention/offboarding jobs (periods pending legal review), never ad hoc.

## Database
- Foreign keys with explicit `ON DELETE` behaviour (usually `restrict`); indexes for every FK and common filters; CHECK constraints for enums and non-negative amounts.
- Use PostgreSQL features where they fit (JSONB for configurable settings/snapshots, partial and expression indexes) — but keep queries in Eloquent/Query classes, not raw SQL strings, unless justified.
- Avoid N+1: eager-load in Queries; Pest tests may assert query counts on list pages.

## Configuration, not constants
School-configurable rules (grading scales, fee heads, leave quotas, attendance thresholds, approval limits, academic-year dates) are stored as tenant settings with validated defaults. Platform-owned definitions (permission catalog, role templates, plans, modules) live in the landlord DB and are seeded, not hardcoded in controllers.
