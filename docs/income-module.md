# Income Module

Tracks money coming **in** to a project or job site (client deposits, draw payments, etc.). Each record has a date, title, description, amount, file attachments, and a **status**: money already **received**, or money still **expected**.

Expected income is what makes receivables independent of invoices — the company financial report reads it as money to receive without an invoice having to exist.

## Status

- **Phase 1 (done, 2026-08-12):** Project-level Income page (`projects/{project}/income`).
- **Received / expected (done, 2026-08-18):** status + due date, see below.
- **Phase 2 (done, 2026-08-18):** Job site Income page (`job-sites/{jobSite}/income`).
- **Distribution (done, 2026-08-18):** project-level income can be split across the project's job sites — see below.

## Data Model

Table `incomes` (migration `2026_08_12_100000_create_incomes_table.php`), following the dual FK pattern from `docs/project-jobsite-parity-rule.md`:

| Column | Notes |
|---|---|
| `project_id` | required, cascade on delete |
| `job_site_id` | nullable — null = project-level income |
| `income_date` | date — when it arrived (received) or a reference date (expected) |
| `due_date` | nullable date — **when expected money is due**; required while `status = expected`, and kept afterwards as the record of what was expected (only expected records read it) |
| `status` | enum `received` \| `expected`, default `received` (migration `2026_08_18_100000_add_expected_status_to_incomes_table.php`, additive: existing rows are `received`) |
| `title` | string |
| `description` | nullable text |
| `amount` | integer **cents** (see `docs/monetary-storage.md`), exposed as dollars via `amount()` Attribute |
| `created_by` | FK to users |

Indexes: `(project_id, income_date)`, `job_site_id`.

Table `income_distributions` (migration `2026_08_18_110000_create_income_distributions_table.php`):

| Column | Notes |
|---|---|
| `income_id` | required, cascade on delete |
| `job_site_id` | required, cascade on delete |
| `amount` | integer **cents** — this job site's share |

Unique on `(income_id, job_site_id)`, indexed on `job_site_id`. **Percent is not stored.**
It is an entry aid in the grid only; storing it would leave two sources of truth to
reconcile every time the income amount moves.

### Received vs expected

- `isReceived()` / `isExpected()` — status helpers.
- `isOverdue()` — **derived**: expected, with a due date before today. Never stored, the
  same rule the payables side uses.
- `effectiveDate()` — the date the record counts on: `income_date` when received, the
  `due_date` when expected. Every report reads this.
- `getStatusLabel()` / `getStatusColor()` — Received (green) / Expected (amber) /
  Overdue (red).
- `markReceived(?string $receivedOn = null)` — books expected money as received: sets the
  status and writes the receipt date to `income_date`. The `due_date` is **kept** — it
  records what was expected and when, and destroying it on a single click (there is no
  undo) would lose that for nothing, since `effectiveDate()` ignores it once received.

On the page: the form has a Status select, a required **Due Date** when Expected (the plain
date field relabels to *Reference Date*), a Status column with the badge, and a green ✓
**Mark as received** action on expected rows. The list is ordered by
`COALESCE(due_date, income_date)` — the same date the first column shows — and the view
modal carries the status badge, the due date and the reference date.

**The summary cards count received money only** (`Total Received`, `This Month`), with a
separate **To Receive** card carrying the overdue figure. Mixing expected into the total
would make this page disagree with `docs/company-financials.md`, which classifies the same
record as a receivable.

`App\Models\Income` relationships: `project()`, `jobSite()`, `createdBy()`, `distributions()` (hasMany), `attachments()` (morphMany). Deleting an income deletes its attachments (and their files, via the `Attachment` deleting hook). Helper: `isProjectLevel()`.

Inverse relationships: `Project::income()`, `Project::projectLevelIncome()`, `JobSite::income()`.

## Distribution across job sites

