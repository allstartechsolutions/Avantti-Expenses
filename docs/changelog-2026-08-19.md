# Changelog — 2026-08-19

Two system improvements: the header search now finds job sites as well as projects and
behaves like a real command palette, and the cost code add/edit form on budgets and
templates moved out of the sidebar into a dialog.

Nothing in this change is destructive: **no migrations**, no schema changes, no data
touched. Both items are view + component work plus translations.

Commit state at the time of writing: the header search is committed in `fe5d7df`; the cost
code dialogs are still in the working tree.

---

## Header search: projects *and* job sites

`app/Livewire/Shared/HeaderSearch.php` and its view were rewritten.

**The starting point.** The search box was already wired into the header and already lazy —
it was verified end to end in a browser before anything was touched. What made it feel dead
was its reach: it matched `project_name` and nothing else, so a client name, an address or a
job site returned "No job sites found".

**What it matches now.** Two `#[Computed]` groups, at most `PER_GROUP` (5) rows each,
ordered by name:

| Group | Matched against |
|---|---|
| Projects | `project_name`, `street`, `city`, `contact_person`, client `company_name` |
| Job sites | `job_site_name`, `street`, `city`, `contact_person`, parent project `project_name` |

**The database question — unchanged property, now proven.** `term()` returns `null` while
the trimmed term is under `MIN_LENGTH` (2) and every computed property short-circuits on it.
Measured with the query log:

| State | Queries |
|---|---|
| Page load, empty box | 0 |
| 1 character typed | 0 |
| 2+ characters typed | 3 (projects, their clients, job sites) |

`%` and `_` are escaped before they reach the `LIKE`, so typing a wildcard searches for that
literal character instead of matching every row. Relations are eager-loaded and the selects
are narrowed to the rendered columns, so a search is a fixed small number of queries however
many rows come back.

**The dropdown.** Grouped with counts; each row carries a status dot, the name, its parent
(client for a project, project for a job site), the address and the status label. Arrow keys
browse, enter opens, escape closes — the highlight is pure Alpine over the rendered anchors,
so browsing costs no server round trips. A spinner replaces the clear button while the
debounced request is in flight. The empty state names the term that failed and says what the
search actually covers; below the minimum length it says how many characters are needed.
`x-cloak` keeps the panel hidden until Alpine takes over. Right-aligned and capped at
`calc(100vw - 3rem)`, so it cannot push the page sideways.

**Terminology.** Group labels use `__('Projects')` and `__('Job Sites')`, so they follow
whatever each install calls those levels — currently **Job Sites** / **Lots** in English and
**Projetos** / **Locais** in pt_BR.

**Verified in the running app** (headless Chrome, authenticated, `/dashboard`): typing "Rio"
returned 1 project and 2 job sites; two arrow-downs then enter landed on `/job-sites/125`,
the second row. Nothing clipped the panel, and there were no JS exceptions. Light and dark
were both checked, as were the results, empty and below-minimum states.

**Gaps left open, recorded in `docs/header-search.md`:**

- Desktop only — `inc/header.blade.php` is `hidden lg:block`, so the mobile header still has
  no search. Doing it properly means a full-width overlay, not a 384 px dropdown.
- Not permission-scoped — see the new **N9** in `docs/permissions-notes.md`.
- "See all matches" goes to the projects index filtered by the term; there is no combined
  results page that also covers job sites.

Details: `docs/header-search.md`.

---

## Cost codes: the add/edit form is a dialog

Both cost code screens carried the same inline form in the right-hand sidebar, which pushed
the page around every time it opened. Both now open `<x-ui.modal maxWidth="2xl">` over the
list.

| Screen | Component | Dialog partial |
|---|---|---|
| Budget cost codes (project **and** job site budgets) — `/budgets/{budget}` | `Budget/BudgetShow` | `livewire/budget/partials/item-modal.blade.php` |
| Template cost codes — `/cost-codes/templates/{template}` | `CostCode/CostCodeTemplateShow` | `livewire/cost-code/partials/code-modal.blade.php` |

