# Frontend conventions (Inertia + React + TypeScript)

## Structure
```
resources/js/pages/<module>/...       Inertia pages (e.g. pages/fees/collect.tsx)
resources/js/components/ui/           shadcn/ui (generated; edit sparingly)
resources/js/components/<module>/     module components (marks-grid.tsx)
resources/js/components/shared/       data-table, money, date, status-badge, empty-state
resources/js/layouts/                 app-layout (staff), parent-layout, platform-layout, auth-layout
resources/js/hooks/ lib/ types/
resources/js/actions/ routes/         Wayfinder output — generated, never edit by hand
```
- File names kebab-case; one main PascalCase component per file.
- Generated TypeScript types (from Data objects) are committed and never edited by hand; regenerate after changing a Data object.

## Data and routes
- Page props come from Data objects only. Type props with the generated types; no `any` (if unavoidable, add a comment explaining why).
- Use Wayfinder functions for every URL and form action; never hardcode paths.
- Server state: Inertia props, partial reloads and deferred props. No client-side cache library unless a screen needs polling/offline and it's approved.
- Forms: Inertia `useForm` / `<Form>` with server-side validation errors. Client-side validation is a convenience, never the only check.
- Tables: the shared `data-table` component (TanStack Table); server-side pagination, sorting and filtering for large lists.

## Formatting
- Money arrives as integer paise; display with `lib/format.ts` (en-IN grouping, ₹). Never do money arithmetic in React beyond display.
- Dates arrive as ISO strings; display in the school's timezone via `lib/format.ts`. Calendar dates (`YYYY-MM-DD`) are displayed as-is, never passed through `new Date()` in a way that shifts the day.
- All user-facing text through translation keys (English only for now; multilingual later). No hardcoded UI strings in components.

## Permissions in the UI
- Hide or disable actions based on the abilities object the server sends. Never infer access from role names on the client.
- Never put tenant IDs, internal `id`s or secrets in the page, URLs or local storage.

## UX baseline
- Mobile-first: parent and teacher daily flows must work on a low-cost Android phone over a slow connection. Keep page payloads small; paginate; lazy-load heavy components (charts, editors).
- Accessible: labelled inputs, keyboard navigation, focus management in dialogs, sufficient contrast, no colour-only status.
- Use shadcn/ui components and Tailwind tokens; don't introduce new colour values or a second component library.
- The prototype's screens and flows are a UX reference; rebuild them with these components, don't copy its HTML/CSS/JS.
