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
