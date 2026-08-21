# Review and Improvements — the final phase of every module

**The standard lives in `CLAUDE.md`.** Every module ends with one explicit phase that is
planned from the start and never skipped: review the whole module, walk the real screens,
close the gap between what the screens promise and what the code enforces, sweep the
notations collected while building, and bring the docs and pt_BR level with what was built.

**This file is the backlog.** Anything noticed mid-build that would have derailed the
feature in hand gets written down here instead — with enough detail that it can be picked
up cold — and is then worked during the module's review phase.

**What is in here right now:** the quotation chain rows, the 2026-08-19 code review, the cost
code / change order rows (phases 1–6, for that module's phase 7), and the meetings rows
M1–M12 with the write-ups of the ones already done.

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

## Cost codes on expenses and change orders

Collected while building phases 1–6 on 2026-08-19/20. **To be worked in phase 7, Review and
Improvements** — the checklist is §14 of `docs/expense-changeorder-costcode-plan.md`, which
groups everything below by what it costs to fix. Two earlier findings turned out to be wrong
and are corrected in place rather than deleted, because the wrong version was acted on.

### Noticed during phase 1 (data model and ledger)

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

---

## Meetings, minutes and tasks

Collected while building, from phase 0 on 2026-08-19. To be worked in **phase 9, Review and
Improvements** (see `docs/meetings-module-plan.md`).

| # | Item | Status |
|---|---|---|
| M1 | ~~Shared status words carry the wrong gender for tasks~~ | **done** 2026-08-20 — task and meeting screens have their own keys. See below. |
| M2 | ~~`Task::isMeetingTracked()` and `hasSubtasks()` each run a query~~ | **done** 2026-08-20 — they use eager-loaded counts. See below. |
| M3 | ~~Progress roll-up is only recalculated when something calls it~~ | **done** 2026-08-20 — it is a model event now. See below. |
| M4 | **`Meeting::openActionCount()` runs two queries and cannot be eager-loaded.** Acceptable on a detail page, wrong on the meetings index. Give the index a single grouped count during phase 3. | open |
| M6 | **`MyTasks::stats()` runs four counting queries on every render**, and `groups()` a fifth. Fine for one person's list; when the All Tasks page (phase 8) reuses the shape it needs one grouped query instead. | open |
| M7 | **A note can be edited for 30 minutes** (`TaskNote::canEdit()`) but no screen offers it yet — the timeline shows an "edited" marker that nothing can currently set. Either wire the edit control in phase 2 or drop the marker. | open |
| M8 | ~~Deleting a task is not implemented~~ | **done** 2026-08-20 — admin-only, refused for anything in a published minute. See below. |
| M9 | ~~The agenda reorders with up/down buttons, not drag-and-drop~~ | **done** 2026-08-20 — drag added, buttons kept. See below. |
| M10 | **`MeetingAgenda::scopeCandidates()` runs two queries per location on the agenda.** Fine for the three or four locations a real meeting covers; if a meeting ever spans twenty, this wants one grouped query. | open |
| M11 | ~~Editor output printed unescaped~~ | **done** 2026-08-20 — swept. See below. |
| M12 | **The English locale renames Project → "Job Site" but leaves "Job Site" untranslated**, so any screen offering both reads "Job Site" twice — visible in the meetings module's *Add a Location* panel and the agenda item form. `lang/en.json` already maps `Projects → Job Sites`, `# Job Sites → # Lots` and `Job Site(s) → Lot(s)`, so the intended vocabulary is Project→Job Site and Job Site→Lot; the plain `Job Site` key is simply missing. Adding it would correct **33 call sites across 20 files** (estimates, invoices, budgets, reports, payments, meetings) in one go, which is why it was not done as a side effect of writing the user guide. **Owner decided 2026-08-20: do not touch the EN translation.** The user guide therefore explains the vocabulary as the screens actually read it. | won't fix |
| M5 | ~~Attendance defaults to `present`~~ | **done** 2026-08-20 — it starts blank. See below. |

## M11 — the editor-output sweep (done 2026-08-20)

**What was wrong.** Three browser screens printed TinyMCE content with no filtering at all —
`estimate-show.blade.php`, `invoice-show.blade.php` and `daily-report-form.blade.php` echoed
`{!! $model->message_body !!}` straight out of the editor. A `<script>` stored in an estimate
message ran in the browser of everyone who opened that estimate.

**What was swept**, all through `App\Support\RichText`:

| Where | Before | Now |
|---|---|---|
| `estimate-show`, `invoice-show`, `daily-report-form` | raw `{!! … !!}` | `RichText::sanitize()` |
| `emails/estimate`, `emails/invoice`, `emails/quotation-rfq` | raw `{!! $emailBody !!}` | `RichText::sanitize()` |
| `pdf/estimate`, `pdf/invoice`, `pdf/daily-report` | `strip_tags($html, '<p><br>…')` | sanitise **first**, then the same `strip_tags` allowlist |
| `job-site-show` | `{!! Str::limit(strip_tags(…)) !!}` | `{{ … }}` — it was already plain text |

The PDFs keep their original narrow tag lists, so nothing about their layout changes; sanitising
first simply means no attribute survives to reach them. They were never the exposure — dompdf does
not execute a click handler — but they are the same class of code and were cheaper to fix than to
explain.

**Verified** by compiling each swept Blade expression and running a payload carrying `onclick`,
`<script>`, `<img onerror>` and a `javascript:` href through it: every one comes out clean, while
bold, lists and a real `https://` link survive. External links also gain
`rel="noopener noreferrer"`.

**The invariant to keep:** editor output is never printed with `{!! … !!}` unless it has been
through `RichText`. The only remaining unescaped echo of user content is the documentation
article, which is sanitised in `DocumentationService` before it reaches the view.

---

## M1 — the feminine words (done 2026-08-20)

`Task::getStatusLabel()`, `getPriorityLabel()` and `Meeting::getStatusLabel()` now use their own
translation keys — `Task status: completed`, `Meeting status: cancelled` and so on — instead of the
plain words shared with expenses, contracts and quotations. Those shared keys are **unchanged**, so
nothing else in the app moved.

| | was | now |
|---|---|---|
| task completed | Concluído | **Concluída** |
| task cancelled | Cancelado | **Cancelada** |
| meeting cancelled | Cancelado | **Cancelada** |
| task priority low / high | Mínima / Máxima | **Baixa / Alta** |

The priority change is not gender — *mínima* and *máxima* mean minimum and maximum, which is not
what a task's priority says. The other words (*Em aberto*, *Em andamento*, *Impedida*, *Aguardando
confirmação*, *Rascunho*, *Publicada*) were already right and read the same.

