# Review and Improvements — the final phase of every module

**The standard lives in `CLAUDE.md`.** Every module ends with one explicit phase that is
planned from the start and never skipped: review the whole module, walk the real screens,
close the gap between what the screens promise and what the code enforces, sweep the
notations collected while building, and bring the docs and pt_BR level with what was built.

**This file is the backlog.** Anything noticed mid-build that would have derailed the
feature in hand gets written down here instead — with enough detail that it can be picked
up cold — and is then worked during the module's review phase.

Permission gaps go in `docs/permissions-notes.md` instead; they are a separate decision the
owner has to take.

**Status key:** `open` (to be worked) · `scheduled` (assigned to a phase) · `won't fix`
(decided, with the reason) · `done` (with the date).

---

## Quotation chain (requisition → cotação → propostas → mapa → negociação → award)

Collected while building phases 1–5, 2026-08-18/19. To be worked in **phase 9, Review and
Improvements** (see `docs/quotation-module-plan.md`).

### Correctness and data

| # | Item | Status |
|---|---|---|
| Q1 | **The RFQ and map PDFs are reachable by id** by any signed-in user. Same as every other PDF controller in the app, so it needs one decision applied everywhere — recorded as N5 in `docs/permissions-notes.md`. | open |
| Q2 | ~~`quotations.converted_type` / `converted_id` written by nothing~~ | **done** 2026-08-19 — phase 7 writes them for a single winner and leaves them null on a split, where `purchase_orders.quotation_id` / `contracts.quotation_id` are the reliable direction |
| Q3 | **A scope quantity change re-totals stored prices, but a *unit* change does not.** Changing "kg" to "sack" leaves the vendor's price attached to the old unit with no warning. Either warn, or treat a unit change like a quantity change and clear that line's prices. | open |
| Q4 | **Nothing recomputes a proposal when a scope line is added** after proposals arrived: the new line simply shows as *not quoted* for everyone, which is honest but silent. Consider flagging the affected vendors so procurement knows to go back and ask. | open |
| Q5 | ~~`is_split_award` and the per-item winner have no UI~~ | **done** 2026-08-19 — phase 6 |
| Q17 | **A converted contract carries its scope as a note, not as schedule lines.** The plan wanted the awarded items seeded as budget allocations / schedule lines; today the buyer builds the schedule by hand on the contract. | open |
| Q18 | **The draft PO takes `payment_due_date` from the round's needed-by date and one installment.** Reasonable defaults, but the vendor's quoted payment terms are only in the notes — they could seed the installments instead. | open |
| Q19 | **Conversion cannot be undone.** `converted` is terminal, so a PO raised in error has to be cancelled on the PO side and the round left as-is. Decide whether an "unconvert" is wanted or whether cancelling the order is the right answer. | open |

### Screens

| # | Item | Status |
|---|---|---|
| Q6 | **The vendor's attached proposal is only listed by filename** in the proposal screen — no download, no delete. The full `<livewire:shared.attachments>` component should be used there, as it is on the round itself. | open |
| Q7 | **The proposal screen sends a request per keystroke-ish** on every price field (`wire:model.live.debounce.500ms` per row) so the running total can move. On a 40-line scope that is a lot of round trips; consider computing the running total in Alpine and only syncing on blur. | open |
| Q8 | **Two ways to say a round went out** — "Compose the E-mail" and "Mark as Sent" — sit in the same panel. Correct, but the wording could make the choice sharper (sent *by us from here* vs *recorded as sent elsewhere*). | open |
| Q9 | **The map has no CSV export**, only PDF. Buyers often want the numbers in a sheet. | open |
| Q10 | **The comparison map is not reachable from the requisition** — you have to go via the round. A link from the requisition's Quotation Rounds panel would close the loop. | open |
| Q11 | **Long vendor names and many columns**: the map is horizontally scrollable with the item column frozen, but has not been walked on a phone with 5+ proposals. Part of the phase-9 screen walk. | open |
| Q12 | **A requisition cannot be duplicated**, which is the honest alternative to bypassing approval — the owner asked for it. Depends on the N1 decision. | open — see `permissions-notes.md` N1 |
| Q13 | **A rejected requisition is a dead end.** No re-open, no revise-and-resubmit; the only route is a new requisition. Confirm that is intended once Q12 exists. | open |

### Found in the code review, 2026-08-19 — fixed on the spot

