# Income Module

Tracks money coming **in** to a project or job site (client deposits, draw payments, etc.). Each record has a date, title, description, amount, and file attachments.

## Status

- **Phase 1 (done, 2026-08-12):** Project-level Income page (`projects/{project}/income`).
- **Phase 2 (pending):** Job site Income page — to be built after the project page is tested, per the one-page-at-a-time rule. The `JobSite::income()` relationship already exists.

## Data Model

Table `incomes` (migration `2026_08_12_100000_create_incomes_table.php`), following the dual FK pattern from `docs/project-jobsite-parity-rule.md`:

| Column | Notes |
|---|---|
| `project_id` | required, cascade on delete |
| `job_site_id` | nullable — null = project-level income |
| `income_date` | date |
| `title` | string |
| `description` | nullable text |
| `amount` | integer **cents** (see `docs/monetary-storage.md`), exposed as dollars via `amount()` Attribute |
| `created_by` | FK to users |

Indexes: `(project_id, income_date)`, `job_site_id`.

`App\Models\Income` relationships: `project()`, `jobSite()`, `createdBy()`, `attachments()` (morphMany). Deleting an income deletes its attachments (and their files, via the `Attachment` deleting hook). Helper: `isProjectLevel()`.

Inverse relationships: `Project::income()`, `Project::projectLevelIncome()`, `JobSite::income()`.

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

## Localization

`_income` block added to `lang/pt_BR.json` and `lang/en.json`. In the BR GUI the feature is called **"Entradas"** (client's chosen term — nav tab/page title plural, "Entrada" for a single record); the DB, code, and English GUI keep "income". Includes lowercase validation attribute names (`date`, `title`, `description`, `amount`, `location`).

## Intentionally out of scope (per owner decision, 2026-08-12)

- No link to the Invoices module — income records are standalone manual entries.
- Not included in the project/jobsite financial reports for now (reports still derive revenue from contract value + change orders). Can be added later as an "Income received" card.
- No payment status/method fields — a record represents money already received.