The filter dropdowns that build their own labels were moved onto the same keys, so a filter and the
badge it filters for cannot disagree. English is unchanged: `en.json` maps each new key to the
display word it always had.

## M2 — the N+1 on task lists (done 2026-08-20)

**Measured first.** My Tasks with 22 rows ran **36 queries, 21 of them asking about sub-tasks** —
one per row, from `canMarkReady()` calling `hasOpenSubtasks()`.

`isMeetingTracked()`, `hasSubtasks()` and `hasOpenSubtasks()` now answer from whatever the query
already loaded, and only fall back to their own query when nothing did:

1. an eager-loaded relation, if there is one;
2. an eager-loaded count (`subtasks_count`, `open_subtasks_count`, `meeting_items_count`);
3. `subtasks_count === 0`, which settles `hasOpenSubtasks()` without asking;
4. otherwise, the query — a detail screen can afford one.

The two list queries (`MyTasks::groups()` and `ListsScopedTasks::groups()`) now load
`subtasks as open_subtasks_count`.

**After: 16 queries, 1 about sub-tasks.** The meeting screens were already covered — they eager-load
`task.subtasks`, so the loaded-relation branch answers there.

Because the guard now reads three different ways, all three were tested against the same task: a
parent with an open sub-task refuses **Ready** whether it was loaded plain, with counts, or with the
relation; after the sub-task closes, all three allow it; and a task with no sub-tasks at all is
allowed through the count shortcut.

## M3 — the roll-up cannot be missed now (done 2026-08-20)

**The worry was not today's code**, which called `refreshProgressFromSubtasks()` from every path
that existed; it was that a path added later — an import, a command, a screen nobody has written —
would forget, and a parent would show a stale percentage on a screen somebody trusts.

So the roll-up moved out of `TaskService` and onto the model, as `saved`, `deleted` and `restored`
events. The three explicit calls in the service were removed: they are the model's job now, and
leaving them would have implied the model could not be relied on.

Details worth keeping:

- **Any save of a sub-task recomputes**, not only one that changed `progress` or `status`. Deciding
  from `wasChanged()` left a parent stale after a save that touched something else — a `touch()`
  proved it. One count per sub-task save is a fair price.
- **Re-parenting moves two figures**, the parent it left and the parent it joined, read from
  `getOriginal('parent_task_id')`.
- **The parent is re-fetched** rather than used from the relation: one handed in from elsewhere may
  carry a stale `subtasks` relation, which M2's count-preferring logic would then read.
- **A recursion guard**, because recomputing saves the parent, which fires `saved` again. Two
  levels would terminate on their own; the flag says so rather than relying on it.
- **The last sub-task leaving** stops the percentage being derived and leaves the number where it
  was, for the owner to set again — rather than silently dropping to zero.

Verified against every way a sub-task can move, including paths that never touch the service:
`TaskService::setProgress`, a plain Eloquent `update()`, cancelling a child (excluded from the
average), adding a child, moving a child between parents (**both** parents corrected), deleting,
restoring, and the last child leaving. One child update costs six queries with no recursion.

**The one hole, stated plainly:** a query-builder mass update (`Task::where(…)->update(…)`) fires no
model events, so a parent goes stale until the next save of any of its children heals it. Eloquent
cannot observe those. Nothing in the application does it — checked — and if something ever needs to,
it must call `refreshProgressFromSubtasks()` itself.

## M5 — attendance starts blank (done 2026-08-20, owner's decision)

`meeting_attendees.attendance` is nullable and defaults to null. A register seeded from the series,
and a follow-up meeting's copied register, both arrive **unmarked**; the minute shows *"Not
recorded"* rather than asserting somebody was there. Pressing the marked letter again clears it, so
a mis-click can be undone instead of leaving the record saying something nobody checked.

