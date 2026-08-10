# Payment Details Report

Implemented July 2026.

## Overview

The missing layer between the Payment Schedule (cronograma — aggregates only) and the Expense Report (whole-expense figures): **one row per individual payment**, so period totals are true period amounts.

- **Route:** `GET /reports/payment-details` → `reports.payment-details` (admin only)
- **Sidebar:** Reports → Payment Details

Rows:
- **Expense installments** — each `expense_payments` row individually, with its own due date and installment amount (label `n/N`).
- **One-time expenses** — due date = `COALESCE(payment_due_date, expense_date)` (label `1x`).
- **Contract payments made** — dated by `payment_date`, always status `paid`.
- **Open contract balances** — contracts have no payment schedule, so the balance (adjusted amount − paid) is placed on the contract **end date**; contracts without an end date always appear, undated, so nothing is hidden. This placement is an estimate — a banner on screen and PDF says so.

## Semantics

- Date range matches **open rows by due date, paid rows by paid date** (`COALESCE(paid_date, due_date/expense_date)`) — same convention as Accounts Payable.
- Reversed From/To bounds are swapped automatically.
- **Overdue is derived** (row date < today); stored statuses are never consulted.
- Cancelled expenses and contracts are excluded.
- Paid rows show the paid date and **who marked it paid** (`paid_by` / contract payment `created_by`).

## Filters

Period (presets: current month, next month, next 3 months, this year), Client, Project, Job Site (dependent select), Vendor (suppliers), Subcontractor, Type (Expenses + Contracts / Expenses only / Contracts only), Status (**multi-select** checkboxes — any combination of Pending / Overdue / Paid; none selected = all — reworked 2026-08-10). All URL-synced.

Status filter compatibility: `PaymentDetailReportService` accepts a string or array for `$statusFilter` ('all', a single status, or an array of statuses — invalid values drop out), so old single-status bookmarked URLs and the PDF controller's array query params (`statusFilter[0]=paid…`) both work. The PDF header shows the selected statuses joined by commas.

Filter interaction rule: a **Vendor** filter hides contract rows (a supplier can't match a contract) and a **Subcontractor** filter hides expense rows — enforced in the service (`includesExpenses()` / `includesContracts()`).

## Views

Tabs: **Detail** (default, flat rows sorted by date, undated last), **By Project / Job Site** (project rows with job-site sub-rows), **By Vendor** (suppliers and subcontractors, contract rows tagged). KPI tiles: Total in Period, Paid, Pending, Overdue. CSV exports the active tab; PDF (view/download) uses the click-time-URL pattern (see `docs/payment-schedule.md` → PDF/CSV Exports) and renders the active tab (detail = landscape).

## Files

| Layer | Path |
|-------|------|
| Service | `app/Services/PaymentDetailReportService.php` |
| Livewire component | `app/Livewire/Report/PaymentDetailReport.php` |
| View | `resources/views/livewire/report/payment-detail-report.blade.php` |
| PDF controller | `app/Http/Controllers/PaymentDetailReportPdfController.php` |
| PDF view | `resources/views/pdf/payment-detail-report.blade.php` |
| Routes | `routes/web.php` (admin reports group) |

No migrations — pure read layer. The Expense Report and Payment Schedule are unchanged (deliberately: the Expense Report keeps whole-expense semantics; see its doc).

## Related fix (same session)

`ExpenseReportService::normalize()` divided collection sums by 100 even though `ExpensePayment.amount` collection sums already return dollars via the accessor — installment expenses' Paid/Outstanding were 100× too small on the Expense Report. Fixed by dropping the `/ 100`.
