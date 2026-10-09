# Changelog — expense view modal crashed on close (2026-10-09)

Sentry **AVANTTI-CONSTRUCTION-6**: `Call to a member function ability() on null` from
`resources/views/livewire/project/partials/expense-history.blade.php`, five events across
two users on the job site screen between 2 and 9 Oct 2026. Reported by the owner the moment
the latest one landed.

## What happened

`closeExpenseModal()` on `JobSiteShow` and `ProjectShow` reset the expense id, the form
fields and the history, but **not `expenseModalMode`**. The modal body is rendered on every
request whether the dialog is open or not (`x-ui.modal` only hides it), so the render that
followed a close still took the `view` branch, now with `$viewingExpense = null`. The
history partial had been put behind `@can($viewingExpense->ability('edit_paid'), …)` in the
M4 permissions sweep and was the first thing in that branch to dereference the missing
record. Before M4 the partial only looked at `$expenseHistory`, which *was* reset, so the
stale mode went unnoticed.

The project screen carried the identical defect; Sentry had only seen it from the job site
screen because that is where the view modal is used in practice.

## What changed

- **`closeExpenseModal()` resets `expenseModalMode`** on both components, so a closed
  modal goes back to its `create` default with the rest of its state.
- **The history partial is guarded with `@if($viewingExpense)`**, so the one remaining way
  to reach the view branch with no record — the expense deleted by somebody else while this
  modal is open — renders an empty block instead of throwing.

No strings were added, so nothing for `pt_BR.json`.

## Test

`tests/Feature/Expense/ExpenseViewModalTest.php` opens and closes the view modal on both
screens and re-renders with the expense deleted underneath an open modal. All three cases
reproduce the production exception without the fix and pass with it.

## The other two open issues on the project

- **AVANTTI-CONSTRUCTION-5** — `SQLSTATE[HY000] [2002] Connection refused` from the queue
  worker's `queue:restart` cache lookup, six events on 30 Sep–1 Oct, none since. MySQL was
  unreachable for a moment on the server; nothing in the code to change. Resolved in Sentry.
- **AVANTTI-CONSTRUCTION-4** — Sentry's N+1 detector on the batch "pay today" action in
  `ContractPayments`, which inserts one `contract_payments` row per contract the user paid.
  That is the operation, not a leak; left for the owner to ignore in Sentry.
