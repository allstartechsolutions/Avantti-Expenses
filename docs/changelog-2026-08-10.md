# Session Changelog — August 10, 2026

All changes delivered in one session. Database migrations are **additive only** (2 new tables, 2 nullable/default columns — nothing existing is modified or touched). Feature-level detail lives in the linked docs.

**Deploy checklist:** `php artisan migrate` + `php artisan view:clear`. No config changes, no npm build, no backfill. Everything degrades gracefully on installs/projects without budgets.

## 1. Subcontractor employee delete is now admin-only

Closed the open decision from `docs/wishlist.md`: deleting a subcontractor employee requires admin (server-side `authorizeAdmin()` guard in `SubcontractorShow::deleteEmployee` + button hidden behind `@admin`). Adding employees remains open to all users.

## 2. Contract cost codes & progress payments (major feature — 6 phases)

Full design, decisions log, and per-phase detail: **`docs/contract-costcode-payments.md`**. Modeled on the user's budget spreadsheet (`docs/examples/ORCAMENTO DM - VALINHOS HYPER.xlsx`).

**The workflow, end to end (only appears for projects/lots that have a budget):**

- **Default cost code per budget** — star any budget line on the budget page (`budget_items.is_default`); every uncoded amount (contract remainders, uncoded change orders, itemless payments) rolls into it at display time — retroactive, no data migration. No default → "Unassigned" row.
- **Contract allocations** — optional "Cost Code Allocation" card on contract create/edit (`ManagesContractAllocations` trait + shared partial): searchable code picker, live Allocated/Remaining totals, over-allocation blocked, rows cleared on location change. Table `contract_budget_allocations`.
- **Change orders** — optional own cost code (nullable `budget_item_id`), select in the CO modal, code chip in the list. Empty = follows the contract's allocation.
- **Itemized progress payments (medição)** — the Record Payment modal on the contract page lists one line per cost code (scheduled, paid so far, current %). Entering a new cumulative % suggests the amount (Δ% × scheduled value, editable); line amounts auto-sum into the payment total; a mismatch blocks the save; empty lines = plain uncoded payment. Table `contract_payment_items` (amount + cumulative `percent_complete` per code).
- **Schedule of values per contract** — "Cost Codes" card on the contract page: Scheduled / Paid / % Complete / Balance per code, powered by `Contract::costCodeSchedule()` (the single calculation every view reuses; optional payment-date window params for reports).
- **Excel-like Cost Grid per budget** — `budgets/{budget}/cost-grid` ("Cost Grid" button on the budget page): sections → lines exactly like the spreadsheet, section Sub-Totals with % of budget, columns Budgeted | Contracted | Paid | % Complete | Balance, summary cards, grand totals. % Complete is weighted by scheduled value across contracts.

**Compatibility:** all four existing `ContractPayment::create` paths (contract page, bulk Contract Payments, Payment Batches ×2) still work unchanged — itemless payments simply land in the default code. Existing contracts/payments need no backfill.

## 3. Expense Report — "By Cost Code" tab reworked

The provisional committed-only view now combines **expenses + subcontractor contracts** per cost code: Line Items | Expenses | Contracted | Contract Paid | Total Committed (screen, CSV, and PDF). Contracted is the full allocated value of non-cancelled contracts started by the end of the range; Contract Paid counts payments dated inside the range; contracts are omitted when a vendor/category/status filter is applied. This closes the "pending rework" flagged in `docs/expense-report.md` since June.

## 4. Payment Details report — multi-select status filter

The Status dropdown became **checkboxes** (Pending / Overdue / Paid — any combination; none = all), carried through screen, CSV, and PDF (header shows e.g. "Paid, Overdue"). Old single-status bookmarked URLs still work. See `docs/payment-detail-report.md`.

## 5. Translations

37 new `lang/pt_BR.json` entries covering every new UI string (allocation editor, payment modal lines, cost grids, report columns/notes), plus a few pre-existing untranslated report strings (Line Items, Total Cost, All). No existing translation was changed or removed (the file was re-serialized; 4 harmless duplicate lines from before collapsed).

## 6. Demo data (local dev database only)

Seeded for hands-on testing, all named "DEMO …": client *DEMO Client - Cost Codes* (13), project *DEMO Valinhos Hyper* (27) with budget 14 (spreadsheet-style codes, default = 21.1 Equipe de engenharia), two DEMO subcontractors, and contracts 34 (fully allocated + coded CO + itemized payments), 35 (partial allocation + legacy payment → default rollup), 36 (uncoded). **Not part of the deploy** — it exists only in the local database; delete the DEMO client tree + DEMO subcontractors whenever it's no longer wanted.

## Verification

Every phase was verified before moving on: transaction-rolled-back tinker test scripts with hand-computed expected values (27 + 22 + 27 + 24 + 12 + 8 assertions across the phases), plus live HTTP checks driving the real pages (login session, Livewire `/livewire/update` calls for the payment modal, PDF content inspection). All figures tied out to the cent.
