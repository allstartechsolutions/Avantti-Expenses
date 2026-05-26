# Financial Reports — Project, Job Site, and Accounts Payable

**Date:** 2026-05-26
**Goal:** Build three financial reports (per-project P&L, per-jobsite P&L, multi-project accounts payable) that account for **everything** affecting the bottom line: expenses, signed change orders at every level, subcontractor contracts with their own change orders, and paid vs unpaid balances.

This is a separate session from the dashboard + installer work documented in `2026-05-26-dashboard-and-installer.md`. The work here builds on top of that.

---

## Overview

This session delivered:

1. **Negative change orders** — both project-level and jobsite-level `ChangeOrder` rows can now hold negative amounts (for deductive change orders). DB column flipped from `unsignedBigInteger` to `bigInteger`, validation relaxed, displays show `+$X` / `−$X` with red color on negatives.
2. **`amount_source` on Project** — a toggle (manual / from_jobsites) so customers who don't enter a single project-level contract amount can compute it from the sum of their job-site amounts. Existing projects default to `manual`.
3. **`getContractValue()` and `getAdjustedContractValue()` model methods** — single source of truth for the project's base value and its current value with change orders.
4. **Breadcrumb improvements** — Contract Show and Purchase Order Show now have the same breadcrumb pattern as the jobsite/project layouts.
5. **JobSite Overview enhancements** — added a Contracts card (Paid / Unpaid) and updated the Profit/Loss math to subtract contracts in addition to expenses.
6. **Per-project Financial Report** — new tab on every project, with KPIs, contract value breakdown, per-jobsite breakdown, expenses/contracts detail tables, and PDF export.
7. **Per-jobsite Financial Report** — sibling of the project report, scoped to a single jobsite.
8. **Accounts Payable Report** — multi-project AP report with date filters, KPIs, detail table, 12-month projections, and CSV + PDF exports.

---

## The financial math (single source of truth)

Every report and overview that talks about a project or jobsite's bottom line uses this same formula:

```
Adjusted Contract Value (revenue)
   = Base Contract Value (initial_amount or Σ jobsite job_amount)
   + Σ all change orders at project + jobsite level (signed)

Total Expenses
   = Σ Expense.total_amount for project_id = X (includes jobsite-level
     and PO-converted expenses; approved POs become Expenses on approval
     so there is no double-count)

Total Contracts (adjusted)
   = Σ Contract::getAdjustedAmount() for the project/jobsite
     where getAdjustedAmount = Contract.amount + Σ ContractChangeOrder.amount (signed)

Total Contracts Paid
   = Σ Contract::getAmountPaid() = Σ ContractPayment.amount

Profit / Loss
   = Adjusted Contract Value − Total Expenses − Total Contracts (adjusted)
```

The Profit number on the jobsite overview, the project report, the jobsite report, and any PDF export all derive from this same formula.

---

## 1. Negative change orders

### DB change

**File:** `database/migrations/2026_05_26_100002_make_change_orders_amount_signed.php`

Flipped `change_orders.amount` from `unsignedBigInteger` to `bigInteger`. Existing data is unchanged. The `contract_change_orders.amount` column was already signed (per its original migration).

### Validation

Removed `min:0` from `co_amount` / `amount` rules in:

- `app/Livewire/Project/ProjectChangeOrders.php`
- `app/Livewire/Project/ProjectShow.php` (legacy monolithic show)
- `app/Livewire/JobSite/JobSiteShow.php`

### Display

Mirrored the existing `ContractChangeOrder` convention: `+$X` prefix on positives, native `−$X` on negatives, red text for deductions. Applied in:

- `resources/views/livewire/project/project-change-orders.blade.php` (table + view modal)
- `resources/views/livewire/project/project-show.blade.php`
- `resources/views/livewire/job-site/job-site-show.blade.php`

Added a small hint on the create/edit form: *"Use a negative amount for deductive change orders (e.g., -500)."*

### Aggregation

No code changes needed for totals — `$changeOrders->sum('amount')` already returns signed values via the accessor, so totals naturally net additions vs deductions.

---

## 2. Project `amount_source` toggle

### Problem

Some customers fill in `Project.initial_amount` as the total client contract. Others don't — their contract value lives entirely on the job sites (sum of `JobSite.job_amount`). Without a flag, the report had no way to know which one to trust.

