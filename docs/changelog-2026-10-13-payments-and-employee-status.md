# Payment batches show change orders and the contact; active-only pickers; employee on/off (13 Oct 2026)

From the client's change list (`docs/Alteracoes Expenses Avantti.docx`), items 4, 5, 7 and 8.
Items 3 and 6 (*"seleção por subcontractor e jobsite"* on the batch and Contract Payments
screens) are on hold until the client says what they mean: both screens already filter by
job site and by subcontractor.
*Update 15 Oct 2026:* they meant grouping — done for the batch screens, see
`changelog-2026-10-15-payment-batch-grouping.md`.

## 4. The batch screen shows change orders (back charges) like Contract Payments

The batch edit screen added each contract's change orders into *Amount* without saying so:
CTR-0330 showed $10,900 with no trace of its −$100 back charge. It now matches
`/contract-payments`:

- **Amount** is the contract's original amount.
- A **Change Orders** column shows the net (+ green / − red) with a toggle that opens a sub-row
  listing each one: date, title, description, amount.
- The **Total** row gains the Change Orders sum; Amount is now the original total there too.
  Balance is unchanged (amount ± change orders − paid).

Display only — approving a change order still happens on the contract.

## 5. The batch screen shows the subcontractor's contact

The contract's contact (employee) appears under the company name, as on Contract Payments.

## 7. Subcontractor dropdowns offer active subcontractors only

On Contract Payments, the batch edit screen and the new-batch form. A subcontractor already
chosen (in the filter, or the one a batch was made for) stays in the list after it is switched
off. Contracts of inactive subcontractors are still listed under *All* and can still be paid —
only the dropdown changed. This reverses a line in `vendor-unification.md`, updated.

## 8. Employees can be switched off

Directory › Vendors › a subcontractor › **Employees**:

- **Status** column with an Active / Inactive switch (`vendors.edit`; a chip for anyone else),
  and *Since :date by :name* under an inactive employee.
- **All / Active / Inactive** chips with counts; the header and overview card count inactive ones.
- Independent of the employment dates — the existing *Former* badge (end date passed) is unchanged.
- **New contract:** the *Contact (Employee)* dropdown lists active employees only, and saving an
  inactive one is refused (validation, not just the dropdown).
- **Editing a contract** that already names an inactive employee keeps them in the dropdown,
  marked *(Inactive)*, and saves without complaint.

## Files

| File | What |
|---|---|
| `database/migrations/2026_10_13_110000_add_is_active_to_subcontractor_employees_table.php` | **New.** `is_active` (default true, indexed), `deactivated_at`, `deactivated_by` |
| `app/Models/SubcontractorEmployee.php` | Casts, default, `deactivatedBy()`, `active()`, `activeOrCurrent()`, `activate()`, `deactivate()` |
| `app/Livewire/Subcontractor/SubcontractorShow.php` | `toggleEmployeeActive()`, `$employeeStatusFilter`, `employeeCounts` |
| `resources/views/livewire/subcontractor/partials/employees-tab.blade.php` | Status column + switch, chips, filtered empty state, row `wire:key` |
| `resources/views/livewire/subcontractor/subcontractor-show.blade.php` | Counts read the whole list; the delete dialog's employee line translated |
| `app/Livewire/Contract/ContractCreate.php`, `ContractEdit.php`, `contract-edit.blade.php` | Active employees only / active or current; validation |
| `app/Livewire/PaymentBatch/PaymentBatchEdit.php` + blade | Change Orders column, sub-row, totals; contact under the company; `toggleChangeOrders()` |
| `app/Livewire/Contract/ContractPayments.php`, `PaymentBatch/PaymentBatchCreate.php` | Active subcontractors in the dropdown |
| `lang/en.json`, `lang/pt_BR.json` | 7 strings |
| `tests/Feature/Contract/PaymentBatchChangeOrdersAndContactTest.php`, `tests/Feature/Subcontractor/EmployeeActiveStateTest.php` | **New.** 7 tests |

## What moved on production

- Every existing employee is active after the migration; nothing changes until someone flips one.
- The batch screen's *Amount* column and total now show the **original** amount, with change
  orders beside it. Anyone used to reading the adjusted figure there will see a different
  number; Balance is the same as before.
- The subcontractor dropdowns on the payment screens lose inactive subcontractors.
- No permission changes: the switch uses `vendors.edit`, which already guards adding, editing
  and deleting employees.

## Deploy

`php artisan migrate`.
