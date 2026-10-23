# Shiksha ERP — status and what is pending

Quick recall sheet. The detailed record (code map, decisions, verification) is in `docs/PROGRESS.md`; update both when work lands.
Last updated: 2026-10-22.

## Working approach

Screens are built one at a time from prototype screenshots the owner supplies (reference: `school-erp-prototype-v6.zip`, folder `superadmin/` etc.). After each screen the owner reviews, then says which screen is next. Do not start the next screen unprompted.

## Next up

- **Students and guardians.** Dependency approved and installed 2026-10-22 (`spatie/laravel-data` 4.23, transformer 3.x, platform pin 8.3.6; commit `5fc2125`). Owner to send prototype screenshots one screen at a time, plus the required student fields and whether CSV import is in scope.

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

## Housekeeping / known gaps

- No translation keys yet (English literals, like the starter kit pages).
- No `docs/modules/<module>.md` files yet.
- Pest browser tests not set up; full test suite takes about 4–5 minutes (285 tests passing at last run; one Audit test can fail once with a Postgres autovacuum permission error, passes when re-run). Never run two Pest processes at once.
- If tests ever behave oddly, check `bootstrap/cache/config.php`: a stale cached config once made the tests run against the dev database and wipe it. `php artisan config:clear` fixes it; `php artisan db:seed` rebuilds demo data.
- Orphaned old school databases from that incident remain in PostgreSQL (harmless, can be dropped by hand).
- Apache vhost for `admin.shiksha.localhost` not verified (only `php artisan serve --port=8000`).

## Local quick start

- Demo Super Admin: `superadmin@shiksha.test` / `password` (local only). It has no authenticator until you enrol one at first sign-in: open `http://admin.shiksha.localhost:8000/sign-in`.
- Demo schools: `greenfield.shiksha.localhost`, `riverside.shiksha.localhost` (`principal@greenfield.test` / `password`).
- Run: `php artisan serve --port=8000`, then `npm run build` (or `npm run dev`). New Super Admin: `php artisan platform:create-user "Name" email`.
- Quality gates: `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pest --compact`, `npm run types:check`, `npx vp check`, `npm run build`. After route changes: `php artisan wayfinder:generate --with-form`.
