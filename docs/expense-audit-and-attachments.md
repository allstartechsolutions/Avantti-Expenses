# Expense Audit Trail, Admin Editing & Attachments

Implemented July 2026.

## Overview

Three related features:

1. **Payment tracking** — every expense/installment records *who* marked it as paid.
2. **Admin editing of paid expenses** — admins can edit or revert expenses/installments that already have payments; every change is tracked in a history log.
3. **Attachments** — multiple image/PDF files can be attached to Expenses and Purchase Orders at any time (e.g. when the fiscal document arrives days after the purchase). Anyone can upload; only admins can delete.

## Database

| Migration | Change |
|---|---|
| `2026_07_17_100000_add_paid_by_to_expenses_and_expense_payments` | Adds nullable `paid_by` FK (users) to `expenses` and `expense_payments` |
| `2026_07_17_100001_create_expense_change_histories_table` | New `expense_change_histories` table |
| `2026_07_17_100002_create_attachments_table` | New polymorphic `attachments` table |

`expense_change_histories` columns: `expense_id` (cascade), `expense_payment_id` (null on delete, so history survives schedule regeneration), `action`, `changed_by`, `changes` (JSON diff `field => {old, new}`).

`attachments` columns: `attachable_type`/`attachable_id` (morph), `file_path`, `original_name`, `uploaded_by`.

## Who-marked-paid tracking

- `Expense::markAsPaid()` and `ExpensePayment::markAsPaid()` set `paid_by = auth()->id()` and log a `marked_paid` history entry.
- Creating an expense already in `paid` status sets `paid_by` via a `creating` hook.
- Reverting (see below) clears `paid_by` and `paid_date`.
- The "Paid by {name}" is displayed next to the paid date in the expense view modals (payment schedule rows and one-time paid info).

## Admin editing / reverting

- `Expense::isEditableBy(?User $user)` — normal rules (`isEditable()`) still apply for regular users; admins can always edit. Used in `ProjectShow`, `JobSiteShow` and the expense tables' edit buttons.
- `Expense::updateWithHistory(array $data)` — replaces plain `update()` in the edit flows. Records an `edited` history entry with an old→new field diff (money converted from cents). When an expense has paid installments (`hasLockedPayments()`), the payment structure (status, installment count, frequency, dates) is preserved — only descriptive fields, items, amounts and receipt can change, and the schedule is never regenerated/deleted.
- **Revert actions** (admin-only, `wire:confirm`ed, amber "undo" icon/link):
  - `unmarkExpensePaid` — paid one-time expense → `unpaid` (`Expense::unmarkAsPaid()`).
  - `unmarkPaymentPaid` — paid installment → `pending` (`ExpensePayment::markAsPending()` now clears `paid_date`/`paid_by` when reverting from paid).
- All payment status changes (`marked_paid`, `unmarked_paid`, `marked_overdue`, `marked_pending`) are logged regardless of who does them.
- `ProjectExpenses` mark-as-paid methods were routed through the model methods (previously raw `update()` calls that would bypass tracking). Its `deleteExpense` and `JobSiteShow::deleteExpense` now require admin, matching `ProjectShow`.

## Mark-as-paid with chosen date

Clicking **Mark Paid** (installment rows) or the green check (one-time expenses in tables) no longer marks instantly — it swaps to an inline date picker (default: today) with Confirm/Cancel, for bills paid before they were entered in the system. Implemented as `startMarkPaid($type, $id)` / `confirmMarkPaid()` / `cancelMarkPaid()` with `markPaidType`/`markPaidId`/`markPaidDate` state in `ProjectShow`, `JobSiteShow` and `ProjectExpenses` (the old `markExpenseAsPaid`/`markPaymentAsPaid` component methods were replaced). The Payment Dashboard already had this via its pay modal and is unchanged.

## Editable installment due dates

Unpaid installments show a small pencil next to the due date in the payment schedule (all three expense views). Clicking it opens an inline date picker with Confirm/Cancel — for negotiated postponements (common in BR). Handled by `ExpensePayment::changeDueDate()`:
- An **overdue** installment moved to today or later automatically returns to **pending** (parent expense status recomputed).
- Every change is logged in history as `due_date_changed` with old→new dates (and the status change, if any).
- Paid installments cannot have their due date changed (revert first).
Component state/methods: `startEditDueDate` / `confirmEditDueDate` / `cancelEditDueDate` in `ProjectShow`, `JobSiteShow`, `ProjectExpenses`.

## History UI

`resources/views/livewire/project/partials/expense-history.blade.php` — shared partial included in the three expense view modals (`ProjectShow` expense modal, `JobSiteShow` view modal, `ProjectExpenses` view modal). Visible to **admins only** (`@admin`). Shows action, user, timestamp and field-level old→new changes. Components expose it via a `$expenseHistory` array property loaded in `openExpenseViewModal`.

## Attachments

- Model: `App\Models\Attachment` (polymorphic `attachable`). Deleting the record deletes the file from storage.
- Relations: `Expense::attachments()`, `PurchaseOrder::attachments()`.
- Reusable Livewire component: `App\Livewire\Shared\Attachments` + `livewire/shared/attachments.blade.php`.
  - Usage: `<livewire:shared.attachments model-type="expense|purchase-order" :model-id="$id" :key="'...'.$id" />`
  - **Upload**: any authenticated user, `pdf,jpg,jpeg,png`, max 10 MB, stored on the private `local` disk (`expenses/` or `purchase-orders/`), served through `files.show`/`files.download` routes.
  - **Delete**: admin only (`AuthorizesAdmin` + `@admin`).
- Embedded in: Purchase Order show page (own card, available for any PO status) and the three expense view modals.
- The legacy single `receipt_path` on expenses/POs is unchanged and still displayed.

## Known limitations

- Admin edits of expense **line items** are not diffed individually; the history captures the resulting `total_amount` change.
- If an admin edits amounts on an expense whose installment schedule is locked, the schedule keeps its original amounts — correcting individual installment amounts after payment must be done by reverting the paid installment first.
