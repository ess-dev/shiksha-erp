# Security, quality and testing

## Authentication
- Parents: phone + OTP. OTPs are short-lived, single-use, stored hashed, rate-limited per phone, per IP and per school, with lockout after repeated failures. SMS goes through a driver interface; use the log/fake driver until a provider is chosen.
- Staff: phone or email + password (Laravel's default hasher).
- **Two-step is mandatory (locked)** for: Super Admin (`platform_users`), the school owner/principal, and anyone holding a role with any **critical** permission (e.g. refunds, payroll processing, roles and access). Such users can't reach the staff area until two-step is confirmed; if a critical permission is assigned to someone without two-step, they are sent to two-step setup at next sign-in. Two-step is optional for everyone else.
- Password reset, OTP and sign-in never reveal whether a phone, email or membership exists.
- Sessions: host-only cookies, `secure` and `httpOnly` outside local, regenerate on sign-in, invalidate on sign-out and on membership revocation.

## Data protection
- Collect and expose the minimum personal data about children. Data objects include only fields the screen needs.
- Sensitive fields (guardian phone, documents, medical notes, bank details, PAN/UAN) are masked unless the viewer has the specific permission.
- Files: stored under the tenant prefix, never in `public/`; served through short-lived signed URLs after a permission check; validate type (by content, not extension) and size on the server; client-side checks are convenience only.
- Exports and downloads are permission-checked and audited.
- Never log OTPs, passwords, tokens, full phone numbers or document contents. Secrets live in `.env`/server secrets, never in code or seeders.
- Retention periods and children's-data rules are pending legal review: build them as configurable jobs; don't hardcode periods or claim compliance.

## Audit
- Every Action that changes data writes an `audit_events` row in the same transaction: actor (identity + membership/role), action key, subject type + ULID, before/after summary, IP, user agent, time.
- Platform actions (Super Admin, support access to a school, provisioning) go to the landlord audit table.
- An Action without an audit write fails the architecture test.

## Quality gates (must pass before a PR)
- `vendor/bin/pint` · `vendor/bin/phpstan analyse` (Larastan, level 6 now, raising to 8) · `php artisan test` (Pest) · TypeScript check (`npx tsc --noEmit` or the kit's `types` script) · ESLint (the kit's `lint` script) · `npm run build` · generated TypeScript types up to date. Use the script names actually defined in `package.json`/`composer.json`.
- Lefthook runs Pint, ESLint/Prettier and the type-check on staged files at commit; CI on Linux repeats everything with PostgreSQL 18 and Redis.

## Pest architecture tests (keep these green; add to them, never weaken)
- Controllers don't use `DB::` or call Eloquent write methods.
- No `dd`, `dump`, `ray`, `var_dump`, `env()` outside `config/`.
- Every class in `Domain/*/Actions` writes an audit event (via the base Action).
- Models are never returned to Inertia or the API; Data objects never expose `id`.
- Domain modules don't reach into another module's internals except through its Actions/Queries/Data.
- Money columns/properties use the `Money` value object (no `float` in `Domain/*`).

## Tests required for every feature
- Happy path through the Action/Query.
- **Authorization matrix**: allowed in scope; denied out of scope; denied without permission; denied when the module is not in the plan; blocked when the school is suspended/expired.
- **Tenant isolation**: data created in school A is invisible from school B (web, jobs, commands where relevant).
- Validation failures and edge cases from the task (partial payments, retries, duplicate submissions, academic-year boundaries, leap days, midnight IST).
- Financial calculations and grade calculations: deterministic, table-driven tests with exact paise/grade expectations.
- Critical flows also get a Pest browser test.
- Factories create realistic, fictional Indian data; never real people's data.

## Definition of done
Code + migrations + Policies + tests written and **run**; quality gates pass; audit events written; translation keys added; the task summary lists commands run and their results, assumptions, and anything not verified.