### Schema

**File:** `database/migrations/2026_05_26_100003_add_amount_source_to_projects.php`

Added `amount_source` string column (default `'manual'`) to `projects`. Existing rows default to `manual`, so nothing changes for them.

### Enum

**File:** `app/Enums/ProjectAmountSource.php`

```php
enum ProjectAmountSource: string {
    case MANUAL = 'manual';
    case FROM_JOBSITES = 'from_jobsites';
}
```

Cast on the Project model: `'amount_source' => ProjectAmountSource::class`.

### Model methods

**File:** `app/Models/Project.php`

```php
// Base value, no change orders. Resolves the mode.
public function getContractValue(): float
{
    if ($this->amount_source === ProjectAmountSource::FROM_JOBSITES) {
        return round($this->jobSites()->sum('job_amount') / 100, 2);
    }
    return (float) $this->initial_amount;
}

// Current value including all project- and jobsite-level change orders.
public function getAdjustedContractValue(): float
{
    $changeOrdersTotal = round($this->changeOrders()->sum('amount') / 100, 2);
    return round($this->getContractValue() + $changeOrdersTotal, 2);
}
```

### UI

**Create form** (`app/Livewire/Project/ProjectCreate.php` + view): radio toggle. In `manual` mode shows the `initial_amount` input (required). In `from_jobsites` mode shows a dashed-border notice ("Calculated from job sites you add later...") and `initial_amount` is stored as 0.

**Edit form** (`app/Livewire/Project/ProjectEdit.php` + view): same toggle. In `from_jobsites` mode shows a read-only card with the live calculated value (sum of jobsite amounts). On save, `initial_amount` is **only** updated when the mode is `manual` — preserves the prior value so switching back doesn't lose it.

**Validation** is conditional: `initial_amount` is required only in manual mode.

### Display propagation

These places now use `$project->getAdjustedContractValue()` (or `getContractValue()` for the base):

- `resources/views/livewire/project/project-overview.blade.php` (label changed from "Initial Amount" → "Amount", with a subtitle showing the base)
- `resources/views/livewire/project/project-index.blade.php`
- `resources/views/livewire/project/project-show.blade.php`
- `app/Livewire/Dashboard/DashboardIndex.php` (Over Budget KPI + list)
- `resources/views/livewire/dashboard/partials/admin.blade.php` (Over Budget list)

The dashboard "Over Budget" check now correctly evaluates `from_jobsites` projects (it was previously gated on `whereNotNull('initial_amount')`).

---

## 3. Breadcrumb improvements

Added the standard `<x-ui.breadcrumb>` pattern to two pages that had only a plain header.

### Contract Show

**File:** `resources/views/livewire/contract/contract-show.blade.php`

```
Project-level contract:  Projects > [Project] > Contracts > Contract #
JobSite-level contract:  Projects > [Project] > Job Sites > [JobSite] > Contracts > Contract #
```

### Purchase Order Show

**File:** `resources/views/livewire/purchase-order/purchase-order-show.blade.php`

```
Project-level PO:  Projects > [Project] > Purchase Orders > PO #
JobSite-level PO:  Projects > [Project] > Job Sites > [JobSite] > Purchase Orders > PO #
```

Same `@php`/`$breadcrumbs` array pattern, branching on whether `$contract->jobSite` / `$purchaseOrder->jobSite` is set.

---

## 4. JobSite Overview — new Contracts card + correct Profit

**File:** `app/Livewire/JobSite/JobSiteOverview.php` + `resources/views/livewire/job-site/job-site-overview.blade.php`

Was: 3 cards (Contract Value, Expenses, Profit). Profit math was incomplete — it ignored subcontractor contracts entirely.

Now: 4 cards on a `md:grid-cols-2 xl:grid-cols-4` grid:

1. **Total Contract Value** — `job_amount + Σ jobsite change orders`
2. **Total Expenses** — `Σ Expense.total_amount`
3. **Contracts** (new, purple) — headline shows `Σ Contract::getAdjustedAmount()` for this jobsite. Below: `Paid: $X / Unpaid: $Y` where Paid is `Σ getAmountPaid()` and Unpaid is the difference.
4. **Profit / Loss** — now computed as `Contract Value − Expenses − Contracts (adjusted)`. Caption shows the formula explicitly.

