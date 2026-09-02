# Cost Codes on Expenses and Change Orders — Design Proposal

**Status:** design agreed 2026-08-19. **Phases 1-6 built** (§8-§13) and committed. **Phase 7 — Review and Improvements — not started; its checklist is §14.**
Deploy summary: [`changelog-2026-08-20-costcodes-changeorders.md`](./changelog-2026-08-20-costcodes-changeorders.md).
**Date:** 2026-08-19

---

## 1. What already exists (read before designing)

### Expenses — cost codes are already there
- `expense_items.budget_item_id` exists and is set per **line item** (not per expense header).
- `ExpenseCreate` has a cost code search/picker per line, and on save any line without a
  code is auto-assigned to Miscellaneous (`BudgetService::MISCELLANEOUS_CODE = '999999'`).
- The cost code is displayed on the expense list expander, the view modal, and
  `ExpenseReportService::byCostCode()`.

**So the request "cost code on the expense" is 80% done.** The real gaps are:

| Gap | Impact |
|-----|--------|
| **No expense edit exists at all** (`ProjectExpenses` only views/pays/deletes; there is no `ExpenseEdit`) | A wrong cost code is permanent — delete and re-enter is the only fix |
| `ExpenseChangeHistory` model exists but nothing writes to it | Audit trail promised by the schema, never delivered |
| Expense actuals never reach the budget screens | `Budget::costCodeGrid()` aggregates **contracts only**. The budget page cannot answer "how much have we spent on 03000?" |
| No cost code column/filter on the expense list itself (only inside the row expander) | Cannot scan or filter expenses by code |
| `BudgetItem::find()` called inside a Blade loop (`project-expenses.blade.php:312`, `expense-modal.blade.php:63`) | N+1 on every expense list render |
| Two competing "catch-all bucket" mechanisms: `BudgetService` code `999999` vs `budget_items.is_default` | Contracts fall back to `is_default`, expenses fall back to `999999`. Same budget, two different buckets |

### Change orders — no cost code, and they currently mean *revenue*
There are two unrelated things called "change order" in this codebase:

| | `contract_change_orders` | `change_orders` (project / job site) |
|---|---|---|
| Attached to | a subcontract | a project or a job site |
| Cost code | **yes** — `budget_item_id` already | **no** |
| Meaning | raises/lowers what we owe the sub (cost) | raises/lowers **what we bill the client** |
| Feeds | `Contract::costCodeSchedule()` → the budget cost grid | `Project::getAdjustedContractValue()` = contract value + Σ CO, and the job-site row `job_amount + Σ CO` in the financial reports (**since 1 Sep 2026 Σ CO is the *approved* ones only** — see `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`) |

`change_orders.amount` is signed (a deductive CO is negative — already supported).
It has **no status**: a change order is effective the moment it is saved.

**The important consequence:** today a project change order moves the *revenue* line only.
Nothing about it touches the cost budget of any cost code. Adding a cost code to it is not a
field — it is a decision about what a change order *means*.

---

## 2. The design question to settle first

A real change order usually has two sides with **different amounts**:

