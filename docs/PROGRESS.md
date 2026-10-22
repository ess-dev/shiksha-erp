# Shiksha ERP — progress and next steps

Read this first when starting a new session (Claude Code or Cursor), then the rules in `.ai/guidelines/`.
Update this file in the same commit as the work it describes. Last updated: 2026-10-22, after commit `c2b7e01`.

## Where things stand

Branch `dev`, 9 commits, no remote yet. `php artisan test`/`vendor/bin/pest`: **171 tests pass** (about 4.5 minutes). PHPStan (level from `phpstan.neon`) clean.
Quality gates used so far: `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pest --compact`, `npm run types:check`, `npx vp check`, `npm run build`.

### Done (in order)

| # | What | Commit |
|---|---|---|
| 1 | Starter kit baseline + `.ai` rules; Lefthook hooks | `7845c49` |
| 2 | Landlord/tenant split, host-based school resolution, `tenants:provision`, demo schools | `929aad9` |
| 3 | `school` and `module:<key>` middleware, tenant status, subdomain rules | `fda5955` |
| 4 | Pest 4 + architecture tests (`tests/Architecture/ConventionsTest.php`) | `0fa7380` |
| 5 | Append-only audit trail (tenant `audit_events`, landlord `platform_audit_events`), base `Action` | `cdda19b` |
| 6 | Identities, memberships, staff sign-in (phone/email + password), membership re-check per request | `c7c6e1c` |
| 7 | Permission catalog, versioned role templates, roles, scoped assignments, Gate integration, two-step enforcement | `cc87492` |
| 8 | School structure: campuses, academic years, classes, sections, subjects; role-template update flow | `c2b7e01` |

### Code map

- `app/Tenancy/` — `Tenant` (landlord model), `HostTenantFinder`, `SwitchTenantDatabase`, `ProvisionTenant`, `TenantStatus`, `SubdomainRules`.
- `app/Domain/Shared/` — base `Action` (transaction + must write an audit event), `PhoneNumber` (E.164), `CalendarDate` (date strings, never via UTC).
- `app/Domain/Audit/` — `AuditRecorder`, `AuditEntry`, `AuditActor`, `SessionAuditActor`, models `AuditEvent` / `PlatformAuditEvent`.
- `app/Domain/Identity/` — `Identity`, `Membership`, `StaffAuthenticator`, Actions (`CreateIdentity`, `GrantMembership`, `RevokeMembership`, `ChangeIdentityPassword`, `UpdateIdentityContact`).
- `app/Domain/Access/` — `Authorizer` (answers permission keys through the Gate), `GrantGuard`, `Scope`/`ScopeSet`/`ScopeTarget`, `RoleBuilder`, `TwoStepRequirement`, Actions (`CreateRole`, `UpdateRolePermissions`, `AssignRole`, `RemoveRoleAssignment`, `CreateStaffRecord`, `SetUpSchoolOwner`, `ApplyRoleTemplateUpdate`), Queries (`PermissionScopesQuery`, `RoleTemplateUpdatesQuery`, `RoleNamesForIdentityQuery`).
- `app/Domain/School/` — models, `StructureGuard`, Actions (`CreateCampus`, `UpdateCampus`, `CreateAcademicYear`, `SetCurrentAcademicYear`, `CreateSchoolClass`, `CreateSection`, `CreateSubject`, `SetClassSubjects`), Queries (`CampusesQuery`, `SectionsQuery`, `SchoolDefinitionsQuery`), `StructureData`.
- Middleware (`bootstrap/app.php`): `EnsureActiveMembership`, `EnsureTwoStepWhenRequired` run on every web request; aliases `school`, `module`. All school routes in `routes/web.php` and Fortify routes sit behind `school`.
- Migrations: `database/migrations/landlord/` (tenants, tenant_modules, platform audit, identities/memberships, permission catalog and templates) and `database/migrations/tenant/` (audit_events, staff/roles/assignments, school structure). `php artisan migrate` runs landlord only; tenants are migrated by `ProvisionTenant::migrate()` (the demo seeder re-runs it for existing schools).
- Seeders: `AccessCatalogSeeder` (platform data), `DemoSchoolsSeeder`, `DemoStaffSeeder`, `DemoStructureSeeder`; `php artisan db:seed` runs all of them and is safe to repeat.

### Local environment

- Windows + WAMP, PostgreSQL 18.6 at `D:\programfiles\postgres18`. Role `shiksha` (CREATEDB) owns `shiksha_landlord` and `shiksha_landlord_test`; its password is in the git-ignored `.env`. Do not put passwords in the repo or in chat.
- Demo schools: `greenfield.shiksha.localhost`, `riverside.shiksha.localhost`. Demo sign-ins (fictional, local only): `principal@greenfield.test`, `principal@riverside.test`, password `password`. Both are owners, so they must set up two-step before using the staff area.
- Tests use the real tenant database `shiksha_t_testshared` (created on first run, emptied around every test) plus `DatabaseMigrations` tests that create and drop their own tenant databases. Never run two Pest processes at once: they share the test databases.
- pgvector is deliberately **not** installed; wait until the AI feature is scoped.
- The shell tooling here mangles single backslashes in some command strings; write PHP files with the editor tools, not shell heredocs, when namespaces are involved.