Publishing **warns** rather than blocks — *"3 people on the register are not marked…"* — because an
incomplete register is a judgement call for the chair, not a broken minute. The attendance card
shows the unmarked count while the meeting runs.

Existing rows were left exactly as they were: they had already been marked, or were part of a
published minute.

## M8 — deleting a task (done 2026-08-20, owner's decision)

Admin only, and **refused for any task a published minute mentions** — including through its
sub-tasks. That was the open question, and the answer is that a published minute is a record: a
reader following its link to "this task no longer exists" is a hole in it. The refusal names the
minutes and points at **Cancel**, which keeps the history and stops the task counting as open. The
screen shows that sentence in place of the delete button rather than offering something that will
fail.

Everywhere else it deletes: sub-tasks go with the parent, notes and assignees with them, **files are
removed from storage** (they cost money and nothing in the app could reach them again), and lines
come off any **draft** agenda, which is not a record yet. The task itself is soft-deleted, so an
admin's mistake is recoverable in the database, and the deletion is written to the activity log
with its reason.

## M9 — drag to reorder (done 2026-08-20, owner's decision)

Agenda rows are draggable, with a grip handle and a line showing where the row will land. **The
up/down arrows stay** — they are the path that works with a keyboard and on a phone, where dragging
inside a scrolling list fights the scroll. Plain HTML5 drag events, no library added.

The browser sends the order it now shows and the server rebuilds from it defensively, which was the
part worth testing: a **sub-item** id is ignored (not a sibling), an id from **another meeting** is
ignored and does not move, a **partial list** from a stale page leaves the omitted rows in place
rather than dropping them, and a **published** agenda refuses the reorder outright.

---

## Permissions module — parked during M4 (Expenses), 2026-08-20

Three things noticed while sweeping the expense module. None of them blocked the pass; each
needs a decision rather than a fix, so they are recorded here instead of being taken
unilaterally.

### P1 — who should read an expense's change history? *(open — owner's call)*

The history panel on the expense detail was `@admin`. M4 converted it to
`expenses.edit_paid`, which reproduces today's answer exactly (the seeds keep `edit_paid` to
administrators) and makes it one tick to grant.

But `edit_paid` means *"may amend settled money"*, and reading who changed what is not that.
The design standard says a detail view shows its audit facts, which argues for
`expenses.view` — every person who can see the expense sees its history.

Against that: the history names people and shows old amounts, which is exactly the sort of
thing a customer may not want a site team reading.

**Options:** (a) leave it on `expenses.edit_paid`; (b) move it to `expenses.view`; (c) give the
area a seventh action, `expenses.history`, and seed it to administrators. Nothing is blocked
either way.

### P2 — M2 left two redundant `authorizeAdmin()` calls behind its own guards

In `ProjectShow`, `deleteProject()` and `deleteJobSite()` each call
`authorizeAbility('projects.delete', …)` **and then** `authorizeAdmin()`. The confirmation
methods that open the modals ask only for the ability.

So a non-administrator holding `projects.delete` is shown the confirmation dialog, confirms,
and is then refused — precisely the "offered and then refused" behaviour the owner objected to
during M2. The safe direction, but still a screen saying something the code does not do.

Deleting the `authorizeAdmin()` lines makes the grant mean what the matrix says it means. That
is M2's decision to revisit rather than M4's to take, so it was left alone. The buttons were
made consistent where they were plainly wrong: the job-site Delete on the legacy project screen
was `@admin` while its guard was `projects.delete`, and is now `@can('projects.delete')`.

### P3 — the receipt route is closed one directory at a time

`FileController` served **any** file under `storage/app` whose path a signed-in user could
name. M4 closed `expenses/` by resolving the path back to its owning `Expense` and asking
`expenses.view`.

Eleven directories are still open on the old rule — `income/`, `purchase-orders/`,
`requisitions/`, `quotations/`, `contracts/`, `contract-change-orders/`, `change_orders/`,
`daily_reports/`, `temp_daily_reports/`, `subcontractor-documents/`, `company-logos/`. Each
module's pass adds one line to the `$areas` map in `authorizeFile()`. **Every pass from M5
onwards must do this** — it is easy to forget because nothing fails when it is skipped.

`livewire-tmp/` is deliberately left open: it holds in-flight uploads that belong to no record
yet.

**Updated after M5:** `income/` is closed too, through its polymorphic `Attachment` rather than a
column, and the map is now a `match` in `authorizeFile()` that takes either shape. M6 added no
directory — budgets and cost code templates store no files. M7 closed
`requisitions/`, M8 closed `quotations/` — the first with two owners, the round's own files and
each vendor's proposal — M9 closed `purchase-orders/`, M10 closed `change_orders/` and M11 closed both
`contracts/` and `contract-change-orders/`. Four
directories remain, after M14 closed `daily_reports/` —
`temp_daily_reports/` (deliberately open, like `livewire-tmp/`: in-flight uploads that belong to
no record yet), `subcontractor-documents/` (**still open — see P33**) and `company-logos/` (already behind `company.view` at the screen; its directory is the only one
that holds nothing project-scoped).