- **Revenue side** — what the client will now pay us (this is today's `amount`).
- **Cost side** — how much more/less it will cost us to do it, split across the cost codes
  the change actually touches.

The difference between them is the margin on the change. Example:

```
CO #7 "Add 40m of retaining wall"
  Revenue (billed to client)      +  R$ 48,000.00
  Cost budget impact
    03000 Concrete                +  R$ 21,000.00
    04000 Masonry                 +  R$  9,500.00
    01500 Equipment rental        +  R$  3,000.00
    07000 Waterproofing           -  R$  1,200.00   (deleted from original scope)
                                  ----------------
    Cost total                    +  R$ 32,300.00
  Margin on this change              R$ 15,700.00 (32.7%)
```

Three ways to model this:

| Option | Shape | Trade-off |
|--------|-------|-----------|
| **A — two sides, many cost lines** (recommended) | keep `amount` = revenue; add `change_order_items` (cost code + signed amount) | Correct for construction, shows margin per change, one CO can move several codes. Most work. |
| **B — one amount, one cost code** | add `budget_item_id` to `change_orders`, revenue and cost budget move by the same amount | Cheapest. Wrong whenever a change touches more than one trade, and it silently claims every change is sold at zero margin. |
| **C — CO stays revenue-only, separate "budget revision" record** | new entity for internal budget moves | Cleanest separation, but the user then keys the same change twice. |

**Chosen: A**, with B available as a shortcut — if the user assigns a single cost
code and clicks "same as change order amount", one line is created for them. Option A also
covers the *internal* case (a budget transfer with revenue impact = 0, e.g. moving
R$ 5,000 from Contingency to Concrete: two lines, +5,000 / -5,000, revenue 0).

---

## 3. Proposed data model

### New: `change_order_items`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `change_order_id` | FK → change_orders, cascade delete | |
| `budget_item_id` | FK → budget_items, nullable, nullOnDelete | null = falls into the default bucket |
| `description` | string, nullable | what changed on this code |
| `amount` | **signed** bigint (cents) | + increases the code's budget, − decreases it |
| `sort_order` | int | |
| timestamps | | |

Index: `change_order_id`, `budget_item_id`.

### Changed: `change_orders`
| Column | Type | Notes |
|--------|------|-------|
| `status` | enum `draft` / `pending` / `approved` / `rejected`, default `approved` | **existing rows backfill to `approved` so no current number moves** |
| `approved_at`, `approved_by` | nullable | audit |
| `co_number` | string, nullable | per-project sequential ("CO-007") — construction users expect it |

`amount` keeps its exact current meaning (revenue / contract value impact) so every existing
report keeps working untouched.

### Backfill
Existing change orders get **no** items. They show as "cost impact not allocated" in the new
grid, with a one-click "allocate now" action. No guessing on production data.

### Rules
1. Only `approved` change orders revise the budget.
2. Revenue rollups (`getAdjustedContractValue`, the financial reports) keep summing `amount`
   for **every** change order regardless of status — exactly as today, so no live project
   number moves when this ships. Status gates the **cost** side only.
   **Superseded 1 Sep 2026:** status now gates the revenue side too — only an approved change
   order reaches the contract value. See
   `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.
3. A cost line may point at any code in that location's budget; a CO on a job site can only
   use that job site's budget codes; a project-level CO uses the project budget codes.
4. Cost lines are **not** required to add up to the revenue amount — the gap is the margin,
   and it is displayed, never enforced.

---

## 4. Derived figures per cost code (the point of all this)

One service (`BudgetService` extended, or a new `CostCodeLedger`) returns per budget item:

| Figure | Source |
|--------|--------|
| Original budget | `budget_items.budgeted_amount` |
| Approved changes | Σ `change_order_items.amount` for approved COs |
| **Revised budget** | original + approved changes |
| Committed | contract allocations + contract COs + open POs not yet expensed |
| Actual | Σ `expense_items.total_amount` + contract payments |
| Remaining | revised − max(committed, actual) |
| % used | actual / revised |
| Over/under | flagged in red when actual or committed > revised |

Parent codes roll their children up; a bottom "Unassigned / Miscellaneous" row catches
everything with no code, and it is clickable so the user can fix the strays.

---

## 5. Screens

1. **Budget show / cost grid** (merge the two existing pages into one grid)
   Columns: Code · Name · Original · Changes · Revised · Committed · Actual · Remaining · % ·
   progress bar. Section subtotals and a grand total. Click any figure → drill-down.
2. **Cost code drill-down** — one code, everything behind it: budget history, every change
   order line, contracts and POs committed to it, every expense line, running balance.
3. **Change order modal** → full-page modal (per the design standard):
   sticky header (CO number, project/job site, status), body = details + a **cost impact line
   editor** (cost code picker like the contract allocation editor, "distribute remainder",
   "match revenue amount", "add all codes"), live footer showing Revenue / Cost / Margin /
   unallocated remainder. Approve/reject actions with the audit stamp.
4. **Change order list** — status column, cost-impact column, filter by status and by cost code.
5. **Expense edit** (new component) — full-page modal, edits header + lines including cost
   codes, writes `ExpenseChangeHistory` for every changed field. Paid expenses editable by
   admins only (matches the existing rule for paid expenses).
6. **Expense list** — cost code column + filter, and on the cost code picker show live context:
   `03000 Concrete — revised 50,000 · spent 42,000 · this expense 3,000 → remaining 5,000`.
7. Both themes, both locales, `__()` + pt_BR added in the same change, mobile without
   horizontal scroll.

---

## 6. Phasing (one deliverable at a time, tested before moving on)

| Phase | Scope |
|-------|-------|
| 1 | Migrations + models + `CostCodeLedger` service + unify the default/miscellaneous bucket. No UI. |
| 2 | Change order cost lines UI (create/edit/view, approval, margin footer) |
| 3 | Budget grid rebuilt with Original / Changes / Revised / Committed / Actual / Remaining |
| 4 | Cost code drill-down page |
| 5 | Expense edit + change history + cost code column/filter + fix the N+1s |
| 6 | Reports & PDFs (expense report, project/job-site financial report) pick up revised budget |
| 7 | **Review and Improvements** — full module review, both themes/locales, pt_BR, docs |

---

## 7. Decisions (settled 2026-08-19)

1. **Revenue vs cost — Option A.** A change order carries both sides with independent
   amounts: `amount` stays the revenue billed to the client, `change_order_items` carry the
   cost impact per cost code. The difference is the margin, shown live, never enforced.
2. **Multiple codes per CO — yes.** Many cost lines per change order. A single-code change
   uses the same editor with one line plus a "match revenue amount" shortcut.
3. **Approval — yes, cost side only.** `draft` / `pending` / `approved` / `rejected`. Only
   `approved` change orders revise the cost budget. The client contract value keeps counting
   every change order as it does today, so **no existing project total moves**. Existing rows
   backfill to `approved`.
   **Superseded 1 Sep 2026:** approval gates the revenue side too — only an approved change
   order reaches the contract value. Still no historical total moved, because of that same
   backfill. See `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.
4. **Paid expenses — admins only, logged.** The cost code on a paid expense is editable by
   admins, matching the existing rule for editing paid expenses, and every change writes to
   `ExpenseChangeHistory` (the model exists today and is never written to).
5. **Default bucket — `budget_items.is_default`.** The hardcoded `999999` Miscellaneous code
   is retired. Migration work this implies:
   - every existing budget gets exactly one `is_default` item; where a `999999` item already
     exists it becomes the default rather than a second bucket being created;
   - `BudgetService::getMiscellaneousItem()` / `ensureBudgetItem()` are rewritten to resolve
     `Budget::defaultItem()`, creating it once if absent;
   - `MISCELLANEOUS_CODE` / `MISCELLANEOUS_NAME` constants are removed only after every
     caller is moved (`ExpenseCreate::save()` is the live one);
   - existing expense lines pointing at a `999999` item need no change — that item *is* the
     default after the migration.


---

## 8. Phase 1 — built 2026-08-19

Data model, models and the ledger service. No UI: nothing on any screen changes yet, and no
existing figure moves.

### Migrations (run with `php artisan migrate`)

| Migration | Does |
|---|---|
| `2026_08_19_180000_create_change_order_items_table` | The cost side of a change order: `change_order_id`, nullable `budget_item_id`, `description`, **signed** `amount` in cents, `sort_order` |
| `2026_08_19_180001_add_status_to_change_orders_table` | `status` (draft/pending/approved/rejected, **default approved**), `approved_at`, `approved_by`, `co_number` |
| `2026_08_19_180002_standardise_default_budget_item` | One catch-all per budget: keeps the oldest `is_default` item where there were several, and adopts a legacy `999999` item as the default where there was none. Creates nothing, moves no transaction, and is deliberately not reversible |

Existing change orders take the `approved` default — they were effective the moment they were
saved, which is what the system did until now. `approved_at` / `approved_by` stay null on them:
nobody ever approved them, and a false audit trail is worse than a blank one.

### Models

- **`ChangeOrderItem`** — one cost code's share of a change order. Signed cents accessor,
  `cost_code_display`, `isDeductive()`.
- **`ChangeOrder`** — status constants, `approved()` / `pendingDecision()` scopes,
  `approve()` / `reject()` / `returnToPending()`, and the money the screens need:
  `cost_impact`, `margin`, `margin_percent`, `hasCostLines()`, `resolveBudget()`.
- **`BudgetItem`** — `expenseItems()`, `purchaseOrderItems()`, `changeOrderItems()`,
  `contractAllocations()`, `contractChangeOrders()`.
- **`Budget`** — `ensureDefaultItem()` (adopts a legacy `999999` bucket rather than creating a
  second one) and `setDefaultItem()` (exactly one flag per budget, in a transaction).

### `BudgetService`

`MISCELLANEOUS_CODE` / `MISCELLANEOUS_NAME` and `getMiscellaneousItem()` are gone. The
catch-all is resolved by the `is_default` flag through `getDefaultItem()`, so contracts,
expenses, purchase orders and change orders all fall into the same bucket. Five callers moved
across: `ExpenseCreate`, `PurchaseOrderCreate`, `PurchaseOrderEdit`, `ProjectShow`,
`ManagesQuotations`.

### `CostCodeLedger` — the service everything else reads from

```php
$ledger = CostCodeLedger::for($budget);
$ledger->rows();            // flat list, parents then children, unassigned last
$ledger->grid();            // sections + subtotals + totals, ready for a table
$ledger->forItem($item);    // one cost code
$ledger->totals();          // budget-wide
```

Each row carries `original`, `changes`, `revised`, `committed_contracts`, `committed_pos`,
`committed`, `actual_expenses`, `actual_payments`, `actual`, `projected`, `remaining`,
`percent_spent`, `percent_committed`, `over_budget`.

Where the figures come from, and the double counting that is deliberately avoided:

| Figure | Source | Note |
|---|---|---|
| Changes | `change_order_items` of **approved** change orders at this location | draft / pending / rejected do not move the budget |
| Committed — contracts | `Contract::costCodeSchedule()` per committed contract | includes the contract's own change orders and its default-code fallback |
| Committed — POs | purchase orders with status `pending` | an **approved** PO is excluded: approving it writes the expense, which is already counted as actual |
| Actual — expenses | every `expense_items` row at this location | an expense is a cost when recorded, not when paid |
| Actual — payments | contract payments | a subset of the contract's scheduled value |
| **Projected** | contracts scheduled + pending POs + expenses | contract payments left out — they are already inside the scheduled value |
| Remaining | revised − projected | |

Everything is aggregated in cents and converted once on the way out. A pending purchase
order's header freight, tax and discount are apportioned across its cost codes by largest
remainder, so the commitment matches the order total to the cent — the same treatment approval
gives them when it writes the expense. Anything with no cost code, or one belonging to another
budget, falls into the budget's default bucket, and into an "Unassigned" row only when the
budget has no default at all.

### Verified against the local database (project 27, budget 14)

- A **pending** change order leaves every figure untouched; approving it moves `changes` and
  `revised` on each coded line (+21,000 / −1,200) and drops its uncoded line into the default
  bucket (+3,000); rejecting it puts everything back.
- Margin: revenue 48,000 − cost 22,800 = 25,200 (52.5%).
- An unpaid expense of 2,500 counts as actual and cuts `remaining` on its code from 7,200 to
  4,700; an expense with no cost code lands in the default bucket.
- A pending PO of 1,000 + 100 freight commits 1,100; split across two codes with an odd total
  of 2,101 it apportions to 1,050.50 + 1,050.50 — no cent lost.
- Section subtotals add up to the grand total.
- **The client contract value is unchanged**: draft, pending, rejected and approved change
  orders all still count toward `Project::getAdjustedContractValue()`, and a change order
  created without a status is `approved`, exactly as before.
  **Superseded 1 Sep 2026:** only approved change orders count toward it now. See `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.

### Not in phase 1

No screen reads the ledger yet — the budget pages still show `Budget::costCodeGrid()`, which
knows only about contracts. That is phase 3.


---

## 9. Phase 2 — built 2026-08-19

The change order editor, at every level, with the cost lines the budget will read in phase 3.

### One editor, three screens

`app/Livewire/Concerns/ManagesChangeOrders.php` holds the whole thing — state, cost lines,
validation, approval, list query and summary — and three components use it:

| Screen | Component | Location |
|---|---|---|
| Project → Change Orders | `ProjectChangeOrders` | user picks: project-level or any of its job sites |
| Job site → Change Orders | `JobSiteShow` | pinned to that job site |
| Project → `/show` (legacy tabbed page) | `ProjectShow` | user picks |

Before this there were **three separate copies** of the change order form, each drifting from
the others. The blade is shared too — `resources/views/livewire/change-order/partials/`:
`form-modal` (full page), `list` (the table), `summary-cards` (the figures above it). A host
supplies `changeOrderProjectId()`, optionally `changeOrderPinnedJobSiteId()`, and
`afterChangeOrderSaved()`.

### The form

Full-page modal: sticky header (number, status badge, location), a `max-w-7xl` body in two
columns, sticky footer that says whether the budget is being moved.

- **Left — the change**: number (pre-filled with the next in the project's series), title,
  requested date, status, location, description, file.
- **Right — the money**: what the client is billed, then the cost lines. Each line is a cost
  code from that location's budget, with its own note and a signed amount. Shortcuts:
  **Remainder** puts the rest of the billed amount on one line, **Split evenly** divides it
  across every line to the cent, **Clear all** empties them.
- **Running totals**: Billed · Cost · Margin · Margin %, live as you type, with a sentence
  underneath saying which way the gap runs — "the cost lines are 15,700 short of the amount
  billed", or "exceed it by 1,200 — this change is being done at a loss".

Change the location and the cost lines clear themselves: they belonged to the other
location's budget. A location with no budget says so in place of the picker, and the change
order can still be saved — it bills the client and touches no cost code.

### Status

`draft` → `pending` → `approved` / `rejected`, set from the form or from the list (a tick
approves in one click; the view screen also offers Reject and Return to Pending, both behind
a confirmation because they pull the money back out of the budget). New change orders default
to **pending**: the cost lines are recorded and reach the budget when someone approves them.
Approving stamps `approved_by` and `approved_at`.

The client contract value still counts every change order, whatever its status — unchanged
from before this work. **Superseded 1 Sep 2026:** only approved change orders count. See
`docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.

### The list

Change order (number, title, date, cost code count, file) · Location · Status · Billed · Cost ·
Margin · actions. Filters for search, location and status. Above it, four figures: billed to
the client, approved cost impact, margin on approved, and how many are awaiting a decision —
plus a warning strip counting change orders that bill the client with no cost breakdown, since
those are invisible to every cost code budget.

Empty states are written for both cases: nothing recorded at all, and nothing matching the
current filters.

### Verified

A temporary Livewire test drove all three screens against the dev database and was removed
afterwards; every row it wrote was rolled back (the change order tables are back to empty).
It covered: create with two cost lines left pending → the ledger does not move; approve →
`changes` moves +21,000 / −1,200 on the two codes; margin 58.75%; the Remainder shortcut;
even split of 100.01 across two lines adding back to 100.01; a zero-amount line refused;
Return to Pending taking the budget change back out; delete removing the lines with it;
the job-site screen pinning its location and refusing a change order from another project;
the legacy tabbed page saving through the same editor; and the change orders tab rendering
its cards and table.

### Not in phase 2

The budget screens still show `Budget::costCodeGrid()` (contracts only), so an approved
change order's effect is visible through `CostCodeLedger` but not yet on any budget page.
That is phase 3.


---

## 10. Phase 3 — built 2026-08-19

The budget screens now read `CostCodeLedger`. Every figure a cost code carries is on screen,
and the contracts-only grid is gone.

### Columns

The cost grid (`budgets/{budget}/cost-grid`) went from
*Budgeted | Contracted | Paid | % Complete | Balance* to:

| Column | What it is |
|---|---|
| Original | what the cost code was budgeted |
| Changes | approved change orders, signed. Draft, pending and rejected are not here |
| Revised | original + changes — the budget in force |
| Committed | subcontracts and their change orders, plus purchase orders awaiting approval |
| Actual | expenses recorded and contract payments made |
| Projected | committed + expenses — contract payments are already inside the contract value |
| Remaining | revised − projected |
| Used | a two-tone bar: the solid part is spent, the pale part is committed-not-yet-spent |

Hovering Committed or Actual shows the breakdown behind it (contracts, pending purchase
orders, expenses, contract payments). A footnote under the table states the three rules that
are easy to get wrong, in the user's own words rather than as accounting jargon.

### Screens

- **Cost grid** — six summary cards (original, changes, revised, committed, actual, remaining
  with % used), a red strip when projected cost passes the revised budget, sections with
  subtotals and "% of budget", an amber Unassigned row that says how to make it go away, and
  an empty state that offers to add cost codes.
- **Budget page** (`budgets/{budget}`) — the headline card now leads with the **revised**
  budget and what is left, with a progress bar and committed / actual / projected underneath.
  Every cost code row shows its revised amount, its change (signed, coloured), a bar and
  "X left" / "X over" instead of the budgeted figure alone; the full breakdown is on hover.
  An amber row appears when anything landed with no cost code.
- **Project budget page** — a project-wide roll-up card across every budget on the project,
  and the same figures on each budget row.
- **Job site budget tab** — the same block, plus the ledger figures on its cost code summary.
  The ledger is only built when that tab is showing, since it walks every contract, expense
  and purchase order for the location.

### Retired

`Budget::costCodeGrid()` is **deleted**. It knew only about contracts and would have been a
second, quieter answer to the same question. `CostCodeLedger` is now the only source for
budget-versus-actual anywhere.

### Verified

A temporary test (removed afterwards, all rows rolled back) put an approved change order of
+3,000 and an expense of 2,500 on cost code 5.5, then checked the screens: revised 10,200 on
that code, 2,500 actual, 7,700 remaining; the cost grid, the budget page and the project
budget page all render those same figures; returning the change order to pending drops the
code back to 7,200 revised and 4,700 remaining. pt_BR was checked at runtime for every new
parameterised string and both plural forms.

### Not in phase 3

Clicking a figure does not yet open what is behind it — the cost code drill-down is phase 4.


---

## 11. Phase 4 — built 2026-08-19

One cost code, everything behind its figures.

### The page

`App\Livewire\Budget\CostCodeDetail`, at two routes:

| Route | Serves |
|---|---|
| `budgets/{budget}/cost-codes/{budgetItem}` | one cost code |
| `budgets/{budget}/unassigned-costs` | the catch-all bucket, when the budget has no default code |

Reached by clicking the code's name anywhere it appears — the cost grid (sections, lines and
the Unassigned row), the budget page, and the job site budget tab.