The component loads contracts with their change orders and payments eager-loaded for the math.

---

## 5. Per-project Financial Report

### Files

- `app/Livewire/Project/ProjectFinancialReport.php` (Livewire component)
- `resources/views/livewire/project/project-financial-report.blade.php` (view)
- `app/Http/Controllers/ProjectFinancialReportPdfController.php` (PDF)
- `resources/views/pdf/project-financial-report.blade.php` (PDF template)
- New nav tab in `resources/views/components/project-nav.blade.php`
- Routes in `routes/web.php`: `projects.report`, `projects.report.pdf.download`, `projects.report.pdf.view`

### Sections

1. **4 KPI cards** (same shape as jobsite overview): Contract Value, Expenses, Contracts (Paid/Unpaid), Profit/Loss.
2. **Contract Value Breakdown** — base value lines (one per jobsite if `from_jobsites`, single "Initial Amount" line if `manual`) plus every change order, dated and signed.
3. **Per Job Site Breakdown** table — one row per jobsite (linked to that jobsite's overview), a "Project-level" italic row for resources with `job_site_id IS NULL`, footer with the Project Total.
4. **Expenses detail** — date, item, supplier, scope, status chip, amount + footer total.
5. **Contracts detail** — contract # (linked), subcontractor, scope, status, adjusted amount (with "incl. ±$X CO" note when change orders exist), paid, balance (amber when > 0) + footer totals.
6. **View PDF / Download PDF** buttons in the page header.

### The "manual mode" caveat

When `amount_source = manual`, an amber note appears under the Per-jobsite Breakdown table explaining that the jobsite rows won't necessarily sum to the Project Total — manual mode intentionally decouples the two. In `from_jobsites` mode the rows do sum to the total.

### PDF specifics

`barryvdh/laravel-dompdf` (already installed). Letter portrait, DejaVu Sans, `#3F5189` brand color, base64-encoded logo, inline CSS. Mirrors the `ContractPaymentsPdfController` pattern.

---

## 6. Per-jobsite Financial Report

Sibling to the project report. Same structure minus the per-jobsite breakdown (one jobsite has no sub-jobsites).

### Files

- `app/Livewire/JobSite/JobSiteFinancialReport.php`
- `resources/views/livewire/job-site/job-site-financial-report.blade.php`
- `app/Http/Controllers/JobSiteFinancialReportPdfController.php`
- `resources/views/pdf/job-site-financial-report.blade.php`
- New nav tab in `resources/views/components/jobsite-nav.blade.php`
- Routes: `jobsites.report`, `jobsites.report.pdf.download`, `jobsites.report.pdf.view`

### Sections

1. 4 KPI cards (same shape).
2. Contract Value Breakdown — `job_amount` + each change order.
3. Expenses detail.
4. Contracts detail.
5. PDF buttons.

The math is the same as the JobSite Overview cards — the report is the audit trail behind those cards, plus PDF export.

---

## 7. Accounts Payable Report (multi-project)

### Why this one is different

The project and jobsite reports answer "is this project profitable?" The AP report answers "**what do I owe and when?**" — it's about cash flow timing, not profitability.

### Files

- `app/Livewire/Report/AccountsPayableReport.php` (component + CSV export)
- `resources/views/livewire/report/accounts-payable-report.blade.php`
- `app/Http/Controllers/AccountsPayableReportPdfController.php`
- `resources/views/pdf/accounts-payable-report.blade.php`
- New sidebar link in `resources/views/components/layouts/inc/sidebar.blade.php` (gated on `reports` module)
- Routes: `reports.accounts-payable`, `reports.accounts-payable.pdf.download`, `reports.accounts-payable.pdf.view`

### Data sources

The AP report unifies **two data sources** into one chronological table:

| Source | Date column | Status used |
|---|---|---|
| `ExpensePayment` (multi-installment expenses) | `due_date` | `pending` / `overdue` |
| `Expense` with `total_installments = 1` (one-time) | `payment_due_date` | `unpaid` / `overdue` |

Approved POs become Expenses on approval, so they're already in the Expense bucket — no double-count.

The status filter on the UI accepts `unpaid` (default = pending + overdue), `pending only`, `overdue only`, `paid only`, or `all`. The component maps these to the right values in both source tables (one-time expense uses `unpaid` where installments use `pending`).

### Sections

1. **Filters** — From / To (default current month), Project, Status. Quick buttons: Current month / Next month / Current quarter / YTD.
2. **3 KPIs** — Due in Period, **Overdue today** (red, ignores the date filter — always "as of today"), Paid in Period.
3. **Selected Period table** — combined installments + one-time, sorted by due date. Columns: Due Date · Vendor · Item · Project · Job Site · Status · Amount. Project and Job Site names are **clickable links opening in a new tab** for easy drill-down.
4. **Monthly Projections table** — 12 forward months starting **after** the selected period. Each row: count + amount of pending + overdue payments expected that month. Empty months show "—".

### Exports

- **CSV** — generated client-side by Livewire's `exportCsv()` returning a `StreamedResponse`. Uses UTF-8 BOM so Excel reads it cleanly. Filename: `accounts-payable-{from}-to-{to}.csv`.
- **PDF** — separate controller. Carries all current filters as query params (`?fromDate=...&toDate=...&projectFilter=...&statusFilter=...`) so the PDF reflects exactly what's on screen. Letter portrait, same brand styling as other PDFs.

---

## Sidebar navigation

The Reports sidebar group (gated on `reports` module via `ModuleAccess::isEnabled('reports')`) now has two entries:

- Sales Tax Report
- **Accounts Payable** (new)

The project and jobsite reports aren't in the sidebar — they're contextual, accessed via the "Report" tab inside a project or jobsite.

---

## Bugs fixed in this session

### `expense_payments.payment_date` does not exist

The dashboard cashflow chart query used `payment_date` for `ExpensePayment`, but that table uses `paid_date`. Fixed in `app/Livewire/Dashboard/DashboardIndex.php`. The other payment tables (`contract_payments`, `invoice_payments`) do use `payment_date` — those queries were already correct.

### `clients.name` does not exist

The dashboard's Past-Due Invoices query had `client:id,name` in the eager-load and the view used `$invoice->client?->name`. The table uses `company_name` (it's a B2B clients table). Fixed.

