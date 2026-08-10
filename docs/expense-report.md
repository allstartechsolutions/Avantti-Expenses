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

Date range, Client, Project, Job Site (auto-scopes to the
selected project), Vendor (supplier), Category (`item_type`: product/service/rental),
Status, and **Date Basis**. Quick-date buttons: current month, current quarter, year to date, last year.
All filters are synced to the URL query string.

### Date Basis (expense vs due)

The date range can match on either basis (`dateBasis`, default `expense`):

- **Expense date (incurred)** — original behavior: `expense_date BETWEEN from AND to`.
- **Payment due date** — one-time expenses match on `COALESCE(payment_due_date, expense_date)`;
  installment expenses match when **any** installment's `expense_payments.due_date` falls in the range.

**Amounts are always whole-expense figures** regardless of basis (an installment plan with one
payment due in the range shows its full total). This was a deliberate decision: every grouping
(project/vendor/cost-code/detail, CSV, PDF) consumes the per-expense `normalize()` figures, and
cost-code rows cannot be prorated (payments are per expense, not per line item). Period-portioned
"due in window" amounts are what the Accounts Payable report and the Payment Schedule section
(see `docs/payment-schedule.md`) answer. When due basis is active, the UI and PDF show a caveat:
"Showing expenses with a payment due in this period. Amounts are full expense totals…".
Invalid `dateBasis` values from the query string are coerced to `expense`. CSV/PDF filenames
include the basis.

When due basis is active, the **Detail tab (web, CSV, and PDF) shows and sorts by the due date**
instead of the expense date (column header switches to "Due Date"). The row's representative
due date (`normalize()`'s `due_date`) is: one-time = `COALESCE(payment_due_date, expense_date)`;
installments = the earliest installment due **within the filtered range** (the reason the row
matched), falling back to the earliest installment overall. For true per-installment period
amounts use the Payment Details report (`docs/payment-detail-report.md`).

### PDF export links
The View/Download PDF buttons build their URL at click time from `window.location.search`
(Livewire keeps the query string synced with the filters), so the PDF always matches the
on-screen filters. See `docs/payment-schedule.md` → "PDF / CSV Exports" for the pattern.

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
3. **By Cost Code** *(reworked 2026-08-10)* — expense line items **plus subcontractor
   contracts** per `budget_item`. Columns: Line Items / Expenses / Contracted / Contract
   Paid / Total Committed (= Expenses + Contracted).
   - **Expenses**: committed cost at the line-item level, as before (expense payments live
     per expense, not per line, so still no expense paid split). Uncoded items and
     item-less expenses fall under "Unassigned".
   - **Contracted**: full scheduled value per code of non-cancelled contracts matching the
     location filters with `start_date` ≤ range end — allocations + change orders via
     `Contract::costCodeSchedule()`, so contract amounts without a code roll into the
     budget's **default** cost code (see docs/contract-costcode-payments.md).
   - **Contract Paid**: contract payments **dated inside the range** per code.
   - Contracts are **omitted** whenever a vendor, category, or status filter is applied
     (those are expense concepts) — `ExpenseReportService::includesContracts()`.
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

**Open / next session:** ~~rework the **By Cost Code** view~~ — done 2026-08-10 as phase 6
of the contract cost-code project (docs/contract-costcode-payments.md): the tab now folds
in contract committed/paid per code (see updated view description above). CSV and PDF
exports updated to the new columns.