## Decisions and conventions worth remembering

- Identities hold sign-in data only (no names). Names live in the tenant `staff` table, linked by `identity_ulid`.
- Membership/identity changes are audited in the **platform** audit table (they write landlord data); school-side Actions audit into the school's `audit_events`.
- Data objects are plain `JsonSerializable` classes (`spatie/laravel-data` is not installed — adding it needs approval). Never send models or internal ids to the browser.
- Scope containment is conservative: a campus scope cannot be shown to contain specific sections (no section-to-campus lookup in `Scope::contains`), so only a school-wide scope may hand out section scopes.
- Template changes need a new version in `AccessCatalogSeeder` (never edit an existing version); schools then see "update available".
- Architecture rule allows only these cross-module uses: other modules' `Actions`, `Queries`, `Data`; `Audit`/`Access` `Enums`; the `Identity` model as the acting principal.

## Open decisions (do not decide in code)

Product domain details, staging host (P11), mobile API style (P4.3), payment gateway, SMS/email/WhatsApp providers, MVP build order, legal retention periods, board-specific grading, tax treatment, money-rounding sign-off, platform (Super Admin) host name. Anything that depends on one goes behind an interface or config value.

## Known gaps and debts

- No role-management or school-structure **screens** yet; all of the above is backend + tests. The shared `auth.abilities` prop exists but no page uses it.
- Class-teacher / subject-teacher auto-assignment (needs allocation + timetable) not built; `role_assignments.source = 'auto'` is reserved.
- Owner transfer through the platform, support access, and platform audit viewer not built.
- `platform_users` (Super Admin) and platform host: not built. Parent OTP sign-in, `pending_guardian_links`, Sanctum tokens: not built. SMS goes behind a driver interface with a log/fake driver when built.
- Two-step is enforced for owners and holders of critical permissions only; there is no UI beyond the kit's security settings page.
- Translations: error pages get text from `lang/en/school.php`; the Laravel-to-React translation sharing (decision D4) is not built. Most kit pages still have hardcoded English strings.
- Per-school database roles with encrypted credentials (decision D3) — columns exist, local setup uses the shared `shiksha` role.
- S3 `tenant` disk (D11) not set up. Retention jobs not built.
- Pest browser tests not set up (needs Playwright). Existing kit tests are still class-style PHPUnit under Pest.
- No `docs/modules/<module>.md` files yet (the workflow rules ask for one per module).
- Test suite is slow (about 4.5 min); speed up the per-test landlord rebuild before it grows.
- Naming leftovers: `database/migrations/landlord/0001_01_01_000000_create_users_table.php` now only creates password-reset and session tables; kit file names kept so existing databases do not re-run it.
- Local `.env`/Apache vhost for `*.shiksha.localhost` not verified in a browser; behaviour is covered by tests only.

## Next steps (suggested order)

Each step: read the relevant rules, plan briefly, build with Actions/Queries/Policies, write tests (happy path, authorization matrix, tenant isolation, edge cases), run the gates, update this file, commit.

1. **First screens (Inertia + React)** — school structure (campuses, years, classes, sections, subjects) and roles/assignments, using the existing Queries and Actions. Needs: controllers in `app/Http/Controllers` (or `app/Http/Web/<Module>` per the layout rule), Form Requests, Policies/Gates, Wayfinder, shared `data-table`, `lib/format.ts`, abilities-driven UI, translation keys, mobile-first. Also add the "template update available" prompt.
2. **Students and guardians** — students, guardians, enrolment per section/year, guardian–student link with portal-access flag, shared-family-phone warning, masked sensitive fields, `students.*` permissions already in the catalog. Needs `spatie/laravel-data` decision first.
3. **Parent sign-in** — phone + OTP (hashed, single-use, rate-limited per phone/IP/school), SMS driver interface with log/fake driver, guardian membership + context switcher, `pending_guardian_links`.
4. **Admissions** → **Attendance** → **Fees (Money value object, paise, idempotent payments)** → **Exams** → **Communication** → **Homework** — order is an open decision; confirm with the owner.
5. **Platform (Super Admin)** — `platform_users`, platform host, two-step always, tenant management screens, owner transfer, support access, platform audit viewer.
6. **Housekeeping** — class-teacher/subject-teacher auto roles, per-school DB roles (D3), D4 translations, D11 storage disk, faster tests, module docs, Pest browser tests, CI pipeline on Linux, remote + protected branches.

## How to start a new session

1. Read `CLAUDE.md` / `AGENTS.md` (generated from `.ai/guidelines/`) and this file.
2. `git status` and `git log --oneline -10` to see what changed since "Last updated" above.
3. Make sure PostgreSQL is running and `.env` has the local `shiksha` password; `php artisan migrate && php artisan db:seed` brings a local database up to date.
4. Pick the next step above (or the one the owner names), write a short plan, and begin.