Money received **at the project level** can be shared out to the project's job sites — a
50.000 deposit covering 30.000 of Lot A and 20.000 of Lot B. Income booked **directly on a
job site** is not involved: it already belongs to one place.

The income keeps its own amount; the distribution rows only explain how it is shared.
Anything not distributed stays project-level, so a deposit can be allocated as the work is
assigned instead of all at once (**partial distribution is normal, not an error state**).
Expected income can be distributed too — the split describes the money regardless of
whether it has arrived.

### Rules (enforced on the model, `App\Models\Income`)

- `syncDistributions(array $shares)` — replaces the whole distribution in one transaction:
  `job_site_id => amount`, zero or blank drops the row. Rejects a non-project-level income,
  a job site from another project, and `Σ shares > amount`.
- `distributedTotal()`, `undistributedAmount()`, `isDistributed()` — the derived figures the
  UI and the reports read.
- An `updating` hook blocks **lowering the amount below what is already distributed**
  (the user adjusts the distribution first — nothing is silently rescaled) and blocks
  **pinning a distributed income to a single job site**. Both throw `DomainException`; the
  Livewire forms catch it and show it as a field error.
- Deleting an income cascades its distribution rows.

### UI

### The income form (project level)

There is **no separate distribute step**: the split is part of the Add/Edit Income form, so
the money and where it goes are decided in one pass. The form is a **full-page modal**
(`<x-ui.modal maxWidth="full">` — see the Design Standard in `CLAUDE.md`): sticky header,
`max-w-7xl` body, sticky footer with the running totals and the actions.

Left column — the money: amount (large, live), status, date, due date when Expected, title,
description, attachments.

Right column — **"Where does this money go?"**, two choices as selectable cards:

1. **One location** — the project in general, or a single job site. This is the classic
   behaviour; the card is the only thing offered when the project has no job sites yet.
2. **Split across locations** — the grid:
   - three totals (Income Amount / Distributed / Remaining at project level) over a
     progress bar that turns red the moment the grid is over-allocated;
   - a location search, **Select all**, **Split evenly**, **Clear all**;
   - a row per job site: tick box, name and contract value, **Amount** and **% of income**
     side by side, and **Take remainder**.

Amount and percent are two views of the same number — typing in either rewrites the other
(`updatedDistributionRows()`), so there is no per-row mode to choose. Changing the income
amount re-derives every percent (`updatedIncomeAmount()`); the amounts stay put. Only the
amount is stored. Ticking a site does not give it money; unticking one takes its share back
out. **Split evenly** divides the whole amount across the ticked sites cents-exact (odd cents
land on the first rows, so the split still adds up); **Take remainder** gives one site
everything not yet assigned.

Saving is one transaction: clear the old split → write the record → write the new shares.
Clearing first is what lets the amount move in either direction without tripping the model
guard. Switching to **One location** clears the split; switching to **Split** clears the
single location, since a split is project-level money by definition.

Errors are ordered by usefulness: over-allocation is reported once above the grid **before**
the field rules run, and an empty split asks for an amount instead of silently saving
nothing. The percent column has no `max:100` rule — it is derived, so a too-large share
must surface as over-allocation, not as a per-row percent error on every row at once.

### The detail view

Clicking an income opens a **full-page detail modal that shows everything the record
holds** — no subset:

- header: title, status badge, project, record id;
- the amount as a headline figure with the received/due date and, when overdue, by how many
  days;
