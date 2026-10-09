# Changelog — vendors can be switched off (2026-09-30)

A vendor can now be **inactive**: a supplier the company stopped buying from, a subcontractor
it will not hire again, a duplicate waiting to be merged. Until now the only exits were to
delete the record (refused as soon as an expense, order or contract named it) or to leave it
in every picker for ever. Asked by the owner on 30 Sep 2026: *"a toggle to disable and
enable, and on the index a filter for that"*, then *"on the cards, active and inactive
numbers"*. Reference: the new section in `docs/vendor-unification.md`.

## What "inactive" means

- **The record is kept**, with every expense, purchase order, contract, quotation, document
  and employee that names it. Nothing is deleted, nothing is re-pointed.
- **It stays on the lists** (Vendors, Suppliers, Subcontractors) behind a status filter, with
  an *Inactive* chip under the company name and a muted row.
- **It stays in report and payment filters.** Reports look backwards; a batch pays a contract
  that already exists. Payment batches, contract payments, the payment-detail and expense
  reports and the company-expense vendor filter were left exactly as they were.
- **It is no longer offered to a new record.** The pickers that start something new — the
  supplier search on project expenses, company expenses, purchase orders, approvals,
  equipment and its maintenance; the subcontractor search on contracts; the catalog item
  supplier list; the quotation vendor suggestions — only show active vendors.
- **An edit form keeps its own vendor.** A catalog item or a maintenance record saved against
  a vendor that was later switched off still lists that one vendor in its dropdown
  (`activeOrCurrent()`), so a saved choice never vanishes from the form that holds it. The
  search-style pickers only search while nothing is selected, so they needed no special case.
- **Who switched it off, and when, is recorded** (`deactivated_at`, `deactivated_by`) and
  shown on the detail page; switching it back on clears both.

## Where the switch is

One action, `TogglesVendorActive::toggleActive($vendorId)`, mounted on five screens:

| Screen | Where the switch sits |
|---|---|
| Vendors list (`/vendors`) | New *Status* column, one switch per row |
| Suppliers list | New *Status* column |
| Subcontractors list | New *Status* column |
| Supplier detail page | Header, beside *Back* and *Edit* |
| Subcontractor detail page | Header, beside *Back* and *Edit* |

