# Changelog — amounts crossing rows on the payments screen (2026-09-16)

A user typing a *Pay today* amount on `/contract-payments` sometimes saw the same amount
appear in other rows — not every row, and not every time. The cause was not in the payment
code at all: the table's rows had no `wire:key`, and Livewire was reusing one row's input for
a different contract without letting go of the first. The same shape was found on two other
screens and fixed there too. Reference: the new rule in `CLAUDE.md` under *UI Components →
Rows with inputs carry a `wire:key`*.

## What was happening

1. The contracts table is a `@forelse` with one `<tr>` per contract, and each row carries
   three `wire:model` inputs (`payAmounts.{id}`, `payMethods.{id}`, `payNotes.{id}`). The
   rows had no `wire:key`, so Livewire's morph matched them **by position** on every
   re-render.
2. Whenever the list shifted — a change-orders sub-row opening or closing, a filter changing,
   the *show zero balance* toggle flipping, a contract dropping out after processing — the
   `<tr>` that had been contract A was patched in place to become contract B. Its input kept
   its DOM node and had its `wire:model` attribute rewritten from `payAmounts.A` to
   `payAmounts.B`.
3. Livewire 4.4.3 re-initialises `wire:model` when the attribute changes, but the directive
   registers its Alpine bindings with no cleanup (`Alpine.bind(el, bindings)` in
   `wire:model`'s `directive("model", …)`), so the **old binding survived** next to the new
   one. From then on, every keystroke in that field wrote to both `payAmounts.A` and
   `payAmounts.B`, and the value showed up on two contracts.
4. Only the rows *below* the point where the list shifted were affected, which is why it
   looked random and hit some fields but not all.

Keyed rows are matched by key, not position: the `<tr>` moves with its contract, the input
never changes owner, and there is nothing to duplicate.

## What changed

| File | What |
|---|---|
| `resources/views/livewire/contract/contract-payments.blade.php` | `wire:key="contract-{id}"` on every contract row, `contract-{id}-change-orders` on the expandable sub-row, `change-order-{id}` on each line inside it |
| `resources/views/livewire/access/partials/ability-matrix.blade.php` | `matrix-section-{key}`, `matrix-area-{key}` and `matrix-action-{area}-{action}` around every section, area and checkbox |
| `resources/views/livewire/purchase-order/purchase-order-show.blade.php` | `receipt-item-{id}` on each row of the receipt modal |
| `tests/Feature/Contract/ContractPaymentsRowKeysTest.php` | **New.** Renders the screen with two contracts and an expanded change order and asserts every row and sub-row carries its key; sets an amount on one contract, shifts the list, and asserts the other contract stays empty |

## The other two screens

Every screen with a per-row `wire:model` inside a loop was scanned. Two had no keys at all:

- **The access ability matrix** was the more serious one. Its search box filters the areas,
  so the checkboxes shift as the user types, and a reused checkbox would keep its old
  binding: ticking one ability could have granted or revoked another. The role modal now
  renders 174 keyed actions for 174 checkboxes, 34 areas, 2 sections.
- **The purchase-order receipt modal** does not reorder its items, so it was low risk. It
  is keyed anyway: the fix is one attribute, and the rule is easier to hold when it has no
  exceptions.

**Payment batches** was checked and needed nothing: the edit screen, where the per-row
inputs live, already keys every row (`contract-row-{id}`), and the index and show screens
loop without keys but carry no inputs.

## Verification

- The new test passes, with the contract suites, the contract-payment permission tests, the
  access / role / template / purchase-order tests and the date-format sweep.
- The four failures in `tests/Feature/Permissions` (`ConfinementTest`, `LegacyBehaviourTest`,
  `TaskMeetingTest`, `VendorDocumentRemindersTest`) fail on the committed tree without these
  changes and are unrelated.
