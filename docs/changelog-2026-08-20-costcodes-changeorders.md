# Changelog — Cost codes on expenses and change orders (2026-08-19 → 2026-08-20)

Six phases, built in order, each verified before the next started. The plan and the per-phase
build logs are in **[`expense-changeorder-costcode-plan.md`](./expense-changeorder-costcode-plan.md)**;
this file is the deploy-facing summary.

**Phase 7 — Review and Improvements — has not been done.** See §14 of the plan for its
checklist and §8 below for the backlog it has to work.

---

## What changed, in one paragraph

A project or job-site change order used to move only the money billed to the client. It now
carries a second side — what the change does to each cost code's budget — and an approval that
gates it. Every budget screen was rebuilt on one service that answers *Original → Changes →
Revised → Committed → Actual → Remaining* per cost code, a drill-down opens the records behind
any figure, expenses can finally be edited (so a wrong cost code can be corrected) with the
change written to history, and the financial reports and their PDFs report against the revised
budget.

---

## 1. Database

Three migrations, all additive. **Run `php artisan migrate` then `php artisan view:clear`.**

| Migration | Does |
|---|---|
| `2026_08_19_180000_create_change_order_items_table` | The cost side of a change order: `change_order_id`, nullable `budget_item_id`, `description`, **signed** `amount` (cents), `sort_order` |
| `2026_08_19_180001_add_status_to_change_orders_table` | `status` enum draft/pending/approved/rejected **default `approved`**, `approved_at`, `approved_by`, `co_number` |
| `2026_08_19_180002_standardise_default_budget_item` | One catch-all cost code per budget: keeps the oldest `is_default` where several existed, and adopts a legacy `999999` item as the default where none did. Creates nothing, moves no transaction, **not reversible** |

**Nothing on a live project moves on deploy.** Existing change orders take the `approved`
default — which is what the system did before, since they were effective the moment they were
saved — and they have no cost lines, so no cost code budget changes. `approved_at` /
`approved_by` stay null on them deliberately: nobody ever approved them, and a false audit
trail is worse than a blank one.

The client contract value still counts **every** change order regardless of status, exactly as
before. Approval gates the cost side only.

> **Superseded 1 Sep 2026.** Approval now gates *both* sides: only an approved change order
> reaches the contract value. See `docs/changelog-2026-09-01-change-order-approval-gates-revenue.md`.

---

## 2. The service everything reads from

`app/Services/CostCodeLedger.php` — the single source for budget-versus-actual.

```php
$ledger = CostCodeLedger::for($budget);
$ledger->rows();              // flat, parents then children, unassigned last
$ledger->rowsByItem();        // keyed by budget item id (0 = unassigned)
$ledger->grid();              // sections + subtotals + totals, ready for a table
$ledger->forItem($item);      // one cost code
$ledger->totals();            // budget-wide
$ledger->transactionsFor($item);  // the records behind the figures

CostCodeLedger::forProject($project);   // every budget on a project + a roll-up
CostCodeLedger::addTotals($carry, $row); // fold one totals row into another
```

Every row carries `original`, `changes`, `revised`, `committed_contracts`, `committed_pos`,
`committed`, `actual_expenses`, `actual_payments`, `actual`, `projected`, `remaining`,
`percent_spent`, `percent_committed`, `over_budget`.

| Figure | Source | The rule that is easy to get wrong |
|---|---|---|
| Changes | `change_order_items` of **approved** change orders at that location | draft / pending / rejected do not move the budget |
| Committed | contracts (through `Contract::costCodeSchedule()`) + purchase orders with status `pending` | an **approved** PO already became an expense, so it is not committed as well |
| Actual | every `expense_items` row + contract payments | an expense is a cost when recorded, not when paid |
| Projected | contracts + pending POs + expenses | contract payments are left out — they are already inside the contract's scheduled value |
| Remaining | revised − projected | |

Everything is aggregated in cents and converted once on the way out. A pending purchase order's
header freight, tax and discount are apportioned across its cost codes by largest remainder, so
the commitment matches the order total to the cent. Anything with no cost code, or one
belonging to another budget, falls into that budget's **default** item, and into an
"Unassigned" row only when no default is set.