Header carries the code, its parent as a breadcrumb, the location, the description and the
Default badge. Then the same headline card as the budget page — revised, remaining, % used,
committed / actual / projected / expenses — for this code alone. A parent code also lists the
codes under it with their own revised, actual and remaining, each linking on.

### The sections

| Section | Shows | When empty |
|---|---|---|
| Change orders | every approved line with its number, title, date, the line's own note and signed amount — **followed by draft and pending ones**, struck through and labelled "Not approved — not in the budget above" | "No change order has touched this cost code." |
| Contracts | each subcontract carrying money here: number, subcontractor, % complete, scheduled, paid, balance | "No contract carries money on this cost code." |
| Purchase orders awaiting approval | only when there are any; says "part of a :total order" when the order is split across codes | hidden |
| Expenses | every line: date, item with quantity × unit price, supplier, paid/unpaid, amount. A line with no code of its own is marked as such | "Nothing has been spent against this cost code yet." |
| Contract payments | only when there are any, with the note that they are already inside the contract figures above | hidden |
| Record | sort order, created, last updated | — |

The bucket rules match the totals exactly: a record with no cost code, or one belonging to
another budget, is listed under this budget's **default** code, which is why that code's page
shows more than its own name would suggest. `CostCodeLedger::transactionsFor()` owns that
logic, next to the aggregation it has to agree with.

