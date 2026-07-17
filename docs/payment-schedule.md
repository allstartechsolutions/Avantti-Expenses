# Payment Schedule (Project & Job Site Financial Reports)

## Overview
A **Payment Schedule** section on the project financial report (`projects/{project}/report`) and job site financial report (`job-sites/{jobSite}/report`), including their PDFs. It answers: what have we paid, what is still open, when is it due, and what's coming month by month — combining **expenses** (scheduled by due date) and **subcontractor contracts** (totals only).

## What It Shows
1. **Expense tiles** — Expense Commitments / Paid / Upcoming / Overdue, by payment due date, with payment counts.
2. **Contracts strip** — adjusted amount (base + change orders), paid, balance due. Contracts have **no payment due dates** ("no schedule"): balances are point-in-time and never appear in the monthly projection.
3. **Combined summary** — Expenses + Contracts × Committed / Paid / Outstanding.
4. **Upcoming Payments by Month** — an *Overdue (past due)* bucket, then one row per month until the last scheduled open payment (capped at 24 months), then a *Later* bucket if anything falls beyond the cap. Nothing is silently dropped: Overdue + months + Later = total open.

## Rules & Semantics
- **Due dates**: installment expenses use `expense_payments.due_date`; one-time expenses use `COALESCE(payment_due_date, expense_date)`.
- **Overdue is derived** (`due date < today`); the stored `'overdue'` status is never consulted (nothing auto-marks it).
- **Current month row spans [today, end of month]** so already-due items land only in the Overdue bucket (no double counting).
- **Cancelled expenses/contracts are excluded** — this is why the section's totals can differ from the page's "Total Expenses" card, which includes every expense.
- **Scope**: project report = whole project (project-level + all job sites); job site report = that job site only.

## Implementation
- **`app/Services/PaymentScheduleService.php`** — the single source of the numbers. `PaymentScheduleService::forProject($project)->build()` / `forJobSite($jobSite)->build()` returns `['expenses','contracts','combined','projection']`. Query patterns mirror `AccountsPayableService` (deliberately not merged: AP is period-driven and cross-project; this is point-in-time and scoped).
- Consumed by 4 call sites so screen and PDF always match:
  - `app/Livewire/Project/ProjectFinancialReport.php`, `app/Livewire/JobSite/JobSiteFinancialReport.php` (`getPaymentScheduleProperty()`)
  - `ProjectFinancialReportPdfController`, `JobSiteFinancialReportPdfController`
- Web partial: `resources/views/livewire/shared/payment-schedule-section.blade.php`
- PDF partial: `resources/views/pdf/partials/payment-schedule.blade.php`

## Notes
- Amounts are stored in cents; query-level `sum('amount')` is divided by 100, collection sums use the dollar accessors (same convention as `AccountsPayableService`).
- The projection issues ~2 queries per month row; volumes per project are small and `due_date` is indexed. A single GROUP BY month query is a drop-in optimization inside the service if ever needed.
- No migrations — pure read-layer feature.
