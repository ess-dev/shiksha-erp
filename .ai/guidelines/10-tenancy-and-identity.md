# Tenancy and identity

## Two kinds of database
- **Landlord (central)**: tenants (schools), domains, plans, modules/entitlements, subscriptions, platform billing, `platform_users`, `identities`, `memberships`, `pending_guardian_links`, Sanctum tokens, permission catalog, role templates, provisioning/migration job tracking, central audit, aggregated reporting copies.
- **Tenant (one per school organization, all its campuses)**: everything about that school — campuses, academic years, classes, sections, subjects, students, guardians, enrolments, staff, attendance, leave, fees, exams, homework, communication, school roles and assignments, `audit_events`.
- Migrations live in `database/migrations/landlord/` and `database/migrations/tenant/`. Decide which one **before** writing a migration and say which in your summary. Never put school data in the landlord database.

## Resolving the school
- The school is resolved **only from the request host**: verified custom domain first, then `<subdomain>.<APP_BASE_DOMAIN>`. Unknown host → neutral "school not found" page.
- Local: `APP_BASE_DOMAIN=shiksha.localhost` → `greenfield.shiksha.localhost`. Never hardcode a domain; read it from config.
- **Never accept a school/tenant ID from the client** (request body, query string, header, cookie) as proof of access. Mobile API requests resolve the school from the Sanctum token's membership.
- The tenant database connection is switched once per request, queued job and scheduled command **before any query**. Use Spatie's current-tenant mechanism; don't open tenant connections manually.

## Rules that prevent data leaks
- Never query across tenant databases in one operation. Platform-wide figures come from data copied into the landlord database (always with `tenant_id`).
- Queued jobs touching school data must be tenant-aware (carry and restore the tenant). A job that loops over schools switches tenant explicitly per school and catches failures per school.
- Artisan commands that touch school data run per tenant (Spatie's tenant-aware command pattern) and report success/failure per school.
- Cache keys and file paths are tenant-prefixed by the switch tasks; never build a cache key or storage path that omits the tenant.
- Session cookies are host-only. Never set the cookie domain to `.shiksha.*`.
- Tenant status (trial/active/suspended/expired) and plan-module gating are checked in middleware on the server for every school request. The UI only reflects it.

## Tenant migrations
- Every tenant migration runs on every school's database. It must be safe on existing data, backward compatible with the previous app version during rollout, and must not depend on data present in only some schools.
- No destructive change (drop/rename column or table) in the same release that stops using it: expand → migrate data → contract in a later release.
- Don't use `migrate:fresh` / `db:wipe` in any shared environment. Locally, use the project's reset command for both landlord and tenants.

## Identity (G1–G10 locked)
- One global **identity** (landlord) holds sign-in data only: phone (E.164), email, password hash, two-step secrets, status. **No names, addresses, children or other personal data in the landlord DB.**
- **Memberships** link an identity to a school with a kind: `staff`, `guardian` (`student` and `group_admin` reserved, not built in MVP).
- The school's own `staff` / `guardians` records hold personal data and reference the identity by `identity_ulid`. Roles and scopes reference the **staff record**, never the identity.
- Web sign-in happens on the school's own subdomain; after credentials, an **active membership for this school** is required. Wrong password, unknown account and no membership return the **same generic failure**.
- Staff: phone or email + password (+ two-step, see `40-`). Parents: phone + OTP. Super Admin: separate `platform_users` on the platform host, two-step always.
- One phone may belong to several guardian records (shared family phone); warn the school, don't block. Guardians without portal access are never linked.
- A person who is both staff and guardian in one school has two memberships and a context switcher; staff permissions never apply in the parent context.
- Mobile: one Sanctum token per membership; switching school issues another token.
- Membership is re-checked on every request (short cache allowed). Removing a guardian or staff member revokes the membership and its tokens immediately.