### Verified

A temporary test (removed; every row rolled back) put an approved change order (+3,000 with a
line note), a pending one (+700) and a 2,500 expense on code 5.5, then checked the page shows
the approved change order, the pending one marked as excluded, the expense line, and revised
10,200 / spent 2,500 / remaining 7,700 — with the ledger confirming the pending 700 stayed
out. Also covered: both routes over real HTTP, a missing id 404ing, a parent code listing its
children, the unassigned page, and a cost code from another budget refused with a 404 whether
passed as a model or an id. pt_BR checked at runtime for the parameterised strings.

### Not in phase 4

Editing an expense — including its cost code — is still impossible anywhere in the app. That
is phase 5, and it is the last big gap in the chain.


---

## 12. Phase 5 — built 2026-08-20

Expenses can be corrected, and the correction is on the record.

### One editor, three screens (again)

What §1 recorded as "no expense edit exists" was only true of the project page. `JobSiteShow`
and the legacy `ProjectShow` each carried a modal that edited the **pre-items** header fields
(`item_name`, `quantity`, `unit_price`) and had no cost code at all — wrong for every expense
written since expenses gained line items.

All three now open `App\Livewire\Expense\ExpenseEdit` at `expenses/{expense}/edit`.
`ExpenseCreate` and `ExpenseEdit` share `app/Livewire/Concerns/ManagesExpenseForm` and the
blade partials `livewire/expense/partials/form-body` and `item-modal`, so the create and edit
screens cannot drift. `ExpenseCreate` went from 435 lines to 84.

