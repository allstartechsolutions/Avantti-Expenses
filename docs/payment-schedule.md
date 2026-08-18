# Payment Schedule

## Overview
Answers: what have we paid, what is still open, when is it due, and what's coming month by month — combining **expenses** (scheduled by due date) and **subcontractor contracts** (scheduled by their cronograma; see "Contract due dates" below). Available in two places:

1. **Standalone report** — `reports/payment-schedule` (Reports menu, admin-only): **system-wide by default**, filterable by Client, Project, Job Site (dependent selects), and a **From/To date range** (presets: All time, This month, Next month, Next 3 months, This year). The range matches **open items by due date** and **payments made by paid date** (`COALESCE(paid_date, due_date/expense_date)`); expenses without a due date are matched by their expense date so nothing is excluded. Contracts follow the same rule (parcelas by due date, contract payments by payment date). Reversed From/To bounds are swapped automatically. CSV export + PDF (download/view) carry all filters. Component: `app/Livewire/Report/PaymentScheduleReport.php`; PDF: `PaymentScheduleReportPdfController` + `pdf/payment-schedule-report.blade.php`. Service API: `PaymentScheduleService::forSystem(...)->between($from, $to)->build()`.
2. **Section on the financial reports** — the project financial report (`projects/{project}/report`) and job site financial report (`job-sites/{jobSite}/report`), including their PDFs, show the same content scoped to that project/job site.

Both render the same shared partials and the same service, so numbers always agree.

## What It Shows
1. **Expense tiles** — Expense Commitments / Paid / Upcoming / Overdue, by payment due date, with payment counts.
2. **Contracts strip** — adjusted amount, paid, upcoming, overdue, balance due. Contract money is dated (see below) and feeds the monthly projection alongside expenses.
3. **Combined summary** — Expenses + Contracts × Committed / Paid / Outstanding.
4. **Upcoming Payments by Month** — an *Overdue (past due)* bucket, then one row per month until the last scheduled open payment (capped at 24 months), then a *Later* bucket if anything falls beyond the cap, then a *No due date* bucket for contract money that has no date at all. Nothing is silently dropped: Overdue + months + Later + No due date = total open.

## Contract due dates (Aug 2026)
Contracts used to be point-in-time totals excluded from the projection. They are now scheduled:

- **Each open parcela** of the contract's cronograma is due on its own date — the vencimento for date parcelas, the data prevista for eventos. The open amount is the parcela's balance (scheduled − settled), so paid parcelas drop out.
- **Everything the cronograma does not cover** — the unscheduled remainder, and the entire balance of a contract without a cronograma — is due on the **contract's end date**.
- **A parcela with no date of its own** (an evento with no data prevista) falls back to the contract end date too.
- **Contracts with no end date** land in the *No due date* bucket rather than being forced into a month or dropped.
- The split is `balance due = Σ open parcelas + remainder`, so a contract's dated items always add up to what it still owes — no double counting, nothing lost.
- **When the parcelas exceed the balance due they are scaled down to it** (proportionally, with the rounding crumb on the first item). That happens whenever money was paid without settling a parcela — a payment made before the cronograma existed, or a payment batch — or when the cronograma schedules more than the adjusted contract amount. Without the clamp both reports over-state payables by the unlinked amount (a 25.600 parcela on a contract owing 4.247 reported 25.600).
- **`Contract::getUnscheduledRemaining()`** is the matching rule on the payment side: the unscheduled amount minus what has already been paid off-schedule, capped at the balance due. The contract page and the payment-batch gate both call it, so "what may be paid without naming a parcela" cannot drift between them.
- The split itself lives on the model: **`Contract::openPayableItems()`** returns `['date', 'amount', 'label', 'scheduled']` per item and is the single definition shared with the **Accounts Payable** report (see below).
- **The date range now filters contracts too**, like expenses: open items by their due date, contract payments by their payment date. Undated contract money is therefore excluded whenever a range is set (a filter cannot place what has no date). With no range, the strip's Adjusted/Paid/Balance are exactly the old point-in-time numbers.

## Accounts Payable report (same treatment, Aug 2026)
`reports/accounts-payable` now dates contract money the same way, via the same
`Contract::openPayableItems()`:

- **Selected Period rows** include open contract items (parcela or unscheduled remainder) whose due date falls in the period, shown as `Contract CTR-0001 — <parcela>` with the subcontractor as vendor and pending/overdue derived from the item's own date. They obey the status filter exactly like expense rows (`unpaid` / `pending` / `overdue` / `all`); `paid` still lists the contract payment ledger.
- **Due in Period** includes them (the tile now breaks out expenses vs contracts), and **Overdue (today)** includes past-due contract items regardless of the period.
- **Monthly Projections** (12 months after the period) include them.
- **Contract Balances Outstanding** and the **per-subcontractor summary** stay point-in-time, which is where contract money with no date at all (no parcela date, no end date) is still counted — a period-based figure cannot place it.
- **`AccountsPayableReportPdfController` now reads `AccountsPayableService`** instead of re-implementing the queries. That duplicate had drifted: the PDF's rows and "Paid in Period" left contract payments out entirely. Screen and PDF are now guaranteed to agree.

## PDF / CSV Exports
- **PDF buttons build their URL at click time** from `window.location.search` (Alpine `x-on:click.prevent`), because Livewire keeps the browser query string in sync with the active filters. This guarantees the PDF always matches what is on screen — static hrefs went stale when the DOM morph didn't update anchor attributes. The same pattern is applied to the Expense Report and Accounts Payable report export buttons. Requirement: the component's `$queryString` property names must match the query params the PDF controller reads (they do on all three reports).
- The PDF header prints the active scope (Client / Project / Job Site) and the Period when a date range is set; unfiltered shows "All clients, projects, and job sites".
- CSV is a Livewire action (`exportCsv`) and always reads current component state; the filename carries the date range.

## Terminology note (lang/en.json)
This install remaps terminology app-wide: code "Project" displays as **"Job Site"**, code "Job Sites" display as **"Lots"**. The singular mapping `"Job Site": "Lot"` (plus `"All projects"`/`"All job sites"`) was added July 2026 so report filter labels are unambiguous — before that, the project filter and the job-site filter both displayed as "Job Site". In the UI/PDFs this report therefore shows: **Job Site** = a project, **Lot** = a job site.

## Rules & Semantics
- **Due dates**: installment expenses use `expense_payments.due_date`; one-time expenses use `COALESCE(payment_due_date, expense_date)`.
- **Overdue is derived** (`due date < today`); the stored `'overdue'` status is never consulted (nothing auto-marks it).
- **Current month row spans [today, end of month]** so already-due items land only in the Overdue bucket (no double counting).
- **Cancelled expenses/contracts are excluded** — this is why the section's totals can differ from the page's "Total Expenses" card, which includes every expense.
- **Scope**: project report = whole project (project-level + all job sites); job site report = that job site only.

## Implementation
- **`app/Services/PaymentScheduleService.php`** — the single source of the numbers. `contractSchedule()` builds the dated contract items (cached per instance) and feeds both `contractSummary()` and `projection()`; contracts are loaded with `scheduleItems.payments` / `scheduleItems.measurements.payments` and share the parent instance so parcela balances cost no extra queries. `PaymentScheduleService::forSystem($clientId, $projectId, $jobSiteId)` (all args optional) / `forProject($project)` / `forJobSite($jobSite)`, then `->build()` returns `['expenses','contracts','combined','projection']`. Query patterns mirror `AccountsPayableService` (deliberately not merged: AP is period-driven; this is point-in-time).
- Consumed by 4 call sites so screen and PDF always match:
  - `app/Livewire/Project/ProjectFinancialReport.php`, `app/Livewire/JobSite/JobSiteFinancialReport.php` (`getPaymentScheduleProperty()`)
  - `ProjectFinancialReportPdfController`, `JobSiteFinancialReportPdfController`
- Web partial: `resources/views/livewire/shared/payment-schedule-section.blade.php`
- PDF partial: `resources/views/pdf/partials/payment-schedule.blade.php`

## Notes
- Amounts are stored in cents; query-level `sum('amount')` is divided by 100, collection sums use the dollar accessors (same convention as `AccountsPayableService`).
- The projection issues ~2 queries per month row; volumes per project are small and `due_date` is indexed. A single GROUP BY month query is a drop-in optimization inside the service if ever needed.
- No migrations — pure read-layer feature.

## See also
`docs/company-financials.md` — the company-wide report (Aug 2026) that puts this payables
picture next to the money coming in, with a line-level detail table. Its out side is
cross-checked against this service's numbers.