---

## Permissions module — parked during M5 (Income), 2026-08-21

### P4 — "Mark as received" is filed under `income.edit` *(open — low stakes)*

Booking expected money as cash changes what the company financial report counts as revenue, and
M5 put it behind `income.edit` because it is a correction to the record and the income area has
no `pay`-style action.

Expenses got a separate `expenses.pay` for the equivalent act. The asymmetry is deliberate —
`expenses.pay` already existed in the catalogue and settling a supplier is a bigger act than
confirming a client's payment landed — but if the owner wants them to match, adding
`income.receive` is a catalogue line, a guard swap on two methods and a seed entry.

Nothing is blocked; recorded so the asymmetry is a decision rather than an oversight.

### P5 — the split grid is guarded six times over *(done, noted for the pattern)*

`income.distribute` guards `updatedIncomeLocationMode`, `splitEvenly`, `assignRemainder`,
`clearAllShares`, `toggleAllSites`, `updatedDistributionRows` **and** the save. That is not
belt-and-braces for its own sake: every one of them is a public Livewire method reachable from
the browser regardless of what the page rendered.

The trap it exposed is worth carrying into later passes: **an action that a component calls
internally must not be the guarded one.** Leaving split mode called `clearAllShares()`, so
guarding that method would have refused somebody on the way *out* of a mode they were never
allowed into. Split into a guarded public action plus an unguarded protected worker.

---

## Permissions module — parked during M6 (Budget & Cost Codes), 2026-08-21

### P6 — company-wide abilities can only be held through a role *(FIXED in F0, 2026-08-21)*

The owner's standing rule during M6: **no ability should be reachable only through a role.** For
anything scoped to a project or a job site that already holds — `budget.lock` can be granted on a
role, on a permission template, on one project, on one job site, or to one person on one project,
and M6's tests prove all four.

For a **company-wide** area it does not. A user's company-wide abilities come from
`users.role_id` → `role_abilities`, and nothing else. To give one person `cost-codes.edit` without
giving it to everyone who shares their role, you have to create a role for them.

Memberships already model "this person, this scope, these abilities" and are `nullableMorphs`, so
a company-level membership would need no new table — a scope of null, or a `Company` scope
implementing `PermissionScope`. The resolver's step 4 would consult it before falling back to the
role.

**Built in F0.** The owner chose exceptions that can both add and take away, so a new table —
`user_abilities` — holds one row per ability a person differs from their role on: `granted = true`
is always allowed, `granted = false` is never allowed, and no row at all means follow the role.
`PermissionResolver::companyAllows()` is now the only thing that consults a role, so the rule
holds everywhere the role would have answered. Settings → Users → **Access** is the screen; it is
two-state to edit and three-state in storage, so only real differences are written.

### P7 — `budget.lock` is not a `sensitive` action but arguably should be *(open — cosmetic)*

The catalogue marks it `sensitive`, which today means one thing only: a warning marker in the
matrix, and never granted by a template by default. Both are true of it. Recorded only so the
flag's meaning is not quietly widened later — `sensitive` is a hint to the person granting, not a
statement about who may hold it.

### P8 — locking is a project-level idea applied to a per-budget record

A project can have one project budget and one budget per job site, and each locks independently.
That is right for a job site that has finished while the rest of the project runs, but it means
there is no single "the project's baseline is frozen" state, and no way to lock all of them at
once.

Nobody has asked for one. If it comes up, the natural shape is a *Lock all* action on the project
Budget tab gated on `budget.lock` for the project, which cascades to its job-site budgets and
writes one history line each — not a new column.

---

## Permissions module — parked during M7 (Requisitions), 2026-08-21

### P9 — the standalone quotation round is the half of N1 still open *(M8's to settle)*

M7 locked the submitted requisition, added *Duplicate*, and made submitting its own grant. What
it could **not** close is the original point of N1: `quotations.purchase_requisition_id` is
nullable by design, so a round can be raised with no requisition at all — and the approval gate
is walked around by starting one step further down the chain.

This is M8's, not a leftover: it is a question about quotations, and the answer shapes that
pass. The three shapes on the table are per-install (a setting), per requisition type, or per
grant (`quotations.create_standalone`, which would match how `requisitions.approve_own` handles
the equivalent problem).

### P10 — administrators are outside every rule the ability system expresses

Surfaced by N2 but general. `PermissionResolver::decide()` step 3 returns true for
`$user->is_admin` before any area is consulted, so an administrator holds `approve_own`,
`edit_paid`, `budget.lock` and everything else by definition. Every "block" this module builds is
therefore a block on non-administrators.

That is the right default and it is what makes the legacy bridge safe. But it means a rule the
owner states as absolute — "the reviewer must not be the requester" — is absolute only below
admin. If any of these need to bind administrators too, the check has to sit outside the ability
system (a model-level rule, like `refuseIfLocked()` in M6, which *does* bind administrators).

**`refuseIfLocked()` is the pattern to copy** where a rule is about the record rather than the
person. Worth deciding case by case rather than as a policy.

### P11 — ownership rules on submit are still loose *(low stakes)*

