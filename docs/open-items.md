# Open Items — handoff for the next session

Rewritten 2026-08-18 after the income distribution work shipped and the quotation module was
planned. Read this first; every finished piece of work has its own file (index at the bottom).

---

## 1. State of the repo

- `main` at `b6665e1`; **working tree clean apart from this file and the quotation plan.**
- Everything below the "shipped" line is committed. Nothing is half-built.
- **Deploy needs:** `php artisan migrate` (phase-4 audit enum, the incomes status/due-date
  migration, and `income_distributions`) and `php artisan view:clear`.
- **Process rules (user-set):** never commit, never merge, never push — the user does all
  three. Leave finished work in the working tree and report it.

## 2. Next up — Quotation module (planned, not started)

**The plan is `docs/quotation-module-plan.md`. Read it before writing anything.**

The buy side: ask several vendors what they would charge, compare, negotiate, award one.
Not the client-facing quote — that is the existing Estimate module, which is unchanged.

Researched against Brazilian practice (Sienge and others). The chain is
`solicitação de compra → cotação → mapa comparativo → negociação → escolha justificada →
pedido de compra (material) | contrato + medições (serviço)`. The client's
"service becomes a contract, item becomes an expense" **is** standard BR practice, with one
correction: materials go **quote → PO → expense**, because `PurchaseOrder::createExpenseFromPO()`
already makes the expense when a PO is approved.

**Decisions already taken with the owner — do not re-litigate:**

| Question | Decision |
|---|---|
| Material path | Quote → **Purchase Order** → Expense (never straight to an expense) |
| Service path | Quote → **Contract** (payment schedule, medições, retention already exist) |
| Split award | **Supported**, but awarding the whole quote to one vendor is the default |
| Minimum proposals | **Block below 2, warn below 3** (3 is the BR norm) |
| Requisition (solicitação de compra) | **Included** — the chain starts with a site requisition |
| BR terminology | **Cotação** — *orçamento* is already taken by Budget and Estimate |

**Build order is 8 phases** (see the plan). Phase 1 is the requisition: migrations, models,
index/create/detail, approve–reject with audit trail. One page at a time, tested before the
next — CLAUDE.md rule 7.

Six smaller questions are still open in §6 of the plan, each with a stated assumption. The
two that matter: **who may approve a requisition** and **who may award a quotation** — both
assumed admin, matching the existing approve/delete pattern.

## 3. Shipped 2026-08-18 (committed)

- **Income distribution across job sites** + the **job site income page** — `a2c8639`.
  Project-level income splits across the project's lots inside the income form itself;
  full-page form and detail views. See `docs/income-module.md` and
  `docs/changelog-2026-08-18-income-distribution.md`.
- **Code review of that work** — `c9bf382`, five defects fixed. See
  `docs/code-review-2026-08-18-income-distribution.md`.
- **`CLAUDE.md` gained a Design Standard section** (a2c8639) — full-page modals for real
  work, detail views that show every field, visible totals, bulk actions, designed empty
  states, both themes and locales, project/job-site parity. **It applies to everything from
  now on**; the user asked for it explicitly after rejecting a cramped dialog.

## 4. Engineering items still open

- **Code review of contract phases 6 and 7** — the boletim/cronograma PDFs and the
  translation sweep never went through one. The sweep touched
  `ContractPayment::getPaymentMethodLabel()`, which the invoice and sales-tax views also
  render.
- **`fputcsv()` PHP 8.4 deprecation** — every report CSV export omits the explicit `$escape`
  argument. One line per call site; four call sites.
- **Stale medição baseline** — cancelling an approved medição *after* a later draft was
  created leaves that draft's `previous_percent` on the cancelled baseline. Recreating the
  draft is the workaround.
- **One batch row per contract** — `payment_batch_items` is unique per (batch, contract), so
  a batch settles at most one parcela or medição per contract. Lifting it means dropping that
  unique index.
- **`income_distributions.amount` is a signed bigint** — negatives are unreachable through
  the app but the column would accept one. Needs a second migration if it matters.

## 5. Things worth knowing when picking this up

- **Local data:** MariaDB `test_despesas` via Herd; `mysql` CLI is **not on PATH** — use a
  bootstrap script (below) or `php artisan tinker`. The app runs in **English** locally with
  a terminology remap (Project displays as "Job Site", Job Sites as "Lots"); pt_BR is the
  other install, where income is **"Entrada"**, never "Receita".
- **Verification pattern that works here.** `php artisan tinker --execute` mangles `use`
  statements; instead write a script and bootstrap Laravel by hand:
  ```php
  $base = "/Users/jr/Lerd/Despesas";
  require $base."/vendor/autoload.php";
  $app = require $base."/bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  ```
  Then exercise components with `Livewire::test(...)` inside
  `DB::beginTransaction()` / `DB::rollBack()` so live data is never touched, and render pages
  through the HTTP kernel to catch view errors. Note `->get('x')` reads a **property**;
  view data needs `->viewData('x')`. Flash messages set inside `Livewire::test` are not
  visible via `session()` — assert on state instead.
- **Livewire public arrays are client-controlled.** A row index the server never built can
  arrive, and Livewire writes it **before** the `updated` hook runs. Guard every path that
  walks such an array, including the one feeding the view. See the code review doc.
- **Every translation sweep ends with a full-view compile check** — `Blade::compileString()`
  over all of `resources/views`, then `php -l` on the output. See `docs/translation-system.md`.
  A sweep once wrapped a PHP property and 500'd three pages.
- **Reports must agree.** The out side of `CompanyFinancialService` is cross-checked against
  `PaymentScheduleService`; `Contract::openPayableItems()` and
  `Contract::getUnscheduledRemaining()` are shared on purpose so the rules cannot drift.

## 6. Documentation index

| Topic | File |
|---|---|
| **Quotation module plan (next feature)** | `docs/quotation-module-plan.md` |
| Income module, incl. received/expected and distribution | `docs/income-module.md` |
| Income distribution + job site income page changelog | `docs/changelog-2026-08-18-income-distribution.md` |
| Code review of that work | `docs/code-review-2026-08-18-income-distribution.md` |
| Cronograma / medições / retenção, all seven phases | `docs/contract-payment-schedule-plan.md` |
| Payment schedule + accounts payable reports | `docs/payment-schedule.md` |
| Company Financials report | `docs/company-financials.md` |
| Purchase Order module (quote → PO → expense target) | `docs/purchase-order-module.md` |
| Contract module (quote → contract target) | `docs/contract-module.md` |
| Translation system + sweep safety rule | `docs/translation-system.md` |
| Project / job site parity rule | `docs/project-jobsite-parity-rule.md` |
| What shipped 2026-08-17/18 | `docs/changelog-2026-08-18.md` |
