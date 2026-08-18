# Company Financials Report

## Overview
One page answering "where does the company stand": everything **received and paid**, and
everything **still to receive and still to pay** — income, invoices, expenses and
subcontractor contracts *including their cronograma parcelas and medições*.

- **Route:** `reports/company-financials` (Reports menu, admin-only)
- **Component:** `app/Livewire/Report/CompanyFinancialReport.php`
- **Service:** `app/Services/CompanyFinancialService.php` — the single source of the numbers
- **PDF:** `CompanyFinancialReportPdfController` + `pdf/company-financial-report.blade.php`
- **Screen:** `livewire/report/company-financial-report.blade.php`

Built Aug 2026 because the existing reports each showed one half: Accounts Payable lists
payables line by line, Payment Schedule totals them by month, and neither knows the money
coming in.

## What it shows
1. **Position tiles** — Received · Paid · To Receive (with overdue) · To Pay (with overdue).
2. **Net Cash (settled)** — what actually moved — and **Net Forecast** — everything
   received and to receive minus everything paid and to pay.
3. **By Source** — Income, Invoices, Expenses, Contracts × settled / open / overdue / total.
4. **Month by Month** — in, out and net per month, with a *No due date* row so the months
   always add up to the totals.
5. **Detail** — every entry behind those numbers: date, source, description, party,
   project, job site, status, signed amount. It has its own direction / status / source
   filters, and **narrowing it never changes the totals above** (deliberate: the summary
   is the company position, the list is a lens on it).

## Where the numbers come from

**Money in**
- **Income** records: `status = received` counts as cash on `income_date`; `status =
  expected` is a receivable on its `due_date`. This is what lets receivables exist
  **without** an invoice (see `income-module.md`).
  Under a **job-site scope** the query also matches project-level income distributed to
  that job site, and counts **only that job site's share**. The project and client scopes
  count the income **once, whole** — so a project's job sites can sum to less than the
  project total, by exactly the undistributed remainder.
- **Invoice payments** — `completed` / `partially_refunded`, net of refunds, by payment date.
- **Open invoices** — balance due on the invoice due date. Drafts are excluded.

**Money out**
- **Expenses** — installments by `expense_payments.due_date`, one-time by
  `COALESCE(payment_due_date, expense_date)`; paid ones by their paid date.
- **Contracts** — cash paid by payment date, plus what is still owed dated by
  `Contract::openPayableItems()`: each open cronograma parcela on its own date
  (vencimento / data prevista), and whatever the cronograma does not cover on the
  contract's **end date**. Retention releases are labelled as such.

**Rules**
- Overdue is always **derived** (due date < today); no stored status is trusted.
- Cancelled expenses and contracts are excluded everywhere.
- Settled money is matched by the date it moved, open money by the date it is due — so a
  date range answers "what moved and what falls due in this window".
- Contract money with no date at all (no parcela date, no contract end date) can only
  appear when no range is set; it is reported in the *No due date* row.

## Filters
Client · Project · Job Site (dependent on the project) · From/To with presets (All time,
This month, Next month, Next 3 months, This year) — the same set as the payment schedule
report. Plus detail-only filters: direction (in/out), status (settled/open/overdue) and
source. Everything lives in the query string, so the CSV and PDF follow what is on screen.

## Exports
- **CSV** (`exportCsv`) — summary, by source, month by month, then the full detail list,
  honouring the detail filters.
- **PDF** — view and download, built from the same service and the same filters; the
  buttons append `window.location.search` at click time, the house pattern that keeps the
  export in sync with the screen.

## Consistency guard
The out side must agree with the existing reports. Verified on real data:

```
out open :  company 57.857,50   payment-schedule 57.857,50   MATCH
out paid :  company 39.853,00   payment-schedule 39.853,00   MATCH
```

If those ever drift, the cause is a rule changing in one service and not the other —
`Contract::openPayableItems()` is deliberately shared so the contract half cannot.

## Notes
- No migrations of its own; it reads what the other modules already store (the Income
  status/due-date columns ship with the income module change).
- Amounts are stored in cents; the service converts once through the model accessors.
- Invoices are optional: with none in the system the Invoices row simply does not appear.