N1 option 3 was "you may submit, edit and cancel **your own** draft; someone else's needs a
reviewer". M7 implemented the *return-to-draft* half of that but left `submitForApproval` open to
anyone holding `requisitions.submit` on the project — so a colleague can still put your draft in
the queue.

Arguably fine: on a site, somebody keying in and somebody sending are often different people by
design, and the requisition names its requester either way. Left as it is; recorded so the gap is
a decision.

---

## Permissions module — parked during M8 (Quotations), 2026-08-21

### P12 — a stale foreign key survives on sqlite only *(FIXED in M9, 2026-08-21)*

Closed by `2026_08_21_140000_drop_legacy_vendor_foreign_keys_on_other_drivers`, which drops the
seven legacy constraints on every non-MySQL driver and does nothing on MySQL, where the vendor
unification already dropped them. `QuotationTest`'s scaffolding row is gone and the
award-to-purchase-order path is covered against a real schema. **The original note follows.**


`create_purchase_orders_table` gave `purchase_orders.supplier_id` a foreign key to the legacy
`suppliers` table. The vendor unification drops that constraint and remaps the ids — but its body
returns early on any driver that is not MySQL, because there are no legacy rows to move.

So on **MySQL, production is correct**: the constraint is gone and `supplier_id` holds a vendor
id. On **sqlite**, the original constraint is still there, and converting an awarded round into a
purchase order fails with a foreign-key error for any vendor with no matching legacy row.

*(As written during M8: `QuotationTest` inserted a matching `suppliers` row in its fixture rather
than pretend the schema was something it was not, and noted that M9 would hit the same wall. M9
took the better option — a new additive migration rather than editing one that has already run in
production.)*

### P13 — `approval_limit` has no company-wide home *(FIXED in F0, 2026-08-21)*

The ceiling lives on a permission **template** and on a **membership**. A company-wide user with
no membership on the project therefore has **no ceiling at all** — `approvalLimit()` returns null
and every amount is within it.

That is fine while confinement is off, since every real user is company-wide and the ceiling was
never enforced before. It stops being fine at F1: the moment "Assigned only" is offered, an
install will have some people capped and some not, with no way to cap the company-wide ones short
of giving them a membership on every project.

**Built in F0.** The owner chose the ceiling on the **role**, with a per-person override —
`roles.approval_limit` and `users.approval_limit`, both nullable and both in cents. Null at both
levels still means *no ceiling*, so an install that upgrades and sets nothing is not suddenly
capped. `approvalLimit()` now reads the membership first (a ceiling on the project it was granted
for), then the person, then the role.

### P14 — `User::canReviewRequisitions()` is now dead

M7 and M8 replaced every call site. The method survives only in `app/Models/User.php`. It goes
with `AuthorizesAdmin`, the `@admin` directive and the `admin` middleware in F2; recorded so it is
not mistaken for something still in use.


---

## Permissions module — parked during M9 (Purchase Orders), 2026-08-21

### P15 — the delivery does not touch the expense *(open — worth a decision, not urgent)*

Approving a purchase order creates an expense. Recording a delivery against that order changes
nothing about the expense: it stays at the full ordered amount whether one bag arrived or all
hundred did.

That is defensible — the order is what was committed, and the supplier will invoice for it — but
it means "partially received" and "expense for the full amount" sit side by side with nothing
tying them together. On a part-delivery that never completes, the expense overstates what was
actually taken.

Three shapes if it matters: leave it (the order is the commitment); show the received proportion
on the expense as information only; or let a receipt adjust the expense, which is a much bigger
change and would need its own history.

Nobody has asked. Recorded so the gap is a decision.

### P16 — receiving is per order, not per job site delivery note

A delivery is recorded against the whole order. Where one order covers several job sites — which
the data model allows, since `job_site_id` is on the order and not on its lines — there is no way
to say "the cement went to Site A". In practice orders are raised per site, so this has not come
up; if it does, the answer is a job site on the receipt rather than on the line.


---

## Permissions module — parked during M10 (Change Orders), 2026-08-21

### P17 — `contract-change-orders/` is a different module *(M11's)*

`change_orders/` and `contract-change-orders/` are two different things. The first is a change to
the **project's** scope, which M10 covers. The second is an *aditivo* to a **contract**, served by
`App\Livewire\Contract\ContractChangeOrders`, and it belongs to the `contracts` area rather than
`change-orders`. M11 closes its directory and guards its screen; M10 deliberately left both alone
so the two are not conflated.

### P18 — the ceiling reads the cost side, and a change order has two

A change order carries **revenue** (`amount`, what the client is now billed) and **cost** (the sum
of its cost lines). M10 checks the approval ceiling against the cost side, because the ceiling is
about what somebody may commit the company to spending.

That is the right reading for a normal change order, where the client pays more and the company
spends more. It is a weaker control on one where the revenue is large and the cost small — the
margin is the company's gain, so there is nothing to protect against — and on the reverse, where
the cost is large and the revenue small, the ceiling still binds correctly.

Recorded because "the ceiling" is now checked against three different figures in three modules
(the awarded total in M8, the order total in M9, the cost impact in M10) and each choice is
deliberate rather than incidental.


