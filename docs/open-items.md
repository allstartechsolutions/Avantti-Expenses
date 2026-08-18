# Open Items — handoff for the next session

Written 2026-08-18. Everything below is either **not started** or **known but deliberately
deferred**. Finished work is documented in its own file (see the index at the bottom).

---

## 1. State of the repo

- `main` at `f726ea8`; working tree clean. The contract payment schedule feature is
  complete through **all seven phases** (`docs/contract-payment-schedule-plan.md`).
- **Deploy needs:** `php artisan migrate` (phase-4 audit enum + the incomes
  status/due-date migration) and `php artisan view:clear`.
- **Process rules (user-set):** never commit, never merge, never push — the user does all
  three. Leave finished work in the working tree and report it.

## 2. Next feature — income distribution across job sites

**The requirement (user, 2026-08-18):** income received at **project level** must be
distributable to that project's **job sites**. Income received **directly on a job site**
stays as it is — no distribution involved.

Today `incomes` has a single nullable `job_site_id`: null = project-level, set = that job
site. There is no way to say "this 50.000 deposit covers 30.000 of Lot A and 20.000 of
Lot B".

### Proposed shape (to confirm before building)

- New table `income_distributions`: `income_id`, `job_site_id`, `amount` (cents),
  timestamps. One row per job site receiving a share.
- The income keeps its own amount; the distribution rows explain how it is split. The
  undistributed remainder stays project-level, which makes partial distribution natural
  (and lets a deposit be allocated as the work is assigned).
- Guard: `Σ distributions ≤ income.amount`, enforced in the model, not just the form.
- UI on the project income page: a "Distribuir" action on project-level rows opening a
  grid of the project's job sites with amount (and maybe %) per row, live remainder,
  one-transaction save — the same shape as the cronograma grid editor.
- Reporting: a job-site-scoped query must count each job site's **share**, while the
  project-scoped query counts the income **once**. `CompanyFinancialService::applyScope()`
  and the project/job-site financial reports are the call sites to change.

### Questions to settle first

1. **Amounts or percentages** in the distribution grid (or both, like the cronograma)?
2. Must a distribution be **complete** before it counts, or is a partial split fine with
   the remainder staying project-level? (Proposal: partial is fine.)
3. Can **expected** income (status `expected`) be distributed, or only received money?
   (Proposal: yes — the distribution describes the money regardless of arrival.)
4. What happens to distributions when the income **amount is reduced** below the
   distributed total — block the edit, or scale the rows?
5. Should a distributed income still appear on the **project** income list as one row
   (proposal: yes, with a "distributed" badge and the split visible in the view modal)?

## 3. Engineering items still open

- **Code review of phases 6 and 7** — the boletim/cronograma PDFs and the translation
  sweep are the only work that never went through one. The sweep touched
  `ContractPayment::getPaymentMethodLabel()`, which the invoice and sales-tax views also
  render.
- **Stale medição baseline** — cancelling an approved medição *after* a later draft was
  created leaves that draft's `previous_percent` on the cancelled baseline. Recreating the
  draft is the workaround.
- **One batch row per contract** — `payment_batch_items` is unique per
  (batch, contract), so a batch settles at most one parcela or medição per contract.
  Lifting it means dropping that unique index.
- **Income module phase 2** — the job-site income page was never built
  (`JobSite::income()` already exists). Worth doing **with** the distribution work above,
  since both touch the same page.
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
