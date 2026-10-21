# Access control (roles and permissions, Option C — layered)

## Model
- The **platform** owns the permission catalog (landlord): each permission has a key (`area.action`), module (for plan gating), risk level (`normal` / `sensitive` / `critical`) and an owner-only flag.
- The **platform** owns versioned role templates (Principal/owner, Vice principal, Coordinator, Class teacher, Subject teacher, Accountant, Admission officer, Transport in-charge, Librarian, Front office). Schools receive copies; a new template version is offered as an update the school can apply or skip, and the school's own edits are kept.
- Each **school** builds its roles (tenant DB) by copying a template, adjusting it, or starting blank. Permissions for modules outside the school's plan are unavailable.
- Access is scoped **at assignment**: whole school, campus(es), wing (class range + campuses), specific sections, or "classes they teach". One person can hold several roles; effective access is the union, each permission limited to its own role's scope.
- Class teacher and Subject teacher roles are assigned automatically from class-teacher allocation and the timetable.
- **Parents and students have no roles.** Guardian access comes only from the guardian–student link (plus portal access flag) and is limited to their own linked children.
- Approval rules (e.g. student-leave day limit) are school settings, separate from roles; the approver is whoever holds the matching permission for that scope.

## Enforcement (server-side, every time)
- Every route is authorized by a Policy or Gate. A missing check is a bug, not a style issue.
- Check in this order: tenant active → module in plan → permission → **scope** → object-level ownership. Role-level checks alone are never enough: a teacher with `attendance.mark` may mark only sections in their scope.
- List Queries filter by the caller's scope; show/update endpoints verify the specific record is in scope (return 404 for out-of-scope records, not 403, to avoid confirming existence).
- The frontend hides what the server refuses; it never decides access itself. Pass the user's effective abilities to Inertia as a computed Data object, not raw role data.

## Guardrails
- Owner-only permissions (e.g. `roles.manage`) can't be granted to non-owner roles.
- Nobody can grant a permission or scope they don't hold themselves.
- Every school has at least one owner; owner transfer happens only through the platform.
- Granting a critical permission requires an explicit confirmation step and is audited; the holder must have two-step enabled (see `40-`).
- Every change to roles, role permissions and assignments writes an audit event.

## Adding a permission
1. Add it to the landlord permission catalog seeder with module, risk and owner-only flag.
2. Add it to the relevant role-template version(s) with a changelog entry (schools see "update available").
3. Gate the route/Policy, the Query scope and the UI ability.
4. Tests: allowed in scope, denied out of scope, denied without the permission, denied when the module isn't in the plan, denied when the school is suspended.