---

## Permissions module — parked during M11 (Contracts & Payments), 2026-08-21

### P19 — `payments.pay` cannot obey a ceiling, and that is P13 biting for real *(FIXED in F0, 2026-08-21)*

`contracts.pay` obeys `approval_limit`. `payments.pay` — the same act, on the company-wide
dashboard — cannot, because the ceiling lives on a membership or a permission template and these
screens belong to no project.

So the same person can be stopped from releasing R$ 50.000 against a contract from inside the
project, and then release it from the payments dashboard with no ceiling at all. **That is a real
hole in the ceiling as a control, not a cosmetic gap.**

**Fixed in F0**, by P13's fix. `approvalLimit()` falls through to the person's own ceiling and
then their role's when there is no membership to answer, so the payments dashboard is capped by
the same number the contract screen uses. The honest description is now simply: *the ceiling
binds, and a membership can raise or lower it on the project it covers.*

### P20 — deleting a contract is not blocked when it has payments

`ContractShow::delete()` now needs `contracts.delete`, but nothing stops deleting a contract that
has been paid against — its payments go with it through the cascade, and the money that left the
company loses its record.

M10 refuses to delete an approved change order for exactly this reason, and M6 refuses to change
a locked budget. The same shape fits here: refuse the delete while the contract has payments, and
say so. Not done in M11 because it changes behaviour beyond the permission pass and deserves to
be a decision rather than a side effect.

### P21 — the schedule-of-values grid is `contracts.edit`, not its own grant

Rebuilding the schedule of values changes *when* money becomes payable, which is close to
`measure` and close to `edit`. M11 put the grid under `edit` (it is part of the contract's own
terms) and releasing an instalment under `measure` (it confirms the trigger happened).

Defensible, but somebody could be given `edit` to fix a typo in the notes and thereby be able to
restructure the payment schedule. If that matters, `contracts.schedule` is the fourth grant.


---

## Permissions module — parked during M12 (Documents), 2026-08-21

### P22 — the PDF controllers are still `auth` only *(the other half of N5)*

M12 closed the document repository's half of N5. The PDF controllers named in that notation are
untouched: `/quotations/{id}/rfq/pdf`, `/quotations/{id}/map/pdf`, the six report PDFs, the
contract schedule and measurement PDFs, the daily report PDFs, and the estimate and invoice ones.
All are behind `auth` and nothing else, so any signed-in person can fetch any of them by id.

Each of them belongs to a module that has had, or will have, its own pass. **M8 should have
guarded the quotation PDFs and did not**; M9's, M10's and M11's PDFs are in the same state.

The fix is one line per controller — resolve the record, ask the module's `view` ability against
it — and it is the same shape as `FileController::authorizeFile()`. Either later passes pick up
their own, or **F3 sweeps the lot**; recorded so it cannot be forgotten either way.

**Progress:** M13 guarded the meeting minute PDFs, M14 the daily report PDFs, M15 the estimate
and invoice PDFs, and **M17 the largest share** — all six company reports and the project and
job-site financial reports, each answering to the same grant as its screen.

**Two sets are left, and no pass is coming for them:** the quotation RFQ and comparison-map PDFs
(M8's) and the contract schedule and measurement PDFs (M11's). Both modules are finished. These
need picking up deliberately or **F3 sweeps them** — one line each, resolving the record and
asking the module's `view` ability.

### P23 — `scopeVisibleTo` resolves internal documents in PHP

`documents.see_internal` is a scoped grant, so the answer differs per project and a single WHERE
clause cannot express it. `Document::scopeVisibleTo()` therefore loads every internal document's
id, filters them through the resolver, and feeds the surviving ids back into the query.

That is fine at the sizes this application sees — internal documents are the exception, and the
resolver memoises per request — but it is a query that grows with the number of internal
documents rather than with the page being shown. If an install ever files thousands of them, the
answer is to resolve the person's projects once and compare `project_id` in SQL.

### P24 — the trash is one grant, not two

Delete, restore, purge and empty-trash all sit under `documents.delete`, which reproduces the old
`canDeleteDocuments()` exactly. Purging is irreversible and restoring is not, so they are
arguably different acts — `documents.purge` would be the fifth grant.

Not split, because nothing in the old behaviour distinguished them and this pass had enough
genuine changes in it. Recorded as a decision rather than an omission.


---

## Permissions module — parked during M13 (Tasks & Meetings), 2026-08-21

### P25 — a meeting's grants cannot be given per project

A meeting spans several projects through its items, so M13 asks its grants **without a scope**.
For somebody company-wide the role answers; for somebody confined the resolver checks whether any
of their memberships grants it.

The consequence: you cannot say "this person may run meetings on the Alpha project only". They
either run meetings or they do not. That is honest — the record genuinely has no single project —
but it is worth knowing before somebody tries.

If it ever matters, the answer is not a `project_id` on the meeting; it is to check the grant
against each **item's** scope as the agenda is built, which is a bigger change than a permission
pass.

### P26 — `Task::visibleTo()` reads memberships, not the resolver's answer