`Budget::costCodeGrid()` — the old contracts-only grid — was **deleted**, so there is only one
answer to the question.

---

## 3. Change orders

**Models.** `ChangeOrderItem` (signed cents, `cost_code_display`, `isDeductive()`).
`ChangeOrder` gained status constants, `approved()` / `pendingDecision()` scopes, `approve()` /
`reject()` / `returnToPending()`, and `cost_impact` / `margin` / `margin_percent` /
`hasCostLines()` / `resolveBudget()`.

**One editor, three screens.** `app/Livewire/Concerns/ManagesChangeOrders.php` plus
`resources/views/livewire/change-order/partials/{form-modal,list,summary-cards}.blade.php`,
used by `ProjectChangeOrders`, `JobSiteShow` and the legacy tabbed `ProjectShow`. Before this
there were three separate copies of the form.

**The form** is a full-page modal: number pre-filled with the next in the project's series,
title, date, status, location, description, file on the left; what the client is billed and the
cost lines on the right. Shortcuts: **Remainder** puts the rest of the billed amount on one
line, **Split evenly** divides to the cent, **Clear all**. Running totals show Billed · Cost ·
Margin · Margin % live, with a sentence saying which way the gap runs. Changing the location
clears the cost lines — they belonged to the other budget. A location with no budget says so,
and the change order still saves.

**The list** gained Number, Status, Billed, Cost and Margin columns, a status filter, four
summary figures, and a strip counting change orders that bill the client with no cost
breakdown — those are invisible to every cost code budget.

---

## 4. Budget screens

- **Cost grid** (`budgets/{budget}/cost-grid`) — nine columns (Original, Changes, Revised,
  Committed, Actual, Projected, Remaining, Used), six summary cards, a red strip when projected
  cost passes the revised budget, section sub-totals with "% of budget", an amber Unassigned row
  that says how to make it go away, and three footnotes stating the rules above in plain words.
  Hovering Committed or Actual shows the breakdown behind the number.
- **Budget page** — leads with the **revised** budget and what is left; each cost code shows its
  revised amount, its signed change, a two-tone bar (solid = spent, pale = committed) and
  "X left" / "X over".
- **Project budget page** — a project-wide roll-up plus the same figures per budget.
- **Job site budget tab** — the same block; the ledger is only built when that tab is showing.

## 5. Cost code drill-down

`App\Livewire\Budget\CostCodeDetail` at `budgets/{budget}/cost-codes/{budgetItem}` and
`budgets/{budget}/unassigned-costs`, reached by clicking a code's name anywhere it appears.

Sections: change orders (approved, then draft/pending struck through and labelled *not in the
budget above*), contracts, purchase orders awaiting approval, expenses, contract payments, and
the record's own audit facts. A parent code also lists its children.

> The route parameter is deliberately **untyped** (`mount(Budget $budget, $budgetItem = null)`).
> With `?BudgetItem $budgetItem = null`, Livewire's implicit binding 404s the unassigned page
> before `mount()` runs. The component resolves the id itself and checks ownership explicitly.

## 6. Expenses

`App\Livewire\Expense\ExpenseEdit` at `expenses/{expense}/edit` — the first real expense editor.
`ExpenseCreate` and `ExpenseEdit` share `app/Livewire/Concerns/ManagesExpenseForm.php` and the
partials `livewire/expense/partials/{form-body,item-modal}.blade.php`; `ExpenseCreate` went from
435 lines to 84. All three expense screens link to it.

| Situation | Rule |
|---|---|
| Unpaid, single payment | anyone who can reach the screen edits everything |
| Paid, or an installment paid | **administrators only** |
| An installment has been paid | amounts and payment terms locked; cost codes, names, supplier, date and notes still editable |
| Created from a purchase order | same lock — the expense mirrors the order |

Locked screens say which reason applies, disable the inputs, and the server enforces it again on
save. `Expense::updateWithHistory()` gained an optional second argument so the line-level diff is
folded into the **same** history entry as the header diff — one row per save:

```
Edited                                       Ana Paula · 20 Aug 2026 14:32
  notes:                    — → Recoded to sand
  Line 1 — cost_code:       5.4 - Cimento → 5.5 - Areia Media / Fina / Lavada
```

Both expense lists gained a **Cost Codes** column and an **All Cost Codes** filter. The three
`BudgetItem::find()` calls inside Blade loops are gone — the label travels on the line state.

## 7. Reports and PDFs

- **Budget by Cost Code** section in the project and job-site financial reports, on screen and in
  their PDFs, headed *lifetime figures, whatever the report dates say elsewhere*.
- **Change orders show both sides** in the Contract Value Breakdown: cost and margin per change
  order, *not approved* when it is not, *no cost breakdown* when nobody costed it.
- **A printable cost grid**: `budgets/{budget}/cost-grid/pdf` (and `/pdf/view`), landscape.
- **Expense report** By Cost Code tab says it shows spend in the selected period, and each code
  links to its drill-down where the figures are lifetime.

---

## 8. What phase 7 has to work

Everything noticed mid-build went into
**[`review-and-improvements.md`](./review-and-improvements.md)** — five sections, one per phase,
plus two corrections to earlier findings. The plan's §14 turns them into a checklist. The ones
worth knowing before touching the code:

1. **`change_orders.co_number` is not unique.** Two people creating a change order at the same
   moment get the same number. The quotation chain solved this with a unique index and retry.
2. **Approval has no permission guard.** Anyone who reaches the screen can approve, reject or
   un-approve — see `permissions-notes.md` §4b, which lists four questions for the owner.
3. **Dead code left behind.** The legacy expense modal in `JobSiteShow` and `ProjectShow` is
   unreachable for create and edit but still serves *view* mode, so it could not simply be
   deleted. `saveExpense()`, `openExpenseCreateModal()`, `openExpenseEditModal()` and
   `Expense::isEditableBy()` are all dead or contradictory now.
4. **Every financial report is built twice**, once in the Livewire component and once in the PDF
   controller, with the arithmetic copy-pasted. This work needed the same edit in four places.
5. **Two `BudgetItem::find()` N+1s remain** in the purchase-order blades.
6. **Contracts can be paid beyond what they allocate** — pre-existing, and the ledger reports it
   faithfully, but the contract module should flag it.
7. **Performance:** `ProjectBudget` and `ProjectFinancialReport` build one ledger per budget on
   the page. Fine today; the first project with dozens of job sites will feel it.

## 9. How this was verified

Every phase was driven with a temporary Livewire feature test against the **dev MySQL database**,
then deleted. Nothing was left behind: the change order tables are empty, and expenses, budgets
and purchase orders are at their pre-work counts.

Two traps worth remembering, both cost time here:

- **`DatabaseTransactions` must be pointed at MySQL before the app boots.** Setting
  `config(['database.default' => 'mysql'])` inside `setUp()` *after* `parent::setUp()` leaves the
  transaction wrapping sqlite, and every write commits for real. (It did — 27 change orders had
  to be deleted by hand.) Set `DB_CONNECTION` / `DB_DATABASE` in `$_ENV`, `$_SERVER` and
  `putenv()` **before** `parent::setUp()`.
- **`Livewire::test()` injects a blank model for an optional typed model parameter**, so
  components with `?Model $x = null` in `mount()` fail under test while working fine on their real
  route. Test those over HTTP with `$this->get(route(...))`.

The existing test suite is **broken independently of this work**: 33 tests fail because the
sqlite migration `2026_02_11_100001_add_partial_status_to_invoices_table` cannot run.

## 10. Translations

Roughly 130 new strings, every one with a pt_BR translation added in the same change, and the
parameterised ones (`:amount`, `:percent`, `:count`, `:number`, `:id`, `:term`) plus both plural
forms checked at runtime through the translator.

> `lang/pt_BR.json` lost four keys to a concurrent edit during this work and they were restored
> by hand. If anything else looks missing from that file, this is why.
