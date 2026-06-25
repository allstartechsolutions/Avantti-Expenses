# Expense Report

A consolidated report that rolls **expenses** up by project/job site, by vendor, and by
cost code — with paid / outstanding / overdue totals — plus a flat detail list. It
complements the **Accounts Payable** report (which is dated-by-due-date and also includes
subcontractor contracts); the Expense Report answers *"where did the money go?"*

- **Route:** `GET /reports/expenses` → `reports.expenses` (admin only)
- **Sidebar:** Reports → Expense Report

## Files

| Layer | Path |
|-------|------|
| Service | `app/Services/ExpenseReportService.php` |
| Livewire component | `app/Livewire/Report/ExpenseReport.php` |
| View | `resources/views/livewire/report/expense-report.blade.php` |
| PDF controller | `app/Http/Controllers/ExpenseReportPdfController.php` |
| PDF view | `resources/views/pdf/expense-report.blade.php` |
| Routes | `routes/web.php` (admin reports group) |

## Filters

Date range (filters by `expense_date`), Client, Project, Job Site (auto-scopes to the
selected project), Vendor (supplier), Category (`item_type`: product/service/rental), and
Status. Quick-date buttons: current month, current quarter, year to date, last year.
All filters are synced to the URL query string.

### Status filter (derived)

`outstanding` and `overdue` are computed, not stored, so the status filter is applied in
PHP after loading:

- `all` — everything (default)
- `unpaid` — `outstanding > 0`
- `pending` — `outstanding > 0` and not overdue
- `overdue` — `overdue > 0`
- `paid` — `outstanding <= 0`

## Views (tabs)

1. **By Project / Job Site** — projects with job sites nested; project-level expenses
   (no job site) appear as a "Project-level" sub-row. Columns: Total / Paid / Outstanding / Overdue.
2. **By Vendor** — same columns grouped by supplier.
3. **By Cost Code** — committed cost per `budget_item`, at the **line-item** level. Shows
   **total cost only** (no paid/outstanding split) because payments are tracked per expense,
   not per line. Line items with no cost code, and expenses with no line items, fall under
   "Unassigned".
   > ⚠️ **Pending rework (next session):** the cost-code grouping will change as part of a
   > broader cost-code improvement that also brings cost codes into **contracts**. Treat the
   > current `byCostCode()` implementation and the "By Cost Code" tab as provisional.
4. **Detail** — flat transaction list with installment label (e.g. `3/10`).

## Amount semantics

Computed per expense in `ExpenseReportService::normalize()` from the eager-loaded
`payments` collection (no per-row queries):

- **One-time** (`total_installments = 1`): `paid` = full total if `status = 'paid'`, else 0.
  `overdue` = outstanding if unpaid and `payment_due_date ?? expense_date` is before today.
- **Installment** (`total_installments > 1`): `paid` = sum of paid payments;
  `overdue` = sum of unpaid payments whose `due_date` is before today.
- `outstanding = total − paid` in all cases.

Cancelled expenses are always excluded. All money is stored as cents and surfaced as
dollars via model accessors; display uses `Number::currency()` with `config('app.currency')`
and `config('app.locale')`.

## Exports

Both reflect the **active tab + current filters**.

- **CSV** — `ExpenseReport::exportCsv()` streams the active view (UTF-8 BOM for Excel).
- **PDF** — DomPDF via `ExpenseReportPdfController` (`reports.expenses.pdf.download` /
  `reports.expenses.pdf.view`). Detail view renders landscape; others portrait.

## Build log

### Session 1 — initial build (2026-06-25)

Built the report end to end, reusing the existing Accounts Payable patterns
(`AccountsPayableService` / DomPDF controller) for consistency. Delivered in three steps
per the project's "one page at a time" rule:

1. **On-screen page** — service, Livewire component, view, route `reports.expenses`, sidebar link.
2. **CSV export** — `exportCsv()` reflecting the active tab + filters.
3. **PDF export** — controller, `pdf/expense-report.blade.php`, view/download routes.

Decisions made with the user:
- New dedicated report (not an extension of Accounts Payable).
- "Category" = `item_type` filter **and** cost-code grouping ("Both").
- Date range filters by `expense_date`; overdue derived from due dates; cancelled excluded.

**Open / next session:** rework the **By Cost Code** view as part of a wider cost-code
improvement that also adds cost codes to **contracts** (see ⚠️ note above). Everything else
on this report is considered complete pending the user's manual verification in their dev
environment (this checkout had no `vendor/`, so it could not be booted here).
