# Open Items — handoff for the next session

Written 2026-08-18, updated the same day after the distribution work. Everything below is
either **done and awaiting commit**, **not started**, or **known but deliberately
deferred**. Finished work is documented in its own file (see the index at the bottom).

---

## 1. State of the repo

- `main` at `5be0c44`. The contract payment schedule feature is complete through **all
  seven phases** (`docs/contract-payment-schedule-plan.md`).
- **Uncommitted in the working tree:** income distribution + the job site income page
  (section 2 below).
- **Deploy needs:** `php artisan migrate` (phase-4 audit enum, the incomes
  status/due-date migration, and `income_distributions`) and `php artisan view:clear`.
- **Process rules (user-set):** never commit, never merge, never push — the user does all
  three. Leave finished work in the working tree and report it.

## 2. Income distribution across job sites — DONE (2026-08-18)

Built and verified; see `docs/income-module.md` and
`docs/changelog-2026-08-18-income-distribution.md`. Decisions taken with the user:

| Question | Decision |
|---|---|
| Amounts or percentages | **Both**, per row, like the cronograma. Only the amount is stored. |
| Partial distribution | **Allowed** — the remainder stays project-level. |
| Expected income | **Distributable** — the split describes the money either way. |
| Amount lowered below the distributed total | **Blocked**, with a form error. Nothing is rescaled silently. |
| Distributed income on the project list | **One row**, with the split shown under the location badge and in the view modal. |
| Where the split is edited | **Inside the income form** (full-page modal), not a separate step — decided together with the amount. |
| Detail views | **Full page, every field the record holds** — see the Design Standard added to `CLAUDE.md`. |

The job-site income page (income module phase 2) shipped with it, including the read-only
**Project share** row.

**Still in the working tree, uncommitted** — the user commits, merges and pushes.
Deploy needs `php artisan migrate` + `php artisan view:clear`.

## 3. Engineering items still open

- **Code review of phases 6 and 7, and of the income distribution work** — the boletim/cronograma PDFs and the translation
  sweep are the only work that never went through one. The sweep touched
  `ContractPayment::getPaymentMethodLabel()`, which the invoice and sales-tax views also
  render.
- **Stale medição baseline** — cancelling an approved medição *after* a later draft was
  created leaves that draft's `previous_percent` on the cancelled baseline. Recreating the
  draft is the workaround.
- **One batch row per contract** — `payment_batch_items` is unique per
  (batch, contract), so a batch settles at most one parcela or medição per contract.
  Lifting it means dropping that unique index.
- **`fputcsv()` PHP 8.4 deprecation** — every report CSV export omits the explicit
  `$escape` argument, including the new Company Financials one. One line per call site;
  four call sites.

## 4. Things worth knowing when picking this up

- **Local data:** MariaDB `test_despesas` via Herd; `mysql` CLI is not on PATH — use
  `php artisan tinker`. Four contracts, four medições on CTR-0003, four income records.
  The app runs in **English** locale locally, with a terminology remap (Project displays
  as "Job Site", Job Sites as "Lots"); pt_BR is the other install.
- **Verification pattern that works here:** exercise Livewire components with
  `Livewire::test(...)` inside `DB::beginTransaction()` / `DB::rollBack()` so live data is
  never touched, and render pages through the HTTP kernel to catch view errors. Flash
  messages set inside `Livewire::test` are not visible via `session()` — assert on state,
  or call the component method on a bare instance to check the message text.
- **Every translation sweep ends with a full-view compile check** — see
  `docs/translation-system.md`. A sweep once wrapped a PHP property and 500'd three pages.
- **Reports must agree.** The out side of `CompanyFinancialService` is cross-checked
  against `PaymentScheduleService`; `Contract::openPayableItems()` and
  `Contract::getUnscheduledRemaining()` are shared on purpose so the rules cannot drift.

## 5. Documentation index for this work

| Topic | File |
|---|---|
| Cronograma / medições / retenção, all seven phases | `docs/contract-payment-schedule-plan.md` |
| Payment schedule + accounts payable reports (contract dating) | `docs/payment-schedule.md` |
| Company Financials report | `docs/company-financials.md` |
| Income module, incl. received/expected | `docs/income-module.md` |
| Translation system + sweep safety rule | `docs/translation-system.md` |
| What shipped 2026-08-17/18 | `docs/changelog-2026-08-18.md` |
| Income distribution + job site income page | `docs/changelog-2026-08-18-income-distribution.md` |