### `subcontractors.name` does not exist

Same issue, different table. The project Financial Report's Contracts detail had `subcontractor:id,name`. The `subcontractors` table also uses `company_name`. Fixed in `ProjectFinancialReport.php` and its view.

---

## Files in this session

### Created

- `database/migrations/2026_05_26_100002_make_change_orders_amount_signed.php`
- `database/migrations/2026_05_26_100003_add_amount_source_to_projects.php`
- `app/Enums/ProjectAmountSource.php`
- `app/Livewire/Project/ProjectFinancialReport.php`
- `app/Livewire/JobSite/JobSiteFinancialReport.php`
- `app/Livewire/Report/AccountsPayableReport.php`
- `app/Http/Controllers/ProjectFinancialReportPdfController.php`
- `app/Http/Controllers/JobSiteFinancialReportPdfController.php`
- `app/Http/Controllers/AccountsPayableReportPdfController.php`
- `resources/views/livewire/project/project-financial-report.blade.php`
- `resources/views/livewire/job-site/job-site-financial-report.blade.php`
- `resources/views/livewire/report/accounts-payable-report.blade.php`
- `resources/views/pdf/project-financial-report.blade.php`
- `resources/views/pdf/job-site-financial-report.blade.php`
- `resources/views/pdf/accounts-payable-report.blade.php`

### Modified