### What may be changed, and by whom

| Situation | Rule |
|---|---|
| Unpaid, single payment | anyone who can reach the screen edits everything |
| Paid, or an installment paid | **administrators only** (`AuthorizesAdmin`) |
| An installment has been paid | amounts and payment terms are **locked**; cost codes, names, supplier, date and notes still editable |
| Created from a purchase order | same lock, because the expense mirrors the order |

Locked screens say which of the two reasons applies, in a strip at the top. The amount inputs
are disabled and the "add line" and "remove line" actions disappear; the server enforces it
again on save, so a hand-crafted request cannot get round the disabled attribute.

### History

`Expense::updateWithHistory()` already existed and diffed the header. It gained an optional
second argument, so the line-level diff computed by `ExpenseEdit` is folded into the **same**
entry — one history row per save, reading like:

```
Edited                                       Ana Paula · 20 Aug 2026 14:32
  notes:                    — → Recoded to sand
  Line 1 — cost_code:       5.4 - Cimento → 5.5 - Areia Media / Fina / Lavada
```

The edit screen shows the whole history underneath the form: every payment, reversal, due-date
change and edit, with who and when.

### Cost codes on the lists

Both expense lists gained a **Cost Codes** column (chips, "+2" when a expense spans more) and
an **All Cost Codes** filter that matches by code across the location's budgets. The three
`BudgetItem::find()` calls inside Blade loops are gone — the code label now travels on the line
state, so a list of 200 expenses costs no extra queries.