| # | Defect | Where |
|---|---|---|
| R1 | **A `committed()` patch landed on an expense query, not the contract one** — `CompanyFinancialService` would have fataled on every company financial report (`Builder::committed()` does not exist on Expense). Caught by running the report. | `app/Services/CompanyFinancialService.php` |
| R2 | **Six contract queries were missed by the first draft-status sweep** — the contract payments PDF, the project and job-site financial report PDFs, the expense report service, the accounts-payable subcontractor summary, and the company financials. A draft contract would have counted as committed money in all six. | controllers + services |
| R3 | **Awarding a vendor who cannot supply a single line was allowed**, and converting it then threw an uncaught `DomainException` — a 500 for the user. Now blocked at the award with a plain message. | `ManagesQuotations::collectWholeWinner()` |
| R4 | **A tampered `vendorRows` entry reached the database** and blew up on the foreign key. Non-existent vendor ids are now dropped. | `ManagesQuotations::collectVendors()` |
| R5 | **Attachments outlived their records.** Deleting a requisition or a round left the attachment rows and their files behind, and a round's cascade deleted its vendor rows without ever firing their model events. All three models now clear their attachments the way `Income` does. | `PurchaseRequisition`, `Quotation`, `QuotationVendor` |

### Found in the second code review, 2026-08-19 — fixed on the spot

| # | Defect | Where |
|---|---|---|
| R6 | **The Payments dashboard was 500ing** — `$installments->merge($oneTime)` merges a collection of arrays into an *Eloquent* collection, whose `merge()` asks each item for `getKey()`. It fails whenever the installment query returns nothing, which is the state the page is in right now. **Pre-existing, nothing to do with the quotation work** — found by smoke-testing every GET route in the app. Fixed with `toBase()`. | `app/Livewire/Payment/PaymentDashboard.php` |
| R7 | **Dead code removed**: `PurchaseRequisition::getTotalQuantity()` and `scopePending()` were written and never called. | `app/Models/PurchaseRequisition.php` |

**Verified in this pass, with evidence rather than reading:**

- **The module switch works.** Turning `quotations` off returns 403 on all six new routes (both requisition pages, both quotation pages, both PDFs), leaves other modules at 200, and removes the nav links.
- **Every reachable page still renders**: 129 GET routes smoke-tested; the only failures are the Flux vendor asset routes (pre-existing), `setup` (404 once set up) and the two `files/*` routes that need a query parameter.
- **The role split holds on screen**: rendered as admin, manager and employee in both locales — employees are offered no approve, reject, award, revoke or convert action anywhere, while still being able to enter proposals and read the map.
- **pt_BR is complete**: 800 translatable strings across the 100 changed PHP/Blade files, none missing.
- **Attachment downloads work** for the new `requisitions/` and `quotations/` directories, and path traversal is refused.
- **Rollback order is safe**: the migration that adds `quotation_id` to purchase orders and contracts reverses before the `quotations` table it points at is dropped.

### Performance and housekeeping

| # | Item | Status |
|---|---|---|
| Q14 | **Every generated PDF is ~880 KB**, almost all of it the embedded DejaVu font. It matches the app's existing PDFs, but the RFQ one is e-mailed to every vendor on every round, so subsetting the font would be worth measuring. | open |
| Q15 | **`Quotation::respondedCount()` and friends load the whole vendor collection.** Fine at today's scale, and the list already eager-loads them; revisit if a project ever carries hundreds of rounds. | open |
| Q20 | **Requisition and quotation numbers are generated with `MAX(...)+1` and no unique index.** Two people creating at the same instant would get the same number. Contracts have a unique index; estimates, invoices and POs do not — so this needs one decision for all five, not a change to two new tables. | open |
| Q22 | **A requisition being quoted cannot be cancelled.** `canBeCancelled()` covers draft, pending and approved — once a round exists the requisition is `quoted` and the site has no way to withdraw the ask; the round has to be cancelled first. Decide whether cancelling should cascade or stay manual. | open |
| Q21 | **Nothing locks a round while it is being awarded.** Two reviewers awarding at the same moment would both pass the checks and the last write would win. The award is rare and deliberate, so this is a note rather than a fix. | open |
| Q16 | **pt_BR keys were added feature by feature.** The final sweep should look for near-duplicates and for English strings that slipped through the model helpers. | scheduled — phase 8 |

---

## How to add to this file

