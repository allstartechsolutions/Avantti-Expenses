# Back charges

A back charge (BC) is money a subcontractor owes Avantti — damage, rework, cleanup — taken
out of what Avantti pays them. Requested in the November 2026 change requests (batch items 4,
5, 10 and 11, `docs/changes_requested/november-2026-tracker.md`). Branch `back-charges`.

**Status: built and reviewed 9 Nov 2026.**

## Decisions (6–9 Nov 2026)

- **Change orders stay exactly as they are.** A BC is its own record, not a negative change
  order.
- **Against a company or one of its employees.** Either the subcontractor company or a
  `SubcontractorEmployee` of that company.
- **Origin lot is optional.** It records where the problem happened, for reference later. A BC
  is often deducted from a contract on a different site.
- **Only where they worked** (9 Nov 2026): the job site and lot offered are those where the
  subcontractor — or, once chosen, the employee — has a contract (any status but draft). Checked
  again on save; an edited back charge keeps the place it already names.
- **Deducted wherever a sub is paid**: payment batch lines and direct contract payments
  (`/contract-payments` and "record payment" on the contract page).
- **Deduct all or part.** Payments are rarely the whole contract; each payment takes as much of
  a BC as the user chooses, and the rest stays open for later payments.
- **A deduction lowers the contract it is taken from.** "Pay $1,000, deduct $300 of BC-0007,
  write a $700 check": the contract owes $1,000 less.
- **As simple as possible.**

## How it reads on screen

```
Pay                       $1,000.00
Back charge BC-0007       − $300.00    remaining $500 → $200
───────────────────────────────────
Check amount                $700.00
```

On the contract:

| | |
|---|---|
| Contract | $10,000.00 |
| Change orders | + $0.00 |
| Back charges deducted | − $300.00 |
| **Adjusted contract** | **$9,700.00** |
| Paid | $700.00 |
| **Balance due** | **$9,000.00** |

The balance falls by the full $1,000; "Paid" shows the cash that actually went out.

## Data

`back_charges`
| column | |
|---|---|
| `number` | `BC-0001`, generated like contract numbers |
| `subcontractor_id` | required |
| `subcontractor_employee_id` | nullable; must belong to that subcontractor |
| `project_id`, `job_site_id` | nullable: the origin, for reference only |
| `amount` | cents |
| `date`, `reason`, `description` | |
| `created_by`, timestamps | |

Files go through the existing attachment component (`file-drop`).

`back_charge_deductions`
| column | |
|---|---|
| `back_charge_id` | |
| `contract_id` | the contract the money was taken from |
| `payment_batch_item_id` | nullable, cascade: taken in a batch (deleting the line deletes its deductions) |
| `contract_payment_id` | nullable, cascade: the payment it was taken from (set when a batch line is approved) |
| `amount` | cents, > 0 |
| `created_by`, timestamps | |

**Remaining** = `amount` − sum of deductions. A BC is *open* while remaining > 0 and
*fully deducted* at 0. There are no other statuses: nothing is "carried over", whatever is not
deducted simply stays open.

## The money

**A payment is stored gross; its deduction sits beside it.** Pay $1,000 with $300 deducted:
the `contract_payments` row says $1,000 and one `back_charge_deductions` row says $300 against
it. The check is the difference.

Why gross: installments, measurements, retention and the cost-code split all read the payment
amount. Stored gross, an installment of $1,000 paid with $700 cash + $300 BC reads as fully paid
and the retention maths does not change. Stored net, each of those formulas would need its own
correction.

What the screens show is the same either way. Per contract, with **B** = back charges deducted
(deductions whose payment exists):

| figure | formula | example |
|---|---|---|
| Adjusted contract | amount + change orders − B | $9,700 |
| Paid (cash) | payments − B | $700 |
| Balance due | adjusted − paid = amount + change orders − payments | $9,000 |

Balance due needs **no change**: B cancels out. Only the two figures read by people move:
the contract's cost (adjusted) and the cash that left the bank (paid).

- **One rule for "taken":** a deduction counts against the contract once it has a
  `contract_payment_id`. A direct payment sets it on save; a batch line sets it when the line
  is approved and its payment is created. Deleting the payment deletes its deductions, and the
  BC reopens.
- **Reserved while pending:** a deduction on a pending batch line already lowers the BC's
  remaining, so two batches can't take the same dollars. A rejected line's deductions are
  released (deleted).