### Verified

A temporary test (removed; every row rolled back — expenses, items, history and purchase orders
are all back to their pre-test counts) covered: creating through the shared form still writes
the right total and cost code; editing moves an expense's money from code 5.4 to 5.5 in the
ledger and writes one `edited` history row containing both the note change and the
`Line 1 — cost_code` change with the right user; a paid expense is 403 for a non-admin and
opens for an admin; the cost code filter narrows the list to the right expense on both codes;
the job site tab renders the column and filter; and a purchase-order expense refuses a quantity
and price change while still accepting the recode. The real create and edit routes were both
requested over HTTP.

### Not in phase 5

The reports and PDFs still show expenses without the revised-budget context — phase 6.


---

## 13. Phase 6 — built 2026-08-20

The reports and the PDFs learned about cost codes.

### Budget by Cost Code, on four surfaces

One section, two renderings — `livewire/shared/cost-code-section` for the screens and
`pdf/partials/cost-code-section` for print — added to:

- the **project financial report** (screen + PDF), grouped by location with a sub-total per
  budget and a project-wide total;
- the **job site financial report** (screen + PDF), for that job site's budget.

Columns: Original · Changes · Revised · Committed · Actual · Remaining. Codes with nothing
budgeted and nothing spent are left out, so the section stays readable on a big cost code
template. On screen every code links to its drill-down.

