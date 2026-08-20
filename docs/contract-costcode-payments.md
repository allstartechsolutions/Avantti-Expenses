# Contract Cost Codes & Progress Payments — Design

**Status:** Approved design, implementation in phases (see bottom).
**Decided with user on 2026-08-10.** Reference spreadsheet: `docs/examples/ORCAMENTO DM - VALINHOS HYPER.xlsx`.

## Goal

Bring contracts into the cost-code system and support progress-billing (medição) style payments:

1. A contract's amount is **split (allocated) across cost codes** of the project/lot budget — a schedule of values.
2. A contract payment can **cover several cost codes**; each payment line records the code, the amount, and the **cumulative % complete** for that code. The % **drives** the suggested amount (`Δ% × allocated amount`), overridable.
3. **Excel-like grids** at two levels:
   - **Per contract:** schedule of values — allocated, paid, % complete, balance per code.
   - **Per project/lot:** all cost codes of the budget (grouped 2-level like the Excel, with section sub-totals and % of total), columns: Budgeted | Contracted | Paid | % Complete | Balance.
4. Change orders may carry an **optional own cost code** (defaults to the contract's allocation when empty).
5. **No material/labor split** — single amount per line (kept schema-simple; can be added later as additive columns).
6. **Allocation is optional; uncoded amounts roll into the budget's default cost code.** Each budget can mark one item as default (star toggle on the budget page, `budget_items.is_default`). Resolution happens at **display/report time** — nothing is stored against the default, so it's retroactive for all existing data and re-buckets automatically if the default changes. With no default marked, uncoded amounts show as an "Unassigned" row.

## Decisions log

| Question | Decision |
|---|---|
| One code or split per contract | **Split** via allocation table |
| Payment ↔ codes | **One payment, several codes** (payment line items) |
| % of job done | **Drives suggested amount** (Δ% × allocated), overridable |
| Material vs labor columns | **No** — single amount per line |
| Grid location | **Both** per-contract and per-project/lot |
| Change orders | **Optional own cost code** |
| Uncoded contract/payment amounts | **Roll into a per-budget default item** (`is_default` star), resolved at display time |
| Employee delete (unrelated, same session) | Admin-only |

## Schema (all additive — production safe)

### `contract_budget_allocations`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `contract_id` | FK contracts, cascadeOnDelete | |
| `budget_item_id` | FK budget_items, nullOnDelete | same pattern as `expense_items` |
| `amount` | unsignedBigInteger | cents, same accessor pattern as Contract |
| timestamps | | |

Unique `(contract_id, budget_item_id)`. Sum of allocations should equal the contract amount; UI shows an "Unallocated" remainder row when it doesn't (existing contracts have no allocations → fully unallocated, everything keeps working).

### `contract_payment_items`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `contract_payment_id` | FK contract_payments, cascadeOnDelete | |
| `budget_item_id` | FK budget_items, nullOnDelete | |
| `amount` | unsignedBigInteger | cents |
| `percent_complete` | decimal(5,2) nullable | **cumulative** % for that code as of this payment |
| timestamps | | |

A payment **without** items behaves exactly as today (unallocated). All 4 existing creation paths (ContractShow, ContractPayments bulk, PaymentBatchEdit ×2) stay valid; the itemized entry UI is added where it makes sense (ContractShow first).

### `budget_items.is_default`
Boolean, default false. At most one per budget (enforced by the toggle action in BudgetShow, which clears the previous default). `Budget::defaultItem()` returns it.

### `contract_change_orders.budget_item_id`
Nullable FK, nullOnDelete. Empty = follows the contract's allocation. A CO with a code increases that code's scheduled value in the grids (base allocation + CO adjustments).

## Calculation rules

- **Scheduled value per code** = allocation amount + change orders assigned to that code. COs without a code are shown on the "Unallocated" row (they raise the contract total but no specific line).
- **Paid per code** = sum of payment item amounts for that code. Payment amounts not covered by items count as unallocated paid.
- **% complete per code** = the latest (max payment_date, then id) `percent_complete` recorded for that code — cumulative, not summed.
- **Suggested payment amount** when entering a line = `(new % − previous %) × scheduled value / 100`, editable before saving.
- **Validation:** payment item amounts must sum to the payment amount; codes must belong to the project/lot budget. `payment_items ⊆` contract's allocated codes + CO codes (soft rule — warn, allow, since real life is messy).

## Graceful degradation (commercial product)

- No budget configured for the project/lot → allocation/step and payment items UI hidden; contracts work exactly as today.
- Existing data untouched: no backfill needed; grids show "Unallocated" buckets.

## Implementation phases (one at a time, tested before the next)

1. **Migrations + models** — ✅ done 2026-08-10. 4 migrations (allocations, payment items, CO `budget_item_id`, `budget_items.is_default`); `ContractBudgetAllocation`, `ContractPaymentItem` models; relations; `Contract::costCodeSchedule()`; default-item star toggle in BudgetShow.
2. **Contract create/edit: allocation editor** — ✅ done 2026-08-10. Shared trait `ManagesContractAllocations` + partial `livewire/contract/partials/allocation-editor.blade.php` in ContractCreate/ContractEdit: searchable code picker, live Allocated/Remaining totals, over-allocation blocked, rows cleared on location change, editor hidden when no budget. Note: trait uses plain methods (`allocatedTotal()`), not `getXProperty` computed props — Livewire memoizes those per request, which made validation read stale totals.
3. **Contract show: schedule-of-values grid** — ✅ done 2026-08-10. "Cost Codes" card on ContractShow (code | scheduled | paid | % complete with progress bar | balance + totals footer), hidden until the contract has any cost coding (allocations, coded COs, or itemized payments). Pending from this phase: cost-code field on the change-order create form (moved to phase 4 scope).
4. **Payment entry with items** — ✅ done 2026-08-10. Record Payment modal on ContractShow shows one line per cost code in the contract's schedule (scheduled, paid-so-far, prior %): entering a new cumulative % suggests the amount (Δ% × scheduled, editable); line amounts auto-sum into the payment amount; mismatch between lines and total is blocked; empty lines = legacy uncoded payment. Also: optional Cost Code select on the change-order modal (validated against the location's budget) + code chip in the CO list. Other payment paths (bulk ContractPayments / PaymentBatch) still create itemless payments — their amounts land in the default cost code via the display-time fallback.
5. **Project/lot cost-code grid** — ✅ done 2026-08-10, rebuilt 2026-08-19 on `CostCodeLedger`. Full-page `BudgetCostGrid` at `budgets/{budget}/cost-grid` ("Cost Grid" button on the budget page). Excel-like: sections → lines, section Sub-Totals with "% of budget", Unassigned row only when no default is set, summary cards + grand totals. Columns are now Original | Changes | Revised | Committed | Actual | Projected | Remaining | Used — contracts, purchase orders, expenses and approved change orders together, not contracts alone. `Budget::costCodeGrid()` was retired in the rebuild; see `docs/expense-changeorder-costcode-plan.md` §10.
6. **Expense Report "By Cost Code" rework** — ✅ done 2026-08-10. Columns: Line Items | Expenses | Contracted | Contract Paid | Total Committed (screen + CSV + PDF). Contracts (non-cancelled, start ≤ range end, location filters) folded in via `costCodeSchedule($paidFrom, $paidTo)` — new optional payment-date window params, existing callers unchanged. Contracts omitted when vendor/category/status filters apply (`includesContracts()`).

**Project complete** — all six phases shipped 2026-08-10 (phases 1–2 design decisions from the July 16 backlog item; reference Excel from user in docs/examples/).

## Terminology note
This install remaps "Project" → "Job Site" and job sites → "Lots" in `lang/en.json` (see docs/payment-schedule.md).