The filter builds a list of the project and job-site ids a confined person holds a membership on,
and matches `project_id` / `job_site_id` against it. It does **not** ask whether that membership
grants `tasks.view` specifically — the route guard has already established the person holds the
ability somewhere, and every per-task action re-checks properly through `taskInScope()`.

So the *list* can show a task on a project where the person holds a membership but not
`tasks.view`; opening it is refused. That is a cosmetic leak of a title, not of anything the task
contains, and closing it means resolving the ability per project rather than per membership —
which is the same shape as P23's problem in documents. Both would be fixed by one helper: "the
scopes on which this person holds ability X".

### P27 — a personal task belongs to nobody's project

A task with no `project_id` is answered by the role rather than by a scope, and appears in every
confined person's list. That is right — it is theirs, filed against nothing — but it means a
personal task is the one record in the application a confined person can always see. Recorded
because it is a deliberate hole in the confinement, not an oversight.


---

## Permissions module — parked during M14 (Daily Reports), 2026-08-21

### P28 — the project financial report is MySQL-only, so it has never been tested *(FIXED in M17, 2026-08-21)*

Closed by `PaymentScheduleService::monthBucket()`, which emits `strftime` on sqlite, `to_char` on
Postgres and the original `DATE_FORMAT` on MySQL. Both the project financial report and the
payment schedule report are now rendered by a test. **MySQL output is unchanged.** The original
note follows.


`ProjectFinancialReport` runs `DATE_FORMAT(due_date, '%Y-%m')` in its payment-schedule query.
That is MySQL-only: the screen 500s on sqlite, which is why nothing in the suite has ever
rendered it. The same class of problem as the `FIELD(priority, …)` found in M7, and the same
consequence — **MySQL-only SQL silently means "untested"**.

`strftime('%Y-%m', due_date)` is the sqlite equivalent, and the portable form is a `CASE` or
letting the application group the rows. It belongs to **M17**, which owns the reports; M14 left
it alone and rewrote the two bookkeeping tests that used to drive that route so they state the
fact instead of hitting it.

**Worth a sweep in M17 or F3:** `grep -rn "DATE_FORMAT\|FIELD(\|GROUP_CONCAT\|IFNULL" app/`
will find the rest.

### P29 — `temp_daily_reports/` stays open on purpose

Half-finished daily report uploads land there before the report exists, so there is no record to
answer for them — the same situation as `livewire-tmp/`. Both are left open in
`FileController::authorizeFile()`.

The exposure is real but small: the paths are random, the files are unreferenced, and a completed
report moves its images into `daily_reports/{id}/`. If it ever matters, the answer is to name the
uploader in the path and check it, not to try to resolve a record that does not exist yet.


---

## Permissions module — parked during M15 (Estimates & Invoices), 2026-08-21

### P30 — a nested component's mount must not require more than its parent's *(rule, learned the hard way)*

M15 guarded `EstimateSendEmail::mount()` with `estimates.send`. That panel is **embedded in the
estimate detail page**, so the guard took the whole detail page away from anybody who could only
read it — a 403 on a screen they were entitled to. Fixed by moving the guard to the embed
(`@can` around the `<livewire:…>` tag) and leaving it on the action.

**The rule: a child component's `mount()` may only ask for something its parent already
required.** Anything narrower belongs on the embed and on the action.

Every other nested component was checked at the time: the five under Access and System Settings
(`TemplateManager`, and the four settings panels) each ask for exactly the ability their parent's
mount already required, so none had the fault. Worth re-running that check in F3:

```
grep -rho "<livewire:[a-z0-9.-]*" resources/views/ | sort -u
```
then compare each child's mount guard against its parent's.

### P31 — `payments.refund` is company-wide, so it cannot be given per client

Refunding is `payments.refund`, which lives in the company-wide `payments` area. There is no way
to say "may refund this client's invoices but not that one's". Nothing suggests that is wanted —
refunds are a finance-office act — but it is recorded because `invoices.record_payment` has the
same property and the pair look like they might be scopeable.

### P32 — a paid invoice can still be deleted

`invoices.delete` refuses nobody once granted, including on an invoice that has been paid through
the pay link. The payments go with it.

M10 refuses to delete an approved change order and M6 refuses to change a locked budget for
exactly this reason; the same shape fits here — refuse while the invoice has payments, and say
so. Not done in M15 because it changes behaviour beyond the permission pass. **The equivalent
gap on contracts is P20; these two want the same answer.**


---

## Permissions module — parked during M16 (Reference data), 2026-08-21

### P33 — `subcontractor-documents/` is still served to anybody signed in

M16 guarded the subcontractor screens, but not the directory their documents live in.
`FileController::authorizeFile()` still has no branch for `subcontractor-documents/`, so any
signed-in person can fetch any subcontractor's paperwork — insurance certificates, licences — by
naming the path.

It was not closed in M16 because the files hang off `subcontractor_documents` rows whose owner is
a vendor, and vendors are a **company-wide** area: the honest guard is `vendors.view`, which both
seeded roles hold, so the branch would change nothing today. It is still worth adding, because
the moment somebody has `vendors.view` taken away the directory should follow.

One line in the map, the same shape as `expenses`. **F3 should close it along with the remaining
PDFs (P22).**