One row, in the right module section, with: what was noticed, why it matters, and — if it is
not obvious — what a fix would look like. Date the section, not each row. If something turns
out to be a real defect rather than an improvement, fix it there and then and note it in the
module doc's rules section instead of parking it here.

---

## Code review — 2026-08-19 (document repository + quotation chain)

A full review of the working tree at the end of the document repository build. Fifteen findings;
the six in the document module and eight of the nine in the quotation chain were fixed in the
same session. Verified before and after in each case.

### Document repository

| Was | Now |
|---|---|
| Deleting a shared document or folder left its link live — the public page **500'd** on the missing record, and the link could no longer be revoked | A link whose target is gone is unusable and says so; deleting revokes its links; revoking works on trashed targets |
| The upload size ceiling was client-side only: declare 1 byte at init, then PUT anything | The real size is checked against the ceiling when the upload completes, and an oversized object is deleted |
| `?folderId=` was trusted — folders and uploads could be filed into another project's folder, orphaned from both trees | Validated on every request and again in `currentFolder()` |
| `post_max_size=0` (unlimited) read as zero, so the panel promised 5 GB while uploads died at 8 MB | Unlimited reads as unlimited; verified across four php.ini combinations |
| The storage quota was displayed but never enforced, and measured one project against an install-wide ceiling | Enforced on both upload paths and measured install-wide, as documented |
| Two uploads for one document could collide on `version_number`; a rolled-back init left multipart uploads R2 bills forever | Collisions retry; the prune command reaps uploads the bucket holds with no version row behind them |
| A tampered category value 500'd on the enum cast; `restoreVersion()` crashed on a trashed document | Both validated |

### Quotation chain

| Was | Now |
|---|---|
| An award wrote freight/tax/discount into the PO header while the items held only the lines — the first save of that PO recomputed from the items and **silently deleted the difference**, and the freight never reached a cost code | `purchase_orders` carries the three amounts; every total includes them; the expense created from the PO gets freight and tax as lines with the discount apportioned, so its items sum to its header |
| The comparison map compared an equalized total against raw line prices, advertising a "saving" that was really the winner's freight | A split now pays each winning vendor's freight and a pro-rata share of their tax and discount; the screens say how many vendors a split would involve |
| A requisition was `fulfilled` on the first conversion, so a second open round lost its link back to it, silently | Fulfilled only when every live round is converted; an existing link is never dropped on save |
| A vendor answering "cannot supply" on every line counted toward the two-proposal floor | Only proposals with at least one price count |
| Two concurrent conversions each created a full set of orders, committing the money twice | The row is locked inside the transaction and re-checked |
| `COT-0001` could be issued twice and go out to vendors | Both numbers are unique in the database, and allocation retries on collision |
| Dropping an invited vendor left orphaned attachment rows and files | Deleted one at a time so the model's cleanup hook runs |
| Quotations had no `module_access` row, so it could never be switched off from the settings screen | Registered like every other module |
| Draft contracts showed as outstanding balances in the payment detail report, and could be written into and approved in a payment batch | `committed()` in the report; drafts refused on batch save and on both approve paths |

### Not fixed

- `Quotation::isOpen()` still permits re-sending an RFQ on an awarded round.
- `proposalTotals()` sums unrounded line products while `saveProposal()` stores rounded ones, so
  the live form total can differ from the saved total by cents.
- `documents:purge-deleted --days=0` purges the whole trash immediately, which is what it says but
  is an easy mis-type.

---

## Noticed while building change order cost codes — phase 1 (2026-08-19)

Parked rather than fixed mid-build. Phase 5 and phase 7 of
`docs/expense-changeorder-costcode-plan.md` own most of these.

- ~~**Expenses cannot be edited at all.**~~ **Corrected 2026-08-20:** the project *page*
  (`ProjectExpenses`) had no edit, but `JobSiteShow` and the legacy `ProjectShow` carried a
  modal that edited the pre-items header fields (`item_name`, `quantity`, `unit_price`) and
  had no cost code at all — wrong for every expense written since the items table existed.
  Phase 5 replaced all three with one editor.
- ~~**`ExpenseChangeHistory` is dead code.**~~ **Corrected 2026-08-20:** payment actions do
  write history through `Expense::recordChange()` and `Expense::updateWithHistory()`. What was
  missing was an `edited` writer covering the line items and their cost codes — phase 5 added
  it, folding the line diff into the same entry as the header diff.
