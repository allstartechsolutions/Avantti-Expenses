# Contract payment tables: sorting, totals row, default method — 3 Oct 2026

Covers the two screens that list contracts with a pay column:
**Contract Payments** (`/contract-payments`, `ContractPayments`) and the
**payment batch** screen (`/payment-batches/{id}/edit`, `PaymentBatchEdit`).
Both now share their table logic through one concern,
`app/Livewire/Concerns/SortsContracts.php`, so they cannot drift apart.

## Sorting on the batch screen

The batch table sorts exactly like Contract Payments already did: click
**Job Site / Lot**, **Contract #**, **Amount**, **Paid** or **Balance**; click
again to reverse.

- Money columns start with the largest figure, text columns with A.
- Lots sort in natural order (*Lot 2* before *Lot 10*); project-level contracts
  (no job site) always come last, in either direction.
- The sort is in the URL (`?sort=balance&dir=desc`). An unknown column is
  ignored — the field is never used to build SQL.
- With no sort chosen, the old project / job site grouping is kept.

**Why the batch screen sorts differently under the hood.** Contract Payments
shows every contract on one page, so it sorts the loaded collection. The batch
table is paginated (50 per page), and sorting only the page on screen would give
the wrong order. `PaymentBatchEdit::sortedPage()` sorts the whole filtered list
on its sums alone (job site + two `withSum`s, no heavy relations), slices out the
current page's ids, and only then loads that page with everything the row needs.
Paid and balance are derived per row and lots need a natural sort, which is why
this is not an `ORDER BY`.

The sortable header is the existing partial
`resources/views/livewire/contract/partials/sort-header.blade.php`, now used by
both tables.

## Totals row

Both tables end with a **Total** row: the number of contracts listed, then the
sum of each money column.

| Screen | Columns totalled |
|---|---|
| Contract Payments | Amount, Change Orders, Paid, Balance |
| Payment batch | Amount (adjusted), Paid, Balance |

- Worked out on the server by `SortsContracts::contractTotals()`, from the same
  sums the rows use; balance = amount ± change orders − paid.
- On the batch screen the totals cover **every filtered contract on every page**,
  not only the 50 on screen.
- The row is hidden when the table is empty (the empty state shows instead).
- Totals are formatted like the summary cards already on these screens
  (`Number::currency`), so the row is visible exactly when those cards are. They
  do not go through `<x-ui.money rollup>`; moving these screens to it should move
  the cards and the row together.

## Running sum of the pay column

Under **Pay Today** (Contract Payments) and **Batch Amount** (batch screen) the
totals row shows a running sum and a count — "3 payments entered" — that moves
with every keystroke. It is computed in the browser from `$wire.payAmounts` by
`resources/views/livewire/contract/partials/pay-total.blade.php`, formatted with
`Intl.NumberFormat` in `app.locale` / `app.currency`.

- It sums **every amount entered**, not only the visible rows: that is what
  *Process Payments* / *Save Draft* acts on. On the batch screen this includes
  pending items saved on other pages.
- Approved batch items leave the sum (they are no longer editable); the
  **Approved** card above the table shows them.
- To make the sum live, the Pay Today input on Contract Payments changed from
  `wire:model.blur` to `wire:model` — the binding the batch screen already used.
  Amounts now reach the server with *Process Payments* instead of on each blur.
  Each row keeps its `wire:key` (see `changelog-2026-09-16-livewire-row-keys.md`).

## Payment method defaults to Check

Every row on both screens starts with **Check** selected; the blank *Select…*
option is gone. The default is one constant:
`ContractPayment::DEFAULT_METHOD = 'check'`.

- Contract Payments: rows return to Check after *Process Payments*.
- Batch screen: *Save Draft* stores Check unless something else was chosen.
- A pending batch item saved earlier with no method shows Check, and approving
  it records a Check payment — the payment matches what the screen showed.

**Behaviour change.** On the batch screen a method alone no longer makes a row a
batch item: a row needs an amount, phase, notes or a *Pays* target. Before, a
method with nothing else saved an empty item; with every row pre-set to Check
that rule would have put every contract on the page into the batch. A pending
item whose only content was a method is removed on the next *Save Draft*.

## Phase field width (batch screen)

The **Phase** input is twice as wide on desktop — `lg:w-56` (224px) from 1024px
up, `w-28` (112px) below, so the table does not widen on tablets and phones.

## Permissions

No new ability. Sorting and totals are part of screens already guarded by
`payments.view` (Contract Payments) and `payments.batch` (batch screen); the
actions keep their own guards.

## Translations

One new string, in `en.json` and `pt_BR.json`:
`:count payment entered|:count payments entered` →
*:count pagamento lançado|:count pagamentos lançados*. Everything else reuses
existing keys (*Total*, *:count contract|:count contracts*, *Sort by :column*).

## Tests

`tests/Feature/Contract/ContractPaymentBalanceTest.php`:

- `test_the_payment_batch_table_sorts_the_same_way_across_pages`
- `test_the_payment_method_defaults_to_check_on_both_screens`
- `test_both_tables_end_with_a_totals_row`

`ContractPaymentsRowKeysTest` now expects `wire:model` (not `.blur`) on the Pay
Today input; what it guards — each input bound to its own contract — is unchanged.

## Local development note

Tests that render a full page (for example the translation sweep in
`CollaborationDocumentTest`) need the front-end assets: either `npm run dev`
running or `npm run build` done once. Without `public/build/manifest.json` or
`public/hot` they fail with *Vite manifest not found*, which is not a code fault.