- a **Record** panel: status, date, due date, project, location (or "Split across N
  locations"), added by, attachment count, created at, last updated;
- the description, or an explicit note that none was added;
- a **Distribution** panel: the full split as a table — every job site with its amount and
  its percent of the income, a progress bar, and footer rows for Distributed and
  Undistributed (project level). Each job site links to its own income page. When the income
  is not split, the panel says which single location it belongs to, that it sits at project
  level unsplit, or that the project has no locations yet;
- the attachments component (upload, download, admin delete);
- footer: added by and created at, next to Mark as received (when expected), Delete (admin),
  Edit and Close.

Distributed rows also show the split under the Location badge in the list.

**Job site income page** — the job site's share appears as a row tagged **Project share**
with the parent income's full amount underneath. It opens the same full-page detail modal in
**read-only** mode: a banner explaining that the project page owns the money, this location's
share as the headline figure, the **full distribution** table with this location's row
highlighted, the attachment list (read-only), and no edit/delete/mark-received actions —
only Close. A second icon jumps to the project income page, which is where that money is
edited.

### Reporting

`CompanyFinancialService::buildIncomingItems()` matches both routes under a job-site scope
(`job_site_id = X` **or** a distribution row for X) and counts **the share** for a
distributed income. The project and client scopes are untouched: they count the income
**once, whole**. So the job sites of a project can add up to less than the project total —
the difference is exactly the undistributed remainder.

The project income page's location filter is still `job_site_id`-based: filtering to a job
site there lists income booked on it, not shares of project-level money. The share view is
the job site's own income page.

## Attachments

Reuses the shared polymorphic attachments system (`docs/expense-audit-and-attachments.md`). `App\Livewire\Shared\Attachments` gained an `'income'` arm in `resolveModel()` and `storageDirectory()` (files stored privately under `income/`).

Files can be attached in two places:
- **Directly in the Add/Edit Income modal** (multiple files, uploaded when the record is saved; on edit they are added to the existing attachments).
- **In the view modal** via the shared attachments component (upload more, download, admin-only delete).

PDF/JPG/PNG up to 10MB each. Validation failures (e.g. wrong file type) block the save entirely — no record is created with rejected files.

## Project Income Page (Phase 1)

- Component: `app/Livewire/Project/ProjectIncome.php`
- View: `resources/views/livewire/project/project-income.blade.php`
- Route: `Route::get('projects/{project}/income', ProjectIncome::class)->name('projects.income')` — covered by the `projects.*` prefix in `config/modules.php`, no module change needed.
- Nav: `income` entry in `resources/views/components/project-nav.blade.php` (between Expenses and Purchase Orders).

Features:
- Search (title/description) and location filter (all / project general / per job site).
- Summary cards: Total Income and This Month.
- Create/edit via a modal on the page (no separate create page — the form is only a few fields), including multi-file upload in the same modal.
- View modal shows details plus the shared attachments component.
- Delete is admin-only with `wire:confirm`; attachment paperclip count shown in the table.

## Job Site Income Page (Phase 2)

- Component: `app/Livewire/JobSite/JobSiteIncome.php`
- View: `resources/views/livewire/job-site/job-site-income.blade.php`
- Route: `Route::get('job-sites/{jobSite}/income', JobSiteIncome::class)->name('jobsites.income')`
- Nav: `income` entry in `resources/views/components/jobsite-nav.blade.php` (between Expenses and Change Orders, mirroring the project nav).

Same shape as the project page — search, the three summary cards, full-page create/edit form
with uploads, full-page detail view, admin-only delete, mark-as-received — scoped to this job
site. Instead of a location selector it shows a Location panel naming the job site and
explaining that a payment shared between locations is added at the project level, with a link
there. The one addition is the **Project share** row described above: counted in the cards,
shown in the list, viewable read-only here, editable only on the project page.

## Localization

`_income` block added to `lang/pt_BR.json` and `lang/en.json`. In the BR GUI the feature is called **"Entradas"** (client's chosen term — nav tab/page title plural, "Entrada" for a single record); the DB, code, and English GUI keep "income". Includes lowercase validation attribute names (`date`, `title`, `description`, `amount`, `location`).

## Intentionally out of scope (per owner decision, 2026-08-12)

- No link to the Invoices module — income records are standalone manual entries.
- Not included in the project/jobsite financial reports for now (reports still derive revenue from contract value + change orders). Can be added later as an "Income received" card.
- No payment status/method fields — a record represents money already received.