**Mechanics.** `$showForm` is gone from both components. They follow the pattern already
used by the requisition and quotation modals: the dialog lives in the DOM and the component
dispatches `open-modal` / `close-modal` with the modal's name, which is a constant on the
component (`BudgetShow::FORM_MODAL`, `CostCodeTemplateShow::FORM_MODAL`) so the view and the
component cannot drift apart. `openEditForm()` resets before it loads, because escape and
backdrop clicks close the dialog without telling the server.

**Three things that make repeated entry faster**, beyond the move itself:

- **Save & Add Another** — `save(true)` writes the row, then keeps the dialog open, clears
  the fields, holds the same parent, bumps the sort order and dispatches `cost-code-saved`
  so Alpine puts the cursor back in Code. A run of codes is one dialog rather than one per
  code. The button only renders when adding, never when editing.
- **Autofocus on Code** the moment the dialog opens (`data-autofocus`, driven off the
  modal's own `modal-opened` event).
- **Sort Order is computed**, not keyed in. `nextSortOrder($parentId)` returns the next free
  position under that parent, and the field says "Filled in for you — change it only to
  reorder."

Everything else the forms did is unchanged: the same validation rules, the same
per-budget / per-template unique code check, the same parent-code context panel when adding
a child, the same flash messages.

**Verified in the running app.** On `/budgets/14`: dialog opens with focus in Code, body
scroll locks, *Save & Add Another* keeps it open with the fields cleared and the sort order
advanced 7 → 8 while the list behind updates, validation renders inside the dialog, escape
closes and releases the scroll lock. On `/cost-codes/templates/2` (331 codes, 33 parents):
*Add Cost Code*, *Add Child Code* (parent shown as "1 - PROJETOS E APROVAÇÕES TÉCNICAS") and
the pencil *Edit* all open the right dialog with the right title, and *Save & Add Another* is
correctly absent while editing. No JS exceptions on either page. Server-side flows were also
asserted through Livewire's test harness. The probe records created during testing were
deleted.

**Size — decided, `maxWidth="2xl"`.** `CLAUDE.md` points at full-page modals for forms
carrying more than a couple of fields. That rule exists to stop real work being crammed into
a dialog too small for it, and it earns its keep on the requisition and quotation forms —
repeating line items, running totals, context you need on screen while typing. A cost code
is four fields and a parent label; a full-screen surface there would add a viewport repaint
and a lot of empty space to an action performed twenty times in a row, working against the
fast repeated entry these dialogs exist for. There is precedent for mid-size form dialogs
(`system-settings/tax-rate-settings` uses `lg`).

**The line for future screens:** full-page when the form carries repeating rows, computed
totals, or context the user must see while typing; a dialog when it is a handful of fields
on one record.

Details: `docs/cost-code-templates.md`, `docs/budget-costcode-system.md`.

---

## Translations

Both locales gained the new strings in the same change — 14 keys, appended under
`_header_search` and `_cost_code_dialog` markers:

- Header search: placeholder and sr-only label, "Clear search", the keyboard hint,
  "See all matches", "No matches for :term", the empty-state explanation,
  "Browse all projects", "Type at least :count characters to search.", and "On Hold"
  (`JobSiteStatus::label()` returns it raw and the view wraps it in `__()`).
- Cost code dialog: "Save & Add Another" and the sort-order hint.

The English file carries this install's terminology, so those strings read "job sites" and
"lots" where the source string says "projects" and "job sites".

---

## Also noticed, not fixed

- **`lang/en.json` maps "Search projects" → "Search job sites"** but the old placeholder
  key `"Search projects..."` mapped to itself, so the header said "projects" while the rest
  of the English UI said "job sites". The new placeholder key is translated properly; the
  old keys are now unused by this component but left in place in case another view uses them.
- **Vite's HMR websocket fails in the browser** — `public/hot` holds `Despesas.test:5173`
  while the page is served from `despesas.test`, and the case mismatch breaks the websocket
  handshake. Harmless for Livewire, but hot reload is dead during development.

---

## Deploy

No migrations. `php artisan view:clear` after pulling.
