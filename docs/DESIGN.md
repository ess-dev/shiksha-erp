# Shiksha ERP — design guide

Read this before building any screen. When the owner has not sent a prototype screenshot for a screen, build it from the patterns below so it looks like the rest of the product, then flag it as "designed from the guide" in the summary. When a screenshot exists, the screenshot wins; update this guide if it shows a new pattern.

Sources: the prototype's `assets/css/styles.css` (theme "Indigo trust"), the screens already built (Students, Schools, Plans, School structure), and the rules in `.ai/guidelines/30-frontend-conventions.md`. The prototype is a UX reference only: rebuild with shadcn/ui and Tailwind tokens, never copy its HTML, CSS or JS.

## Foundations

- **Stack**: Inertia + React + TypeScript, Tailwind 4 tokens in `resources/css/app.css`, shadcn/ui in `resources/js/components/ui/` (edit sparingly). No second component library, no new colour values.
- **Look**: calm, light, indigo. Page background `--background` (#f5f6fa), cards white with a 1 px border, soft radius, almost no shadow. Font Inter, body 14 px. Headings semibold with slightly negative tracking: page title `text-xl font-semibold tracking-tight`, card title 15 px.
- **Colour roles** (use the Tailwind tokens, never raw hex): primary indigo `#4f46e5` for the main action, links and active state; `secondary` / `accent` (indigo tint) for selected and soft fills; `muted-foreground` for secondary text; `destructive` red for dangerous actions and errors. Status colours are used on badges only: success green, warning amber, danger red, info blue, neutral grey. Never colour alone: every status also has a word.
- **Icons**: `lucide-react`, 16 px inside buttons, 18 px elsewhere, stroke 1.9. One icon per action, to the left of the label.
- **Dark mode**: tokens exist; use tokens only so it keeps working.
- **Money** is shown with `formatPaise` (₹, en-IN grouping), dates with `formatCalendarDate` ("15 Oct 2026"), never `new Date()` on a calendar date. Both are in `resources/js/lib/format.ts`.

## Layout

- **Shell**: sidebar (`AppSidebar`) on the left, header (`AppSidebarHeader`) with breadcrumbs and, when the person reaches several campuses, the campus switcher on the right. Sidebar entries show only what the person's abilities allow (`useCan('area.action')`). Entries for modules that do not exist yet are not added; the owner decides when.
- **Page body**: `mx-auto flex w-full max-w-6xl flex-col gap-4 p-4` for lists and profiles, `max-w-3xl` to `max-w-5xl` for settings and forms. Set `Page.layout = { breadcrumbs: [...] }`. Page title with a one-line muted subtitle on the left, action buttons on the right (`flex flex-wrap items-start justify-between gap-3`).
- **Mobile first**: everything works on a low-cost Android phone. Tables scroll inside `overflow-x-auto`; secondary columns use `hidden md:table-cell` / `lg:table-cell`; button rows wrap; dialogs are `max-h-[90vh]` with a scrolling body. Paginate, keep payloads small.
- **Print**: documents meant for paper use `layouts/print-layout.tsx` (no shell) and `print:hidden` on controls.

## Components and patterns

- **Buttons**: one primary per area (`Button` default); `variant="outline"` for the others, `size="sm"` in page headers and rows; `destructive` only for irreversible actions (Issue TC); `ghost`/`link` for inline actions. A button for something not built yet is `disabled` with a `Pending` badge and a `title` that says why.
- **Badges**: `Badge` default (indigo) for "New", "Current", "Ready"; `secondary` for neutral state ("Left", "Inactive"); `outline` for a quiet tag ("Sibling", "Invited"); `destructive` for "Fix" or "Overdue".
- **Cards**: `Card` with `CardHeader` (title + muted description) and `CardContent`. KPI card: small muted label, large value (`text-2xl font-semibold`), muted foot line.
- **Tables**: plain `<table className="w-full text-left text-sm">` (the shared `data-table` is not built; do not install TanStack without approval). Header row `border-b bg-muted/40 text-xs text-muted-foreground uppercase`, rows `divide-y hover:bg-muted/30`, primary cell = name link in `text-primary` with a muted second line, avatar = `InitialsBadge`. Footer: "Showing 1–25 of 208", Previous, "Page 1 of 9", Next. Server-side filters, sorting and pagination with `router.get(url, query, { preserveScroll, preserveState, replace })`.
- **Filter bar**: labelled fields in a grid inside the card's top (`grid gap-3 sm:grid-cols-2 lg:grid-cols-4`), small muted labels, `Input` for search (debounced 300 ms), native `<select className={selectClassName}>` from `components/school/field.tsx`.
- **Forms**: `Field` (label + control + `InputError`) from `components/school/field.tsx`; Inertia `<Form>` for simple posts, `useForm` when state matters; server errors shown under the field; rule errors under a key such as `errors.student`. Required data is enforced on the server, the form only helps.
- **Dialogs**: `Dialog` with `DialogHeader` (title + description), body, `DialogFooter` with Cancel (outline) then the action. Long dialogs: header and footer separated by borders, body `overflow-y-auto`. Destructive confirmations explain what is kept ("Records stay for the retention period"). Wizards (import) go step by step inside one dialog.
- **Tabs**: a `role="tablist"` row of buttons with an underline on the active one (see `pages/students/show.tsx`), arrow-key navigation, state kept in `?tab=`.
- **Toasts**: the server flashes `Inertia::flash('toast', ['type' => 'success', 'message' => ...])`; never build your own.
- **Empty and not-built states**: an empty list says what is missing and what to try ("No students match / Try another search or class"). A feature whose module is not built shows the control or column with the words **"Not available yet"** (or **"Coming soon"** for transport-type extras) and never a fake number. Keep the page shape from the prototype so it fills in later.
- **Sensitive data**: masked unless the server sends it; show a one-line muted note saying why ("Phone numbers are partly hidden. Only staff with permission see full contact details"). Names of people outside the viewer's reach become a count ("1 sibling outside your access").
- **Linking**: every URL from Wayfinder (`@/routes/...`, `@/actions/...`), never a hard-coded path. Props come from generated types (`App.Domain...`), re-exported in `resources/js/types/*.ts`.
- **Accessibility**: every input has a label, dialogs trap focus (Radix does), icon-only buttons have `aria-label`, tab/arrow keys work, contrast comes from the tokens.
- **Copy**: plain English, short, no jargon, school vocabulary (Class 1-A, Roll 3, Principal, Coordinator). Buttons are verbs ("Move student", "Issue TC", "Import 12 students"). All user text is an English literal for now (translation sharing is not built); keep sentences whole so they can be keyed later.

## Recipes for screens with no prototype

1. **List screen** (Staff, Admissions, Fees, Notices...): header + actions, filter card, table, footer pager, Import/Export only if the owner asks and a permission exists. Row opens a detail page.
2. **Detail screen**: back link, avatar + name + one-line meta, action buttons right, tabs, then cards of key/value pairs (`dl` in a `grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4`, label `text-xs text-muted-foreground`, value `text-sm font-medium`).
3. **Settings screen**: stacked cards, each with a title, a muted description and a form; inline row forms for lists (name, status, Save), an "add" form under the list.
4. **Create/edit**: a dialog for short forms (up to about 8 fields), a page for long ones; sectioned with `fieldset` + legend; Save at the end, Cancel beside it.
5. **Approval or workflow list**: table with a status badge and row actions; confirm dialogs that say what happens next and who is told.
6. **Dashboard**: a row of KPI cards, then cards with small tables or lists; charts only after the owner approves a chart library (the Super Admin dashboard uses plain SVG).
7. **Parent and teacher screens** (mobile app style): single column, large tap targets (min 44 px), cards stacked, one primary action per card, bottom-friendly buttons.

## Before you finish a screen

Abilities hide what the server refuses; page props contain no internal ids; loading and empty states exist; works at 375 px width; `npx vp check --fix`, `npm run types:check`, `npm run build` pass; placeholders are marked "Not available yet", "Coming soon" or "Pending"; the summary says which parts came from a screenshot and which from this guide.
