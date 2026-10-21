# Shiksha ERP — project overview (read first)

Shiksha ERP is a multi-tenant School ERP SaaS for Indian K–12 schools: admissions, students, attendance, fees, exams, communication, homework, dashboards; HR, transport and AI features later.

## Locked stack
- Laravel 13, **PHP 8.3** (do not use PHP 8.4+ syntax or features: no property hooks, no asymmetric visibility, no `new` without parentheses in chains, no `array_find`). Composer platform is pinned to 8.3.
- Inertia.js + React + TypeScript (strict), Tailwind CSS, shadcn/ui, Laravel Wayfinder.
- PostgreSQL 18 (central "landlord" database + **one database per school organization**), `spatie/laravel-multitenancy`.
- Redis + Horizon on Linux servers; database queue/cache/session drivers on Windows development machines.
- Pest **4** (+ browser tests), Vitest, Pint, Larastan, ESLint + Prettier, Lefthook.
- `spatie/laravel-data` + generated TypeScript types. Sanctum for the future mobile API.

## Source of truth
Decisions live in the claude.ai project docs, mirrored here as rules:
- Tech stack, tenancy, versions, local environment: `decision-tech-stack-and-tenancy`
- Code structure and conventions (P1–P12): `proposal-code-structure-and-conventions`
- Identity and memberships (G1–G10): `proposal-global-identity`
- Roles and permissions model: `decision-roles-and-permissions`
- The HTML prototype (`prototype-handoff`, `prototype-decisions-log`, `prototype-v6-round5`) is **reference behaviour only**. It is not a spec, not a schema and not an API contract. Its business numbers (fees, grades, leave quotas, GST, payroll rules) are illustrative.

## Still open — do not decide these in code
Product domain, staging host (P11), mobile API style (P4.3), payment gateway, SMS/email/WhatsApp providers, MVP build order, legal retention periods, board-specific grading rules, tax treatment and money-rounding sign-off. If a task depends on one, build behind an interface or config value, and say so in your summary.

## Non-negotiable behaviour for agents
1. **Inspect before writing.** Read the existing code, migrations and tests for the area you touch. Never invent a table, column, class, route, config key or package and present it as existing.
2. **Stay in scope.** Change only what the task needs. Don't reformat, rename or "improve" unrelated code.
3. **No new dependencies** (Composer or npm) without stating why, the alternatives, and getting approval.
4. **Tenant isolation, permissions and audit are never optional** (see `10-`, `25-`, `20-`).
5. **Never claim something is tested or working unless you ran it.** Report exactly which commands you ran and their result.
6. **Don't hardcode** board rules, tax rates, statutory rules or school policies. Make them configurable and mark illustrative defaults `// illustrative — verify`.
7. When something is ambiguous but not blocking, choose the safest reasonable option, state the assumption, and continue. Stop and ask only when a wrong guess would be expensive or irreversible (schema shape of money/audit tables, deleting data, security boundaries).

## Every task summary must include
Files changed · migrations (landlord or tenant) · new permissions/config keys · tests added and commands run with results · assumptions · anything not verified.

## Decided after the first draft of these rules (19 Oct 2026, `decision-platform-setup-details`)
- File storage (D11): one logical `tenant` disk; local disk by default, S3 (our own AWS account, `ap-south-1`) enabled per school by Super Admin only. No code names `s3`/`local` directly.
- Framework tables (D1): sessions, jobs, failed jobs, batches, cache, locks, password resets live on the **landlord** connection.
- Reserved subdomains and slug rules (D2): `config/shiksha.php`.
- Tenant database/role name `shiksha_t_<ulid>` with encrypted per-school credentials (D3).
- Translations (D4): Laravel `lang/` files shared to React via Inertia; no i18n package.
