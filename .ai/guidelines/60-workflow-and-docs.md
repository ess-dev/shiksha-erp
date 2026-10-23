# Workflow, git and documentation

## Branches and commits
- Long-lived branches: `dev` → `staging` → `production`. Work on `feature/<short-name>`, `fix/<short-name>` (from `dev`) or `hotfix/<short-name>` (from `production`, merged back down to `staging` and `dev`).
- Pull requests only; `staging` and `production` are protected with required CI checks. Never push directly to them, never force-push shared branches, never commit `.env` or secrets.
- Conventional Commits: `feat(fees): record part payments`, `fix(attendance): …`, `test(access): …`, `chore: …`. One logical change per commit.
- Lefthook pre-commit hooks must pass; don't bypass them with `--no-verify`.

## How to approach a task
1. Identify the module and its dependencies (Access and School structure underpin everything).
2. Read the relevant existing code, migrations, tests and these guidelines.
3. For anything larger than a small change, write a short plan first: data changes (landlord or tenant), Actions/Queries/Policies, permissions, Data objects/types, pages, jobs, tests.
4. Build in small, reviewable steps; run the tests after each.
5. Summarise as required in `00-project-overview.md`.

## Migrations and data safety
- Never edit a migration that has been merged to `dev`; add a new one.
- Every tenant migration must be safe to run on many existing school databases (see `10-`).
- Seeders: landlord seeders for platform data (plans, modules, permission catalog, role templates); tenant seeders for demo schools only. Demo data is fictional and deterministic.
- To rebuild local data, use only the project's local reset command (local environment only). Never run `migrate:fresh` or `db:wipe`.

## Documentation
- Decisions are recorded in the project docs; when a task changes a documented decision, flag it in the summary instead of silently diverging.
- **These guidelines live in the repo's `.ai/guidelines/` and that copy is the source of truth.** Change them only by PR, then run `php artisan boost:update`. Don't edit the generated `CLAUDE.md` / `AGENTS.md` / Cursor rules directly.
- Each module keeps a short `docs/modules/<module>.md` in the repo: purpose, entities, Actions, permissions, settings, jobs, known limitations. Update it in the same PR as the code.
- Mark values that need expert confirmation with `illustrative — verify` (tax, board grading, statutory payroll, legal retention).

## Things to ask about rather than guess
Deleting or restructuring existing data, changes to money/audit/identity tables, new dependencies, new soft-delete tables, anything touching another school's data, and any decision listed as open in `00-project-overview.md`.
