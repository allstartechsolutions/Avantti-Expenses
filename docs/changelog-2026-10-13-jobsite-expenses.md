# Job site expenses run the project's expense code (13 Oct 2026)

## What was wrong

`/job-sites/{id}/expenses` had its own copy of the expense list and modal inside
`JobSiteShow`, written when an expense carried exactly one item on its header
(`expenses.item_name`, `quantity`, `unit_price`). Expenses have since moved to
line items (`expense_items`) and are filed on the shared create screen, which
leaves those header columns empty. So on the job site screen:

- the **Item** column was blank for every expense filed on the current screen;
- the **detail modal showed no items** — it never read `expense_items`;
- search looked only at the header item name and notes, not items or supplier;
- deleting an expense left its items, payments and receipt file behind.

The project screen (`ProjectExpenses`) showed all of it correctly, which is why
the two screens disagreed about the same records.

## What changed

- `JobSiteShow` uses `HandlesExpensePaymentActions`, the trait behind the
  project and company expense screens: one view modal, one set of payment
  actions, one delete. Scope is unchanged — `expenseInScope()` /
  `paymentInScope()` still confine every id to this job site, and every guard
  is the same `expenses.*` ability it was.
- The job site view renders `livewire.expense.partials.view-modal`; the list
  shows *Supplier / Items* like the project list, and search covers item names
  and supplier.
- The single-item create/edit modal was removed (~1,500 lines). Nothing could
  open it: *Add Expense* and *Edit* already went to the full screens.
- **Older single-item expenses** (no `expense_items` rows) show their header item
  as a line in the detail modal — on every screen that uses the shared modal.
- **Deleted-under-an-open-modal crash fixed** in the shared trait. It kept the
  expense as a public model property, which Livewire re-fetches with
  `firstOrFail()` on each request; the trait now keeps `viewingExpenseId` and
  resolves `viewingExpense` as a computed property that returns null once the
  record is gone. This also affected the project and company expense screens.

### Found in review (Codex, same day)

- `viewingExpenseId` is `#[Locked]` and the computed lookup asks for
  `expenses.view` / `company-expenses.view` itself. Without both, the id could be
  set from the browser and the modal body (which renders on every request)
  would print an expense to somebody holding only the job site overview.
- Every payment action reloads the open modal — status, schedule and history —
  through `fillViewModal()`, as the old job site handler did by reopening it.
  The project and company screens gain the same.
- *Overdue* flashes its confirmation again.

## Behaviour that moved

- Marking an installment paid from the job site modal no longer copies the
  expense's payment method onto the installment; it keeps the installment's own,
  as the project screen always has.
- Deleting from the job site list now also removes the expense's items,
  payments and receipt file, as the project screen does.

No migration, no permission change.

## Still open

`ProjectShow` keeps a legacy single-item expense modal of the same vintage
(`project/partials/expense-modal`). Its route no longer serves the expenses tab
(`ProjectExpenses` does); it should be checked for reachability and removed the
same way.