- **On the model**, `getAdjustedAmount()` and `getAmountPaid()` keep their meaning (gross:
  amount + change orders, and payments as stored), because installments, measurements,
  retention and the status maths are measured against them. Beside them:
  `getBackChargesDeducted()` (B), `getNetAmount()` (adjusted − B, the contract's cost) and
  `getCashPaid()` (paid − B). Balance due is unchanged. On a payment and a batch line,
  `getNetAmount()` is the check.
- **In SQL**, `Contract::scopeWithBackChargesDeducted()` adds `back_charges_deducted_cents`, and
  the model methods read it, so a list needs no query per row. Every screen and report that
  shows a contract's cost or the cash paid switches to the net figures: `ProjectFinancials`,
  the job site and lot overview and financial reports and PDFs, `AccountsPayableService`, the
  batch view and PDF, Payment Detail, the payment dashboards and the worker page. Tests pin
  each one.
- The saving lands on the **lot of the contract it was taken from**; the BC's origin lot is
  only a label.
- Guards: a deduction can't exceed the BC's remaining amount, and a line's deductions can't
  exceed the line (check ≥ $0). Both are checked server-side inside the save transaction, with
  the BC row locked (`lockForUpdate`), so two people paying at once can't deduct the same
  dollars twice.

## Screens

1. **Back Charges list** (Payments menu): number, sub / employee, origin lot, amount,
   deducted, remaining, status; filters (open / fully deducted, sub, lot); CSV; create in a
   full-page modal.
2. **Back charge page**: everything it knows, plus each deduction (date, contract, batch or
   payment, amount, by whom), the files and the audit facts. Edit, plus delete while nothing has
   been deducted. Lowering the amount below what's already deducted is refused. "Waive the
   rest" = edit the amount down to what's been deducted, with a reason.
3. **Payment batch (edit)**: under each line, the sub's open BCs (company ones, plus the
   employee's when the contract names that employee), each with a "deduct" box and a
   "deduct all" shortcut; the net check updates as you type. The batch shows the gross, the
   deductions and the net totals.
4. **Batch view, PDF and CSV**: each line shows gross, BC number and reason, and net check.
5. **Contract Payments and the contract page's "record payment"**: the same deduct panel.
6. **Contract page**: a "Back charges deducted" line beside change orders, and a list of the
   deductions taken from it.
7. **Subcontractor page**: open BCs and their remaining total.
8. **Lot / job site page**: BCs that originated there (reference).

## Permissions (docs/permissions-for-new-modules.md)

A new `back-charges` area, level `global`, `money => true`: `view`, `create`, `edit`, `delete`,
`deduct`. `deduct` also needs the grant of the payment screen it is used on. Like every other
module it is granular: **seeded admin-only** (`ADMIN_ONLY_ABILITIES`) and grantable to any role
from the access screens (decided 9 Nov 2026). `swept => true` in the same change as the guards,
filters and tests.

## Settled (9 Nov 2026)

- **Existing negative change orders stay as they are.** Nothing is converted or moved.
- **Access:** admins only to start, changeable per role on the access screens like every other
  area.

## Stages

1. Migrations, models, the deduction service (`App\Services\BackChargeDeductions`), the
   contract figures, tests (`tests/Feature/BackCharge/BackChargeMoneyTest.php`). Report sweep to the net figures: `ProjectFinancials`, lot overview,
   job site and lot financial reports (screens and PDFs, with an "incl. −$X back charges" line
   per contract), Accounts Payable (subcontractor summary, outstanding contracts, cash paid),
   Payment Detail (the check, each back charge listed under it, a CSV column), the dashboard
   cash-out chart, the worker page. **Done 9 Nov 2026.** Left for stages 3 and 4, where the deduct
   panel lands: the batch edit screen and Contract Payments (they show balance, which is already right).
2. Back Charges list and page, permissions. **Done 9 Nov 2026.**
   - `BackChargeIndex` (`/back-charges`, under Payments in the menu): cards (open, left to
     deduct, deducted, fully deducted), search / status / subcontractor filters, CSV, delete
     from the list while nothing is deducted.
   - `BackChargeShow` (`/back-charges/{id}`): the numbers with a progress bar, every field,
     the deductions (contract, batch or payment, deducted or pending approval, by whom),
     photos and documents (`shared.attachments`, directory `back-charges/`, served by
     `FileController` on `back-charges.view`), created / updated by.
   - One full-page form for create and edit (`ManagesBackChargeForm`): the employee must
     belong to the company and the lot to the job site; once something is deducted the
     amount cannot go below it and who owes it cannot change. "Waive the rest" = lower the
     amount to what was deducted.
   - Permissions: area `back-charges` (view, create, edit, delete), `swept`, admin-only by
     seed. Administrators bypass the ability tables, so nothing is needed on deploy.
     `deduct` is declared in stage 3 with the screen that enforces it.
   - Tests: `tests/Feature/Permissions/BackChargesTest.php`.
3. Deductions in payment batches (edit, approve, view, PDF, CSV). **Done 9 Nov 2026.**
   - Edit: an orange "open back charges" tag on rows whose subcontractor owes; once an amount
     is typed, a panel under the row lists them with a Deduct now box, an All shortcut (what
     is left, capped at the payment) and the check. Saved with the draft
     (`syncForBatchItem`), taken on approval, given back on reject, reject all / selection
     and cancel. Paid column shows cash; Change Orders shows "−$X back charges"; the Total
     Amount card and the totals row show back charges and checks.
   - Without `back-charges.deduct` the panel is hidden and the line is saved around its
     deductions (never through them); the amount cannot go below what it deducts.
   - View / PDF / CSV: each line lists its deductions and the check; CSV columns
     "Back charges deducted" and "Check amount", totals rows for both.
   - Tests: `tests/Feature/BackCharge/BackChargeBatchTest.php`.
4. Deductions on Contract Payments and the contract page. **Done 9 Nov 2026.** The same panel
   on `/contract-payments` (rows paid in one transaction: a bad deduction pays nothing); a
   back-charge block in the contract page's Record Payment modal. Retention releases take no
   deductions. Paid / adjusted / summary cards / CSV / PDF read net. Tests:
   `BackChargeContractPaymentsTest.php`.
5. Contract, subcontractor and lot panels. **Done 9 Nov 2026.** Contract: "Back charges
   deducted" beside the change orders with each one listed, Amount Paid in cash, each payment
   shows its deductions and check. Subcontractor: a Back Charges card (open, left to deduct).
   Job site and lot overviews: the back charges that started there. A subcontractor or an
   employee with back charges cannot be deleted; merging vendors moves them. Tests:
   `BackChargePanelsTest.php`.
6. Review and improvements; client how-to doc (`docs/changes_requested/instructions`, items 4,
   5, 10, 11; demo data `capture/seed_demo_back_charges.php`). **Done 9 Nov 2026.**
   - Found on the screens: the deduct panel showed under every contract of the subcontractor
     and its boxes sat off-screen. It now opens once an amount is typed (an orange tag on the
     row says back charges are open) and is held to `max-w-4xl`.

### Codex review, 9 Nov 2026

Six findings, all confirmed and fixed, pinned in `tests/Feature/BackCharge/BackChargeReviewTest.php`:

- **P1** the remaining amount is read with a locking `SUM … FOR UPDATE` under the back-charge
  lock: under MySQL's REPEATABLE READ a plain read saw the snapshot from before the lock and
  two payments could take the same dollars.
- **P1** a stale batch screen saved after a line was approved turned it back to pending and
  rewrote its deductions (a $700 check reported as $1,000). `saveDraft()` now skips lines that
  are no longer pending — this also closes the older half of the bug, the line flipping back
  to pending — and `syncForBatchItem()` refuses anything but a pending line with no taken
  deduction.
- **P2** a contract given another subcontractor or employee after its line was saved: approval
  re-checks every reserved back charge against the current payee (`payeeError()`), on single
  approve, approve all / selection, and inside `takeForApprovedItem()`.
- **P2** the "of $X charged in all" subtitle and the subcontractor card's "left to deduct" are
  roll-ups and now follow `can_see_money`.
- **P2** a back charge nothing was deducted from can be waived: an edit may lower the amount to
  zero (status **Waived**); a new one still needs an amount.
- **P2** the Paid column sorts by the cash it shows, not the gross.

### Testing by hand

`php artisan db:seed --class=BackChargeTestSeeder` (refuses on production; additive, a second
run does nothing). Every record starts with "Test". Password for all three users:
`TestPass!2026`.

| User | Role | Try |
|---|---|---|
| `test-admin@avantti.test` | admin | everything, including delete |
| `test-pm@avantti.test` | Test PM (back charges) — manager's abilities + payments + back charges view / create / edit / deduct | create and deduct; no delete buttons |
| `test-manager@avantti.test` | manager as seeded | no Back Charges menu, no deduct panel, pays normally |

Job site **Test — Bayside Commons** (Lots 1–4); subcontractors **Test Drywall Co** (employees
Ana Souza, Bruno Costa), **Test Electric LLC** (Carla Dias), **Test Plumbing Inc**.

| Back charge | State | Where to look |
|---|---|---|
| Damaged window frame, $1,500, Drywall, Lot 2 | open | offered on every Test Drywall payment |
| Lost company ladder, $400, Drywall — Ana | open, employee | offered only on CTR-T01 (Ana's contract) |
| Cleanup after drywall, $1,000, Drywall | $600 taken from a $3,000 direct payment on CTR-T02 (check $2,400) | contract CTR-T02, Payment Detail |
| Cut conduit repair, $750, Electric — Carla | fully deducted in the approved batch "Test — Week 44" ($2,000 line, check $1,250) | batch view / PDF / CSV, CTR-T04 |
| Missed inspection fee, Electric | waived (amount 0) | the list's status |
| Water damage under sink, $500, Plumbing | set aside on a pending line of "Test — Week 46 (draft)" | Pending approval; reject the line and it reopens |

"Test — Week 46 (draft)" also has CTR-T01 pending at $3,000 with nothing deducted: open the
batch, take the ladder and part of the window frame, save, approve. CTR-T03 runs on three
released $5,000 installments: pay one with a back charge on the contract page and it still
reads as fully paid.

### Deploying

`php artisan migrate` (two tables). Nothing to seed: administrators bypass the ability tables;
other roles get the area from the access screens.
