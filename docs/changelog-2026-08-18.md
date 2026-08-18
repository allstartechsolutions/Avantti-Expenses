# Changelog — 2026-08-17 / 18

Contract payment schedule finished (phases 3–7), contract money brought into the reports,
a new company-wide financial report, receivables in the Income module, and one production
bug fix. Feature background: `docs/contract-payment-schedule-plan.md`.

## Fix: change orders returned a 500 (production)

`/projects/{id}/change-orders`, the project page and `/job-sites/{id}` (which also backs
`/job-sites/{id}/change-orders`) all fatalled. A translation sweep in commit `196de07` had
wrapped a **PHP property name** in a translation call, producing a nested Blade expression:

```blade
{{ $changeOrder->{{ __('amount') }} < 0 ? '…' : '…' }}
```

Blade compiles that to broken PHP, so the view died on render. Three files carried the
identical line; all now read `$changeOrder->amount`. Swept every view afterwards
(`Blade::compileString()` + `php -l`) — no other view was affected.

## Contract payment schedule (cronograma)

- **Phase 3 — medição por produção.** `Contract/ContractMeasurements`: a Medições card on
  every contract, the boletim editor (previsto / % anterior / % atual / valor do período,
  **fillable from either the % or the value**), approval with snapshotted retention,
  payment of the líquido with the cost-code lines filled from the boletim, and an optional
  **link to a cronograma parcela** — approving a linked medição makes the parcela payable
  and its payments settle it.
- **Phase 5 — payment batches** now name what they pay: a **Paga** column listing each
  contract's payable parcelas and medições, stored on the batch item and carried to the
  payment. Both approval paths refuse a batch row that would bypass the cronograma.
- **Phase 6 — PDFs.** Boletim de medição (with signature lines for both parties) and
  cronograma físico-financeiro.
- **Phase 7 — translation sweep.** 2.014 strings, 0 missing, 0 placeholder mismatches;
  fixed strings that were never wrapped in `__()` (contract page title, status labels,
  "Not specified"/"Unknown" fallbacks, payment-method labels at the model).

## Reports: contract money is now dated

Contracts used to be point-in-time totals excluded from any projection. Both the **payment
schedule** and **accounts payable** reports now date them through
`Contract::openPayableItems()`: each open parcela on its own date, the rest on the
contract's end date, undated contracts in a "No due date" bucket. The AP PDF controller was
rewritten to read `AccountsPayableService` — its duplicate query layer had been leaving
contract payments out of the PDF entirely. Details: `docs/payment-schedule.md`.

## New: Company Financials report

`reports/company-financials` — money in and out, settled and open, across income,
invoices, expenses and contracts (including cronograma parcelas and medições), with the
same Client / Project / Job Site / period filters as the other reports, plus CSV and PDF.
Its out side is cross-checked against the payment schedule report.
Details: `docs/company-financials.md`.

## Income: receivables without invoices

`incomes` gains `status` (received / expected) and a nullable `due_date` (migration
`2026_08_18_100000`, additive — existing rows are `received`). Expected income appears in
the company report's *To Receive* and goes overdue on its due date, so tracking money you
are owed no longer requires an invoice. The project income page gained the status select,
the due date, a status badge, a **mark as received** action, and a separate *To Receive*
card — the "Total" card counts received money only, so the page and the report agree.
Details: `docs/income-module.md`.

## Deploy

`php artisan migrate` (the audit-enum migration from phase 4 and the incomes migration),
then `php artisan view:clear`.