### P34 — the catalog is a money area with no masking *(SETTLED in F0, 2026-08-21)*

`catalog` is declared `money => true` because items carry a current cost, and nothing masks it.
`can_see_money` only reaches project and job-site scopes, and the catalog belongs to no project —
the same root as P13 and P19.

So a Site Team member with `can_see_money = false` sees no project totals but can read every
item's cost in the catalog. Recorded here because the `money` flag now claims something the
screen does not do.

**Settled in F0, and the answer was that the flag was the thing at fault.** `can_see_money`
hides ROLL-UPS, not records (M4, and the owner's own words) — so an item's own cost was never in
scope for masking, and the catalog is not broken. What was broken was `showsMoney()`, whose
docblock claimed the flag made an area "obey money masking"; it is a label meaning *this area puts
money on screen*, and it now says so and finally has a job — a **Money** chip on the permission
matrix, where before it was carried into the row data and ignored.

The real question underneath — *should this person be reading the company's price list at all?* —
is `catalog.view`, and F0 is what makes it answerable for one person. The finance switch itself is
now per-person too: an exception on `finance.view_amounts` takes money off one bookkeeper without
inventing a role for them.


---

## Permissions module — parked during M17 (Reports), 2026-08-21

### P35 — the company reports are not scoped, and probably should not be

Every one of the six reads the whole company: every project's expenses, every supplier's
invoices. They are company-wide areas, so a **confined** person holding, say, `reports.sales_tax`
would see figures from projects they cannot open.

Today that cannot happen — the six are administrator-only by seed, and administrators see
everything anyway. It becomes real the moment somebody grants one of them to a confined user,
which F1 makes possible.

Three options when it matters: leave them company-wide and say so on the screen; filter each
report by the reader's projects (the heaviest option — six different queries); or refuse the
grant to a confined user outright, which is the cheapest honest answer.

**Related to P6/P13/P19/P34 but not the same:** those four want a company-wide *grant* record.
This one is about what a company-wide *report* should show a confined reader.

### P36 — `reports.view` and `reports.export` are declared but unused

The area has eight actions. Six name a report; the other two — `view` ("Open reports") and
`export` ("Export and print") — are guarded nowhere. The sidebar group's `active` pattern matches
`reports.*`, so the group appears when any single report grant is held, and each report's PDF
uses that report's own grant rather than a shared `export`.

They are not harmful, but they are two toggles in the matrix that do nothing, which is the thing
the honesty rule exists to prevent. Either give them a job — `view` as a prerequisite for the
group, `export` as a second gate on every PDF — or take them out. **F3 should decide.**

Note that `project-report.export` **is** used, and does gate the project report's PDF; the unused
pair is on the company-wide `reports` area only.

### P37 — the dashboard's receivables and estimates are not narrowed by project

*Opened 2026-08-21 (M18).*

Every other figure on the dashboard is filtered through `visibleProjectIds()`, so a confined
person's Cash to Pay, Active Projects, purchase orders and cash-flow bars count their own
projects and nothing else. **Receivables and Open Estimates are not.**

That is deliberate rather than an oversight. `invoices` and `estimates` are company-wide areas —
an estimate belongs to a client, not to a project (M15) — and their index screens show every
record to anybody holding the grant. Narrowing the dashboard's version would make the summary
disagree with the screen it summarises, which is the worse of the two inconsistencies.

It only matters once F1 makes it possible to grant `invoices.view` to a confined person. **The
honest fix is the same one P35 wants**: decide whether a company-wide money area may be held by
a confined user at all. If the answer is no, this resolves itself and the narrowing is never
needed. Decide with P6/P13/P19/P34/P35, not on its own.

### P38 — a catalogue ability's name has no pt_BR

*Opened 2026-08-21 (M18).*

`config/permissions.php` carries a display name for every area and for the actions that need one
("Grant and revoke access", "See the company overview", "Suspend or reactivate"). None of them
are in `lang/pt_BR.json`, so the permission matrix and the template editor show English strings
to a pt_BR user — the only screens in the application that do.

The names come out of config rather than out of a Blade file, so `__()` has to be applied where
they are rendered, and the strings then have to be added. Roughly forty of them. **F3**, and it
is a half-hour job, not a design question.

### P39 — a purchase order's attachments can now be deleted by whoever can delete the order

*Opened 2026-08-21 (F2).*

`Attachments::deleteAttachment()` was a hard-coded `is_admin`. F2 held it to the **parent record's
own `delete` grant**, which is one coherent rule rather than six special cases.

For an expense, an income line, a requisition and a quotation that reproduces administrator-only
exactly, because all four are in the seeder's admin-only list. **`purchase-orders.delete` is
not** — both seeded roles hold it — so on a purchase order the act moves from "administrators
only" to "whoever may delete the purchase order itself".

That is defensible: somebody who may destroy the whole order may certainly remove a file from it.
It is recorded because it is a real change in behaviour on deploy, and because the alternative —
adding `purchase-orders.delete` to the admin-only list — would have been a much larger change
in the other direction, narrowing who may delete purchase orders at all.

**Decide in F3:** leave it, or give attachments their own grant (`attachments.delete`) so the six
kinds can differ.