- `app/Models/Project.php` — added `amount_source` to fillable + cast; added `getContractValue()` and `getAdjustedContractValue()`
- `app/Livewire/Project/ProjectCreate.php` / `ProjectEdit.php` — amount source toggle, conditional validation, conditional save
- `app/Livewire/Project/ProjectChangeOrders.php` / `ProjectShow.php` — dropped `min:0` on change order amount
- `app/Livewire/JobSite/JobSiteShow.php` — dropped `min:0` on change order amount
- `app/Livewire/JobSite/JobSiteOverview.php` — loads contracts, passes paid/unpaid totals
- `app/Livewire/Dashboard/DashboardIndex.php` — Over Budget calc uses `getAdjustedContractValue()`
- `resources/views/components/project-nav.blade.php` — added Report tab
- `resources/views/components/jobsite-nav.blade.php` — added Report tab
- `resources/views/components/layouts/inc/sidebar.blade.php` — added AP Report link
- `resources/views/livewire/contract/contract-show.blade.php` — breadcrumbs
- `resources/views/livewire/purchase-order/purchase-order-show.blade.php` — breadcrumbs
- `resources/views/livewire/project/project-overview.blade.php` — uses `getAdjustedContractValue()` + "Calculated from job sites" subtitle
- `resources/views/livewire/project/project-index.blade.php` — uses `getAdjustedContractValue()`
- `resources/views/livewire/project/project-show.blade.php` — uses `getAdjustedContractValue()`, label "Amount"
- `resources/views/livewire/project/project-change-orders.blade.php` — +/- display, hint text
- `resources/views/livewire/project/project-create.blade.php` / `project-edit.blade.php` — amount source toggle UI
- `resources/views/livewire/job-site/job-site-overview.blade.php` — 4-card layout including new Contracts card, updated profit math
- `resources/views/livewire/job-site/job-site-show.blade.php` — +/- display, hint text
- `resources/views/livewire/dashboard/partials/admin.blade.php` — Over Budget list uses `getAdjustedContractValue()`
- `routes/web.php` — six new routes (3 for project report, 3 for jobsite report, 3 for AP report — 9 total)

---

## Known caveats and intentional choices

1. **Manual mode rows don't sum to project total.** When `amount_source = manual`, the per-jobsite breakdown rows show `job_amount + jobsite change orders`, but the Project Total uses `initial_amount + all change orders`. If the user enters both in manual mode, the column sum will diverge. We surface this with an amber note. In `from_jobsites` mode there's no divergence.

2. **AP report excludes contracts.** The user explicitly scoped this report to expenses only. Subcontractor `Contract` rows and `ContractPayment` rows are not included — contract payment scheduling is a different report (potential future work).

3. **AP projections start after the selected period.** If you set the period to current month, projections show months 1–12 starting next month. To see what's due "this month" you look at the Selected Period table; to see what's coming, look at Projections.

4. **Project-level change order at jobsite level?** Not possible by data model — a `ChangeOrder` has `project_id` (required) and `job_site_id` (nullable). The "Project-level" label means `job_site_id IS NULL` and that change order modifies the project contract value globally, not allocated to any jobsite.

5. **Approved POs are Expenses.** When a PurchaseOrder is approved, `PurchaseOrder::createExpenseFromPO()` creates an `Expense` row linked via `purchase_order_id`. The AP and Financial reports both query `Expense` only, so approved POs are counted once. Pending/draft POs are *not* in AP — they're projected costs, not commitments yet.

6. **`initial_amount` is preserved across mode switches.** Switching a project from manual → from_jobsites does not zero out the stored value. Switching back restores it. This is intentional: the user might toggle to validate the calculated value matches their expectation.

---

## Where things live (quick reference)

| Feature | URL | Component | PDF Controller |
|---|---|---|---|
| Per-project Report | `projects/{project}/report` | `ProjectFinancialReport` | `ProjectFinancialReportPdfController` |
| Per-jobsite Report | `job-sites/{jobSite}/report` | `JobSiteFinancialReport` | `JobSiteFinancialReportPdfController` |
| Accounts Payable | `reports/accounts-payable` | `AccountsPayableReport` | `AccountsPayableReportPdfController` |

---

## What's next

Discussed but not built — pick from these (or describe a different report) in the next session:

- **Portfolio Overview** — all projects in one table with key totals (budget, expenses, balance, % consumed, status). Same shape as the project report but rolled up across the company.
- **Budget vs Actual** — per cost-code, comparing budgeted line items to actual expenses with variance %.
- **Subcontractor Statement** — all contracts + payments for one subcontractor across projects, monthly closeout view.
- **Cash Flow** — AR (invoices received) vs AP (expenses + contract payments) per month, scoped or company-wide.
- **Aged Receivables / Payables** — bucketed by days overdue (0-30, 31-60, 61-90, 90+).

The Portfolio Overview is the most natural next step since it directly extends the per-project report we already built.