The section is headed with what it is: *lifetime figures, whatever the report dates say
elsewhere.* A financial report mixing period figures and lifetime figures without saying so
would be a bug, not a feature.

`CostCodeLedger` gained `forProject()` (every budget on a project, each with its grid, plus a
roll-up) and `addTotals()` (fold one totals row into another). `ProjectBudget` now uses the
shared helper instead of its own copy.

### Change orders show both sides

The Contract Value Breakdown listed only what a change order bills. Each line now also carries
its **cost** and its **margin**, on all four surfaces, and says *not approved* when the change
order has not been approved and *no cost breakdown* when nobody has costed it. A change order
selling 5,000 of work that costs 3,000 now reads as such on the report the client sees.

### A printable cost grid

`budgets/{budget}/cost-grid/pdf` (and `/pdf/view`), landscape, with the same nine columns as
the screen, the six headline figures, section sub-totals with "% of budget", the over-budget
strip and the three footnotes explaining Changes, Committed and Projected. Buttons for it sit
on the cost grid page.

### Expense report

The By Cost Code tab now says what it is — *spend in the selected period* — and every code
links through to its budget drill-down, where the figures are lifetime.
`ExpenseReportService::byCostCode()` carries `budget_item_id` and `budget_id` for the link.

### Verified

A temporary test (removed; all rows rolled back — the dev database is unchanged) covered:
the project report showing the section, the code, the revised and actual totals, and a change
order's 3,000 cost and 2,000 margin; the job site report showing its own budget; **three PDFs
rendered for real** (cost grid, project financial, job site financial — each checked to start
with `%PDF`); the expense report emitting the drill-down link and the new caption; and a job
site with no budget showing "No budget yet" rather than an empty table. pt_BR checked at
runtime for the new strings.

### Not in phase 6

The company financial report and the payment reports still know nothing about cost codes —
they are about cash, not budgets, so that was left alone deliberately. Phase 7 is the module
review.


---

## 14. Phase 7 — Review and Improvements (NOT STARTED)

The standing final phase. Written out here so it can be picked up cold in another session.
Nothing below has been done.

### 14.1 What exists, in one list

Read this first — it is what the review covers.

**Service** — `app/Services/CostCodeLedger.php` (the single source for budget-versus-actual;
`Budget::costCodeGrid()` was deleted).

**Models** — `ChangeOrderItem` (new), `ChangeOrder` (status, approval, margin), `BudgetItem`
(five reverse relations), `Budget` (`ensureDefaultItem()`, `setDefaultItem()`, `DEFAULT_ITEM_*`),
`Expense::updateWithHistory($data, $extraChanges)`.

**Concerns** — `ManagesChangeOrders`, `ManagesExpenseForm`.

**Components** — `CostCodeDetail` (new), `ExpenseEdit` (new), plus rewritten
`ProjectChangeOrders`, `BudgetCostGrid`, `BudgetShow`, `ProjectBudget`, `ExpenseCreate`,
`ProjectExpenses`, and edits to `JobSiteShow`, `ProjectShow`, `ProjectFinancialReport`,
`JobSiteFinancialReport`.

**Controllers** — `BudgetCostGridPdfController` (new), plus the two financial report PDF
controllers.

**Views** — `livewire/change-order/partials/{form-modal,list,summary-cards}`,
`livewire/budget/partials/{ledger-row-cells,code-figures,budget-figures}`,
`livewire/budget/cost-code-detail`, `livewire/expense/partials/{form-body,item-modal}`,
`livewire/expense/expense-edit`, `livewire/shared/cost-code-section`,
`pdf/partials/cost-code-section`, `pdf/budget-cost-grid`, and the rebuilt
`livewire/budget/budget-cost-grid`.