- **N+1 in two Blade loops.** `project-expenses.blade.php:312` and
  `partials/expense-modal.blade.php:63` call `BudgetItem::find()` per expense line inside the
  loop, on a page that lists every expense. → phase 5.
- **A contract can be paid more than its schedule allocates.** In the demo data, code 21.1
  shows 16,650 paid against 12,820 scheduled: `Contract::costCodeSchedule()` books a payment
  against the payment line's own cost code, which need not be one the contract allocated to.
  Not introduced here, and the ledger reports it faithfully, but the contract module should
  either prevent it or flag it.
- **`Budget::costCodeGrid()` and `CostCodeLedger` now overlap.** The grid predates the ledger
  and only knows about contracts. Phase 3 replaces the grid with the ledger; until then the
  budget screens still show the contracts-only figures.
- **`lang/en.json` carried a duplicate `"by"` key** (lines 176 and 299, same value). Collapsed
  to one while adding this phase's strings — JSON keeps the last of a duplicate, so nothing
  changed at runtime.

---

## Meetings, minutes and tasks

Collected while building, from phase 0 on 2026-08-19. To be worked in **phase 9, Review and
Improvements** (see `docs/meetings-module-plan.md`).

| # | Item | Status |
|---|---|---|
| M1 | **Shared status words carry the wrong gender for tasks.** `Open`, `In Progress`, `Completed`, `Cancelled` and `Draft` already existed in `lang/pt_BR.json` for other modules, translated in the masculine ("Concluído", "Em Aberto"). A *tarefa* is feminine, so the task screens will read slightly wrong in pt_BR. Existing keys were deliberately not changed — they are shared with expenses, contracts and quotations. Fix by giving the task screens their own keys (e.g. `Task completed` → "Concluída") rather than by editing the shared ones. | open |
| M2 | **`Task::isMeetingTracked()` and `hasSubtasks()` each run a query.** Fine on a detail screen, an N+1 in a list. The list screens must use `withCount('meetingItems')` / `withCount('subtasks')` and read the counts; check every call site during the review. | open |
| M3 | **Progress roll-up is only recalculated when something calls `refreshProgressFromSubtasks()`.** Phase 1 must call it from every path that moves a sub-task (progress, status, cancel, delete, re-parent), and the review has to prove no path was missed — a stale parent percentage on a screen the owner reads is worse than no percentage. | open |
| M4 | **`Meeting::openActionCount()` runs two queries and cannot be eager-loaded.** Acceptable on a detail page, wrong on the meetings index. Give the index a single grouped count during phase 3. | open |
| M6 | **`MyTasks::stats()` runs four counting queries on every render**, and `groups()` a fifth. Fine for one person's list; when the All Tasks page (phase 8) reuses the shape it needs one grouped query instead. | open |
| M7 | **A note can be edited for 30 minutes** (`TaskNote::canEdit()`) but no screen offers it yet — the timeline shows an "edited" marker that nothing can currently set. Either wire the edit control in phase 2 or drop the marker. | open |
| M8 | **Deleting a task is not implemented.** The model has soft deletes and the plan gives admins a delete, but nothing calls it; cancel covers the real case. Decide in the review whether delete is wanted at all, and if so what happens to the meeting items that point at it. | open |
| M9 | **The agenda reorders with up/down buttons, not drag-and-drop** as §5.2 of the plan describes. Buttons are keyboard-reachable, unambiguous on a phone (where dragging inside a scrolling list fights the scroll), and need no library. Decide in the review whether drag is worth adding on top — if so, keep the buttons as the accessible path. | open |
| M10 | **`MeetingAgenda::scopeCandidates()` runs two queries per location on the agenda.** Fine for the three or four locations a real meeting covers; if a meeting ever spans twenty, this wants one grouped query. | open |
| M11 | **`strip_tags()` with an allowlist is used elsewhere in the app to print editor output** (`resources/views/pdf/daily-report.blade.php:206`, and wherever else TinyMCE content is echoed). It removes disallowed *tags* but keeps the *attributes* of the ones it allows, so `<p onclick="…">` survives it. The meetings module now routes editor output through `App\Support\RichText`; the review should sweep the other call sites onto it. Low exploitability (only admins and managers can author that content) but it is stored XSS. | open |
| M12 | **The English locale renames Project → "Job Site" but leaves "Job Site" untranslated**, so any screen offering both reads "Job Site" twice — visible in the meetings module's *Add a Location* panel and the agenda item form. `lang/en.json` already maps `Projects → Job Sites`, `# Job Sites → # Lots` and `Job Site(s) → Lot(s)`, so the intended vocabulary is Project→Job Site and Job Site→Lot; the plain `Job Site` key is simply missing. Adding it would correct **33 call sites across 20 files** (estimates, invoices, budgets, reports, payments, meetings) in one go, which is why it was not done as a side effect of writing the user guide. **Owner decided 2026-08-20: do not touch the EN translation.** The user guide therefore explains the vocabulary as the screens actually read it. | won't fix |
| M5 | **Attendance defaults to `present`.** Convenient when the list is seeded from the series and most people turn up, but it means an untouched attendance list records everyone as present, including people who were never there. Decide in phase 3 whether the default should be blank until the secretary marks the register. | open |

