# Shiksha ERP — bug log

Manual-testing bugs. Add new bugs under "Open" using the template. Ask Claude: "work on BUG-003", "list open bugs", or "fix all open bugs in Fees".

## Status flow

`open` → `in progress` (AI working) → `fixed, retest` (AI finished; you test again) → `verified` (you confirmed) or back to `open` (still broken, add a note).

Only the owner moves a bug to `verified`.

## Template

```
### BUG-000 · Short title
- Status: open
- Module / screen: e.g. Fees › Collect fee (`/fees/collect`)
- Logged in as: e.g. accountant@greenfield.test
- Found: YYYY-MM-DD
- Steps: 1. … 2. … 3. …
- Expected: …
- Actual: … (paste the error text; mention a screenshot file if any)
- Notes / fix: (filled by AI when fixed: cause, files changed, tests run)
- Retest: (filled by owner: date + result)
```

## Open

### BUG-001 · Deleted school stays stuck in "Deleting" status
- Status: open
- Module / screen: Super Admin › Schools › School details (delete school)
- Logged in as: Super Admin (`superadmin@shiksha.test`)
- Found: 2026-10-29
- Steps: 1. Open a school's details. 2. Delete it with the typed confirmation. 3. Go back to the Schools list.
- Expected: the school is purged (database, files, landlord rows) and disappears from the list.
- Actual: the school stays in "Deleting" status and never finishes.
- Notes / fix: not investigated yet. Possible causes to check: `PurgeSchoolData` is a queued job and locally needs `php artisan queue:work` running (the queue is the `database` driver); or the job failed (see `failed_jobs`, and the retry in the Super Admin UI).
- Retest:

## Fixed, waiting for retest

_None yet._

## Verified

_None yet._