**Routes** — `budgets.cost-code`, `budgets.unassigned`, `budgets.cost-grid.pdf.download`,
`budgets.cost-grid.pdf.view`, `expenses.edit`.

### 14.2 Screens to walk

Both themes, both locales, and on a phone. Empty, partial and error states in each.

| # | Screen | Watch for |
|---|---|---|
| 1 | Project → Change Orders | the four summary cards with no change orders at all; the uncosted-CO warning strip; filters that match nothing |
| 2 | Job site → Change Orders | identical behaviour to #1 minus the location column |
| 3 | Legacy `/projects/{id}/show` → Change Orders tab | same editor, same figures |
| 4 | Change order full-page modal | a location with no budget; a change order with 20 cost lines; a negative billed amount; margin at exactly zero; **long code names on a phone** |
| 5 | Budget page | a budget with no cost codes; one with only parents; the amber Unassigned row |
| 6 | Cost grid | nine columns on a phone (scrolls in its own container — check it does not scroll the page); a code 300% over budget; every figure zero |
| 7 | Cost code drill-down | a code with nothing behind it; the unassigned page; a parent with children; a code carrying all six sections at once |
| 8 | Expense create / edit | the locked (PO / paid-installment) variant; an expense with no items; history with 20 entries |
| 9 | Project + job site budget pages | rolled-up figures against the individual budgets |
| 10 | Project + job site financial reports, screen **and** PDF | a project with no budget at all; several job-site budgets; a change order with no cost breakdown |
| 11 | Cost grid PDF | a budget with 200 codes (page breaks); landscape on A4 as well as letter |
| 12 | Expense report → By Cost Code | the drill-down links when several projects are in range |

### 14.3 The backlog to work

Every item is in [`review-and-improvements.md`](./review-and-improvements.md), in five sections
(one per phase). Grouped here by what it costs:

**Correctness — do these first**

- `change_orders.co_number` is not unique; two people can take the same number. Copy the
  quotation chain's unique index + retry-on-collision.
- Approval has no permission guard. `permissions-notes.md` §4b holds four questions for the
  owner; the new permissions module plan may answer them.
- Deleting an approved change order silently revises budgets down with no warning.
- A contract can be paid beyond what its schedule allocates (pre-existing; the ledger reports it
  faithfully, the contract module should flag it).

**Dead code and contradictions**

- The legacy expense modal in `JobSiteShow` and `ProjectShow`: create and edit branches are
  unreachable, but the same modal still serves *view* mode, so it cannot simply be deleted.
  `saveExpense()`, `openExpenseCreateModal()`, `openExpenseEditModal()` are dead.
- `Expense::isEditableBy()` now says something different from `ExpenseEdit`'s guards. Retire it
  with the modal or bring the two into line.
- `BudgetShow::toggleDefaultItem()` clears the flag by hand; setting goes through
  `Budget::setDefaultItem()`. Give the model a clear method too.
- The legacy tabbed `/projects/{id}/show` duplicates the split pages. Decide whether it retires.

**Performance**

- `ProjectBudget` and `ProjectFinancialReport` build one ledger per budget on the page, each
  walking its contracts through `Contract::costCodeSchedule()`.
- Two `BudgetItem::find()` N+1s remain in `purchase-order-create.blade.php:173` and
  `purchase-order-edit.blade.php:184`.

**Shape of the code**

- Every financial report is built twice — Livewire component and PDF controller — with the
  arithmetic copy-pasted. One shared builder would have made this module's phase 6 a single edit
  instead of four.

**Presentation**

- The cost grid needs `min-w-[1100px]`; a stacked card view would read better on a phone.
- The cost grid PDF is landscape while every other PDF is portrait.

### 14.4 Say what the code does

Per the standard: wording that promises something the code does not enforce is a bug. Check at
least these claims —

- "Only an approved change order revises the budget." (true; verify on screen)
- ~~"The client contract value counts it either way."~~ **No longer true from 1 Sep 2026** —
  only an approved change order reaches the contract value. The screens say so; verify that
  wording against the reports instead. See `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.
- "Cost codes can still be corrected." on a locked expense (true; verify the save path)
- "Lifetime figures, whatever the report dates say elsewhere." on the report section
- The three cost grid footnotes about Changes, Committed and Projected

### 14.5 Finish with

- pt_BR read end to end for this module — roughly 130 strings were added.
- `docs/budget-costcode-system.md` still describes the original 7-phase design; phases 4, 6 and 7
  of that document are superseded by this one. Bring it level or point it here.
- Update [`open-items.md`](./open-items.md) when the review closes.
