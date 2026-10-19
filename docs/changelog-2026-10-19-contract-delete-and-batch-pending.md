# Changelog — contract delete in a batch, batch rows that could not be found (2026-10-19)

Three Sentry issues on **avantti-construction** and one report from the users, all in the
contract / payment batch area.

## 1. Deleting a contract that is in a payment batch crashed — AVANTTI-CONSTRUCTION-7

**What happened.** `ContractShow::delete()` called `$contract->delete()` with nothing in
front of it. `payment_batch_items.contract_id` is a plain `constrained()` foreign key — no
cascade, rightly, since a batch row is a financial record — so MySQL refused the delete
(`1451 Cannot delete or update a parent row`) and the page threw. Three events from one admin
between 16 and 19 Oct 2026. Worse than the error: the contract file and the change-order files
were deleted from storage *before* the database refused, so each attempt left a contract that
still existed with its files gone.

**What changed.**

- **`Contract::deleteBlockers()`** — returns `['payment_batch_items' => n]` when the contract
  sits in any batch (pending, approved or rejected), `[]` otherwise. Same shape as
  `Equipment::deleteBlockers()`. A new `paymentBatchItems()` relation backs it.
- **`ContractShow::delete()` checks it first**, before any file is touched, and refuses with a
  flash: *"This contract is in a payment batch and cannot be deleted. Remove it from the batch
  first, or cancel the contract instead."*
- **The contract page shows that sentence in place of the Delete button** when a blocker exists,
  so the button is not offered for a delete that cannot happen. The server check stays — a
  stale page is not permission.
- pt_BR: *"Este contrato está em um lote de pagamento e não pode ser excluído. Remova-o do lote
  primeiro ou cancele o contrato."*

**What did not change.** The contract's own payments (`contract_payments`) still cascade with it,
as before. That contradicts *money blocks the delete; it never cascades* in `CLAUDE.md` and is
left for the owner to decide — see **CD1** in `docs/review-and-improvements.md`.

## 2. *Approve All Pending* looked contracts up one at a time — AVANTTI-CONSTRUCTION-8

`PaymentBatchEdit::approveAll()` ran the balance query
(`Contract::withSum(payments)->withSum(changeOrders)->find($id)`) once per pending row. It now
loads every contract in the batch in one `whereIn(...)->get()->keyBy('id')` and reads from that.
The validation and the payments made are unchanged.

`processApprovedItem()` still re-reads each contract with `Contract::find()` after inserting its
payment. That one stays per row on purpose: `updateStatusFromPayments()` must see the payment
just written, and a contract loaded before the insert would not.

## 3. "Pending (8)" but the rows were not on the screen — reported by the users

**What happened.** On the batch edit page the *Pending* card and the *Approve All Pending*
button count **every** pending item in the batch. The table, however, listed only contracts
that passed the screen's filters, so a pending item disappeared from the list when its contract

- was **paid or cancelled** after it was put in the batch (hidden unless *Show zero balance*),
- went **back to draft** (`Contract::committed()` excludes it),
- fell **outside the filters saved with the batch** (client, project, subcontractor, PM,
  supervisor, status),
- or was simply on **page 2** of the 50-row list.

That was more than confusing: *Approve All Pending* pays the hidden items too, so a user could
approve money they could not see, and could not reject a row that was not on the screen.

**What changed.** `PaymentBatchEdit::render()` now lists *(the committed contracts that pass the
filters)* **or** *(any contract already pending in this batch)*. The filters still decide which
other contracts are offered; they can no longer hide something the batch is about to pay. A
paid or cancelled contract shows with its usual paid/cancelled row treatment and its *Reject*
button, so it can be taken out of the batch. Pagination is unchanged — with more than 50 rows,
some may still be on the next page.

No strings were added for this one.

## 4. Closed and left open in Sentry

- **AVANTTI-CONSTRUCTION-6** (expense history `ability()` on null) — already fixed on 9 Oct, see
  `docs/changelog-2026-10-09-expense-view-modal-null.md`; no events since. Resolved.
- **AVANTTI-CONSTRUCTION-7** and **-8** — resolved with this change. The project has no
  releases configured, so Sentry marked them plainly *resolved*; an event before the deploy
  reopens them as a regression, which is expected.
- **AVANTTI-CONSTRUCTION-9** and **-4** — the N+1 detector on the `contract_payments` insert in
  *Approve All* and in *Pay today* on `/contract-payments`. One insert per payment made is the
  operation itself (a bulk insert would also bypass the model's cents mutator). Left open for
  the owner to ignore in Sentry.

## Tests

`tests/Feature/Contract/ContractDeleteBlockedByBatchTest.php`

- a contract in a batch is refused, stays in the database, and the page shows the reason
  instead of the button;
- a contract in no batch still deletes and redirects;
- *Approve All* on three rows makes no per-row balance lookup and pays all three;
- with a filter that matches nothing, a paid, a draft and an ordinary pending contract are all
  still listed, and a contract not in the batch is not.

Without the fixes, 3 of the 4 fail; with them, all pass. The full suite shows the same 9
failures as before the change (registration, profile, two-factor and four permission screens),
none in this area.

## Files

| File | Change |
|---|---|
| `app/Models/Contract.php` | `paymentBatchItems()`, `deleteBlockers()` |
| `app/Livewire/Contract/ContractShow.php` | `delete()` checks the blockers before any file cleanup |
| `resources/views/livewire/contract/contract-show.blade.php` | Reason shown in place of the Delete button |
| `app/Livewire/PaymentBatch/PaymentBatchEdit.php` | `approveAll()` loads contracts in one query; `render()` always lists pending contracts |
| `lang/en.json`, `lang/pt_BR.json` | The refusal sentence |
| `tests/Feature/Contract/ContractDeleteBlockedByBatchTest.php` | New |