The switch is the shared `x-ui.toggle`, driven by `wire:click` with a `wire:key` that carries
the state (the pattern the component's own notes ask for). Somebody without the grant sees the
same state as a plain chip. A switched-off vendor's detail page also opens with a banner that
says what inactive means and who did it.

## The filter and the cards

- Every list has a **Status** select — *all*, *Active*, *Inactive* — carried in the query
  string (`?status=inactive`) and cleared by *Clear Filters*. The Suppliers list, which only
  had a search box, gained a `clearFilters()` action for it.
- The Vendors list shows the counts in the filter's options (*Active (12)*, *Inactive (3)*)
  and **each type card** (All vendors, Suppliers, Subcontractors, Both) shows its total with
  two chips under it: how many are active and how many are switched off. The six count
  queries that fed the cards became one grouped query (`VendorIndex::cardCounts()`).
- Empty states say why nothing matched: *No supplier is inactive. Every one of them can be
  picked for new records.*, and the equivalents for the search + status combination.

## Permissions

**No new ability.** Switching a vendor off is an edit of the record, not a delete, so the
switch answers to the existing `vendors.edit` — the same grant as the edit form and the
employee rows. It is guarded server-side on every screen (the `wire:click` behind a switch is
a public endpoint); hiding the control is only a courtesy. Reproduced first, then made
revocable: a role with `vendors.view` alone sees the chip and is refused the call.

## What changed

| File | What |
|---|---|
| `database/migrations/2026_09_30_100000_add_is_active_to_vendors_table.php` | **New.** `is_active` (default true, indexed), `deactivated_at`, `deactivated_by` (FK users, null on delete) on `vendors` |
| `app/Models/Concerns/HasVendorActiveState.php` | **New.** `active()`, `inactive()`, `activeOrCurrent($id)`, `activeState($filter)`, `activate()`, `deactivate($userId)`, `deactivatedBy()`, `getActiveLabel()` / `activeLabel()`, `activeStates()` |
| `app/Models/Vendor.php`, `Supplier.php`, `Subcontractor.php` | Use the trait; declare the two casts (a trait's casts are not merged by Eloquent) |
| `app/Livewire/Concerns/TogglesVendorActive.php` | **New.** The guarded `toggleActive()` with its flash messages and an `afterVendorToggled()` hook the detail pages use to reload their model |
| `app/Livewire/Vendor/VendorIndex.php` | `status` filter, `cardCounts()`, the toggle |
| `app/Livewire/Supplier/SupplierIndex.php` | `status` filter, `clearFilters()`, the toggle |
| `app/Livewire/Subcontractor/SubcontractorIndex.php` | `status` filter, the toggle |
| `app/Livewire/Supplier/SupplierShow.php`, `Subcontractor/SubcontractorShow.php` | The toggle; load `deactivatedBy`; refresh after a flip |
| `resources/views/components/vendor/active-state.blade.php` | **New.** Switch for an editor, chip for a reader |
| `resources/views/components/vendor/active-badge.blade.php` | **New.** The Active / Inactive chip |
| `resources/views/components/vendor/inactive-notice.blade.php` | **New.** The detail-page banner |
| `resources/views/livewire/vendor/vendor-index.blade.php` | Status filter with counts, card chips, Status column, Inactive chip, `wire:key` on rows |
| `resources/views/livewire/supplier/supplier-index.blade.php`, `subcontractor/subcontractor-index.blade.php` | Status filter, Status column, Inactive chip, empty states, `wire:key` on rows |
| `resources/views/livewire/supplier/supplier-show.blade.php`, `subcontractor/subcontractor-show.blade.php` | Header switch, banner, *Status* / *Switched off* / *Switched off by* rows in the information card |
| Pickers: `Catalog/CatalogItemCreate`, `Catalog/CatalogItemEdit`, `PurchaseOrder/PurchaseOrderCreate`, `PurchaseOrder/PurchaseOrderEdit`, `Project/ProjectShow`, `Concerns/ManagesExpenseForm`, `Approval/ApprovalForm`, `Contract/ContractCreate`, `Contract/ContractEdit`, `Equipment/EquipmentShow`, `Concerns/ManagesEquipmentForm`, `Concerns/ManagesQuotations` | `->active()` on the search or list; `activeOrCurrent()` on the two dropdowns that read a saved value; the "no suppliers yet" counts follow |
| `lang/en.json`, `lang/pt_BR.json` | 17 new strings, pt_BR in the same change. In sentences the vendor is *a empresa* (feminine: *inativa*); the chip beside the *Fornecedor* / *Subempreiteiro* chips keeps the shared masculine *Ativo* / *Inativo* |
| `tests/Feature/Permissions/VendorActiveStateTest.php` | **New.** Four tests: the audit fields are written and cleared; the grant is required on all five screens and a reader gets a chip; all three lists filter by status and the cards split their totals; an inactive supplier is missing from the approval form's search while `activeOrCurrent()` keeps it for its own form |
| `docs/vendor-unification.md` | New section *Active / inactive switch* |

## Decisions taken along the way

1. **Default view is everyone, not only the active.** The owner asked for a filter, not a
   hidden state; an inactive vendor is still a real company on the directory. The chip and
   the muted row make the state visible without a click.
2. **Payment batches keep offering inactive subcontractors.** A batch pays contracts that
   exist; refusing to pay a subcontractor because it was switched off after signing would be
   a bug. The same reasoning left the report filters alone.
3. **Merging does not touch the survivor's state.** Merging an inactive duplicate into a live
   vendor leaves the live one on.
4. **No confirmation dialog on the switch.** It is reversible in one click and destroys
   nothing; a `wire:confirm` on every flip would be friction without protection.

## Verification

- `VendorActiveStateTest` (4 tests, 55 assertions) and `VendorDirectoryTest` pass.
- Full suite: 1427 passed, 13 failed — the same 13 fail with these changes stashed (settings
  and registration pages missing a `layouts` hint path in this checkout, plus two contract
  payment history and two company-expense dashboard tests). None touches vendors.
- **Not yet done:** a browser walk of the three lists and two pages in both themes and on a
  phone with an inactive vendor present. Logged as VA1 in `docs/review-and-improvements.md`.
