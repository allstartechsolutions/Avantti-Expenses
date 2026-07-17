# Session Changelog — July 17, 2026

All changes delivered in one session; database migrations are additive only. Feature-level detail lives in the linked docs.

## 1. Purchase Order search fix

The PO list on the project page displays each PO as `#{id}`, but search only matched `po_number` (often empty), notes, and supplier — so searching the visible number returned nothing. Search now also matches the ID, tolerating a typed `#` prefix. (`app/Livewire/Project/ProjectPurchaseOrders.php`)

## 2. Payment tracking — who marked it paid

`paid_by` columns on `expenses` and `expense_payments`, set in every mark-paid path (including expenses created already-paid) and cleared on revert. Shown as "by {name}" next to paid dates. See `docs/expense-audit-and-attachments.md`.

## 3. Admin editing of paid expenses + full change history

- Admins can edit paid expenses/installments (`Expense::isEditableBy()`); regular users keep the existing locks.
- Admin-only revert actions: paid one-time expense → unpaid; paid installment → pending.
- New `expense_change_histories` table records every payment status change and every edit (field-level old → new diffs), with user and timestamp. History section (admin-only) in the expense view modals.
- Payment structure is protected: once any installment is paid, the schedule/status can't be clobbered by an edit (`Expense::updateWithHistory()`).
- Fixed on the way: `ProjectExpenses` marked payments paid with raw updates (bypassing tracking); expense delete on the Job Site and Project Expenses pages didn't require admin (the project page did).

See `docs/expense-audit-and-attachments.md`.

## 4. Attachments for Expenses and Purchase Orders

Polymorphic `attachments` table + reusable `<livewire:shared.attachments>` component. Anyone can upload image/PDF (10 MB, private disk) at any time — including approved POs and paid expenses (BR case: the fiscal document arrives later). Delete is admin-only. Embedded on the PO show page and the three expense view modals. The legacy single receipt field is unchanged. See `docs/expense-audit-and-attachments.md`.

## 5. Mark-as-paid with chosen date

Marking an expense/installment paid now shows an inline date picker (default today) with Confirm/Cancel — for bills paid before they were entered. The chosen date is stored as `paid_date`. Applies everywhere marking happens (Project, Job Site, Project Expenses pages; the Payment Dashboard already had this). See `docs/expense-audit-and-attachments.md`.

## 6. Editable installment due dates

Unpaid installments have a pencil next to the due date (all three payment schedule views) — inline date picker for negotiated postponements. An overdue installment moved to today or later automatically returns to pending; every change is history-logged (`due_date_changed`). Paid installments are locked. See `docs/expense-audit-and-attachments.md`.

## 7. Accounts Payable — hide zero-balance subcontractors

"Show Zero Balance" toggle on the Subcontractor Payment Summary (default off = fully paid subs hidden; overpaid/negative balances always visible). Same switch pattern and property name as Contract Payments. URL-synced.

## 8. NEW report: Payment Details

`reports/payment-details` — the cronograma with line-level detail: one row per individual payment (each installment separately, one-time expenses, contract payments, open contract balances dated by contract end date). True period amounts, KPI tiles, Detail / By Project / By Vendor tabs, full filters, CSV + PDF. See `docs/payment-detail-report.md`.

## 9. Expense Report fixes

- **Bug fix (production-visible):** installment expenses' Paid/Outstanding figures were 100× too small (`ExpenseReportService::normalize()` divided dollar sums by 100 again). Totals for installment expenses will (correctly) change after deploy.
- On "Payment due date" basis, the Detail tab (web/CSV/PDF) now shows and sorts by the due date (header switches to "Due Date"); expense-date basis unchanged. See `docs/expense-report.md`.

## 10. Translations

All new strings added to `lang/pt_BR.json`, plus previously untranslated report UI strings (Export CSV, tab names, quick-date buttons, etc.).

## Migrations (all additive)

| Migration | Change |
|---|---|
| `2026_07_17_100000_add_paid_by_to_expenses_and_expense_payments` | `paid_by` FK on both tables |
| `2026_07_17_100001_create_expense_change_histories_table` | audit table |
| `2026_07_17_100002_create_attachments_table` | polymorphic attachments |

## Verification

Every feature was exercised end-to-end via tinker against the local database (payment flows, history diffs, structure locking, file cleanup, report figures with seeded expense + contract data); all Blade templates compile; translation JSON validated. The local DB has no purchase orders or production contracts, so the PO attachments card and report tables deserve a browser pass before commit.
