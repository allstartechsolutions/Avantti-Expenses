# Contract change tracking and status follows the price — 29 Sep 2026

## What happened

A contract was paid in full (status **Paid**), then its price was raised on the
edit screen. It kept saying **Paid** with a balance due, and nothing recorded who
changed the price or what it had been.

## Causes

1. `ContractEdit::save()` never re-derived the status. Payments and change orders
   called `Contract::updateStatusFromPayments()`; an edit of the amount did not.
2. `updateStatusFromPayments()` itself was wrong when nothing was paid: it set
   **Completed**, so adding a change order to an active contract with no payments
   marked it Completed. It also acted on drafts.
3. There was no audit trail of the contract's terms — only of its status.

## What changed

### Status follows the money
- `updateStatusFromPayments(?reason)` rewritten (see `docs/contract-module.md`):
  paid in full → Paid, something paid → Partially Paid, nothing paid → the work
  status is kept (or restored from the history when payments were removed).
  Drafts and cancelled contracts are left alone.
- The contract edit re-derives the status **when the amount changed** — not on
  every save, so a status set by hand (a contract settled outside the system) is
  not undone by editing its notes.
- Each automatic move is written to the status history with a reason naming its
  cause (amount changed / change order / change order deleted), translated.

### Change history (`contract_change_histories`)
Recorded, each with who and when, and the status move it caused:
- **Contract edited** — every field that moved, old → new: subcontractor, contact,
  job site, dates, amount (with the difference), retention, notes, contract file,
  cost-code allocations.
- **Change order added / updated / deleted** — its title, date, amount, cost code,
  description; the fields that moved on an update.
- **Payment deleted** — amount, date, method, reference. A deleted payment used to
  leave no trace at all.
- **Status corrected** — written by the reconcile command below.

Shown on the contract page in a **Change History** card below the change orders
(ten entries, then *Show all*), with an empty state.

### Edit screen: effect on payments
When the contract has payments or change orders, the Financial card shows change
orders, adjusted amount, amount paid and balance due (or **Overpaid**) live as the
amount is typed, and warns when saving will change the status. The contract page
also shows **Overpaid** instead of a negative balance due.

### Fixing contracts already wrong
```
php artisan contracts:reconcile-status        # lists mismatches, changes nothing
php artisan contracts:reconcile-status --fix  # corrects them
```
Only contracts **with payments recorded** are corrected; each correction goes to
the status history (by "System") and to the change history. A contract marked Paid
by hand with no payment recorded is listed as *review by hand* and never changed.

## Permissions
No new ability. The history is part of the contract page (`contracts.view`);
the actions it records keep their own guards (`contracts.edit`,
`contracts.delete`, `contracts.unpay`). Money in the history goes through
`<x-ui.money>`.

## Not changed (noted for review)
- The manual **Change Status** modal still lets a Completed contract be marked
  Paid with a balance due. That is how a contract settled outside the system is
  closed, so it stays; the next price change or change order will re-derive it.
- Payments deleted from a payment batch (`PaymentBatchEdit`) re-derive the
  status but are not yet written to the contract's change history.
- Contracts created before this change have no history of earlier edits.

## Tests
`tests/Feature/Contract/ContractChangeTrackingTest.php`.