### Noticed during phase 2 (change order editor)

- **A third copy of the change order UI existed** on the legacy tabbed page
  (`/projects/{project}/show`, `ProjectShow`) alongside the project and job-site screens. All
  three now run off `ManagesChangeOrders` and the shared partials, but the legacy page itself
  is still routed and duplicates a lot of what the split pages do — worth deciding whether it
  should be retired.
- **`change_orders.co_number` is not unique.** The form pre-fills the next number in the
  project's series, but two people creating a change order at the same moment get the same
  one. The quotation chain solved this with a unique index and retry on collision
  (`2026_08_19_170001_add_unique_numbers_to_quotation_chain`); change orders should get the
  same treatment.
- **Approval has no permission guard** — see `docs/permissions-notes.md` §4b.
- **Deleting an approved change order silently revises budgets back down.** It is the correct
  arithmetic, but there is no warning that the cost codes will move.
- The pt_BR file lost four keys (`_cost_code_dialog`, `Save & Add Another`,
  `Filled in for you — change it only to reorder.`, `Costs that have not been given a cost
  code.`) to a concurrent edit during this session and they were restored by hand. Worth a
  look at whether anything else went with them.

### Noticed during phase 3 (budget screens on the ledger)

- **`ProjectBudget` builds one ledger per budget on the page.** A project with many job sites
  runs the whole aggregation once per budget, and each one walks its contracts through
  `Contract::costCodeSchedule()`. Fine at today's sizes, worth a single grouped query if a
  project ever carries dozens of job sites.
- **`BudgetShow::toggleDefaultItem()` clears the flag by hand** when un-starring, rather than
  going through the model. Setting now uses `Budget::setDefaultItem()`; clearing has no model
  method to call yet.
- The cost grid needs `min-w-[1100px]` for its nine columns, so on a phone it scrolls inside
  its own container. That is the documented behaviour for wide tables, but a stacked card view
  for small screens would read better.

### Noticed during phase 5 (expense editing)

- **The legacy expense modal in `JobSiteShow` is now unreachable but still there.** Its create
  and edit branches are dead (both entry points now go to `expenses.edit` / the create page),
  but the modal also serves *view* mode, so the block cannot simply be deleted. `saveExpense()`,
  `openExpenseCreateModal()` and `openExpenseEditModal()` in that component are dead code.
  Same story in `ProjectShow`. Worth a dedicated cleanup pass with the view mode split out.
- **Two more `BudgetItem::find()` N+1s remain**, in `purchase-order-create.blade.php:173` and
  `purchase-order-edit.blade.php:184`. The fix is the one used for expenses — carry the code
  label on the line state — but it means touching the PO save paths, so it was left alone.
- **`Expense::isEditable()` and the new `ExpenseEdit` guards say different things.** The model
  blocks editing when payments exist; the new screen allows it for admins and locks the
  amounts instead. The model method is now only used by the dead modal code above; when that
  goes, `isEditableBy()` should go with it or be brought in line.

### Noticed during phase 6 (reports and PDFs)

- **Every financial report is built twice** — once in the Livewire component and once in the
  PDF controller, with the arithmetic copy-pasted between them. Phase 6 had to add the same
  wiring in both places for both levels (four edits for one feature). A shared builder service
  would have made it one.
- **`ProjectFinancialReport` now builds a ledger per budget on every render**, on top of the
  existing per-contract loops. Fine at today's sizes; the first project with dozens of job
  sites will feel it.
- The **cost grid PDF is landscape while every other PDF is portrait** — nine money columns
  do not fit otherwise. Worth knowing before someone standardises the paper size.
