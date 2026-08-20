# Open Items — handoff for the next session

Rewritten 2026-08-18 after the income distribution work shipped, the quotation module was
planned, and **phase 1 of that module (the purchase requisition) was built**. Read this
first; every finished piece of work has its own file (index at the bottom).

---

## 1. State of the repo

- `main` at `985089c`; **the requisition module (phase 1) and the quotation round (phase 2)
  proposal entry (phase 3), the comparison map (phase 4), negotiation rounds (phase 5) and
  the award (phase 6) and conversion (phase 7) are uncommitted in the working tree**, along
  with the contract `draft` status that touches the contract module and the money reports —
  thirteen migrations, nine
  models, a service, a mailable, two PDF controllers, four Livewire pages, shared traits and
  partials, routes, nav, module entry, pt_BR strings and docs. The shared `x-ui.modal`
  component also gained modal stacking, which touches every modal in the app.
- **2026-08-19:** the header search rewrite (projects + job sites) is **committed** in
  `fe5d7df`. The cost code add/edit dialogs on budgets and templates are **uncommitted** in
  the working tree. Both are view + component work only — **no migrations** — plus 14
  translation keys across both locales. See `docs/changelog-2026-08-19.md`.
- Nothing is half-built.
- **Deploy needs:** `php artisan migrate` (phase-4 audit enum, the incomes status/due-date
  migration, `income_distributions`, the three `purchase_requisition*` tables and the four
  `quotation*` tables, including `quotation_rfq_emails`, `quotation_vendor_items` and
  `quotation_negotiations`) and `php artisan view:clear`.
- **Process rules (user-set):** never commit, never merge, never push — the user does all
  three. Leave finished work in the working tree and report it.

## 2. Next up — phase 9, Review and Improvements

**Phases 1–8 are done** — the whole chain from the requisition to the purchase orders and
contracts that get paid, with the awarded prices taught back to the catalog
(`docs/requisition-module.md`, `docs/quotation-module.md`), both levels.

**Phase 9 is the standing final phase** from `CLAUDE.md`: work the backlog in
`docs/review-and-improvements.md` — 22 improvement rows, plus the seven defects already
found and fixed across two review passes — and whatever the owner's own end-to-end testing
turns up. The owner intends to run the chain themselves first; those findings come in here.

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
| Who approves a requisition | **Admin or manager** — built |
| Who awards a quotation | **Admin or manager**, no value thresholds |
| Vendor prices | **Procurement keys them in** — no vendor portal; BR vendors reply by e-mail |
| RFQ delivery | **Sent from the system by e-mail**; the app already sends mail |
| Material path | Quote → **Purchase Order** → Expense (never straight to an expense) |
| Service path | Quote → **Contract** (payment schedule, medições, retention already exist) |
| Split award | **Supported**, but awarding the whole quote to one vendor is the default |
| Minimum proposals | **Block below 2, warn below 3** (3 is the BR norm) |
| Requisition (solicitação de compra) | **Included** — the chain starts with a site requisition |
| BR terminology | **Cotação** — *orçamento* is already taken by Budget and Estimate |

**Build order is 8 phases** (see the plan). One page at a time, tested before the next —
CLAUDE.md rule 7.

Three smaller questions remain in §6 of the plan (equalization depth, budget enforcement,
module coverage), each with a stated assumption; none blocks phase 2.

## 2b. Also planned, not started — Meetings / Minutes / Tasks

Planned with the owner 2026-08-19: a meeting-minutes module (ata de reunião) with a real task
system behind it. Minutes are frozen records; tasks are living work; an agenda item is the join,
which is what makes "open items from the last meeting show up on the next agenda" work. Nine
decisions were taken with the owner and must not be re-litigated. **The plan is
`docs/meetings-module-plan.md` — read it before writing anything.** Build order puts the task
system first (phases 0–2), meetings after (3–6), then notifications, reports and the standing
review phase. Nothing built.

## 3. Shipped 2026-08-19

- **Contracts gained a `draft` status** (uncommitted, owner's call 2026-08-19). Contracts
  raised from an award now start as drafts and are activated deliberately.
  `Contract::scopeCommitted()` is the single definition of "counts as money" and was applied
  to the payment schedule, accounts payable, both financial reports, the job-site overview,
  the budget cost-code grid, the dashboard, the contract payments dashboard and CSV, and the
  payment batch screen. Hand-created contracts are unchanged. See `docs/contract-module.md`.
- **Catalog and budget — quotation module phase 8** (uncommitted). Awarding writes the
  agreed prices into `catalog_item_price_history` (the catalog's own `current_cost` is left
  alone so estimates are unaffected); the last real price is shown in the scope picker and
  on the proposal screen; the award screen states the budget position and warns, never
  blocks, when it goes over. See `docs/quotation-module.md`.
- **Conversion — quotation module phase 7** (uncommitted). An awarded round becomes one
  **draft** purchase order per winning vendor (material, with the awarded lines, budget
  items and the vendor's own freight/taxes/discount) or one contract per winning vendor
  (service, with the scope in the notes); vendor supplier/subcontractor flags set; links
  written both ways; `converted` terminal. See `docs/quotation-module.md`.
- **The award — quotation module phase 6** (uncommitted). Whole-round or split-by-line,
  a required justification, the 2-proposal block and the 3-proposal warning with an explicit
  acknowledgement, a separate acknowledgement for awarding an expired proposal, losing
  proposals marked not selected, prices frozen, and a revoke path. See
  `docs/quotation-module.md`.
- **Modal stacking fixed in `x-ui.modal`** — a modal opened from inside another used to
  render behind it (all modals shared `z-50`); Escape closed every open modal at once; and
  a child closing unlocked the page scroll under its still-open parent. New `layer` prop,
  DOM-derived topmost and scroll lock. Touches every modal in the app.
- **Negotiation rounds — quotation module phase 5** (uncommitted). `quotation_negotiations`
  keeps each round's before and after totals with the reason; the price screen doubles as
  the negotiation screen (note required, live movement against the standing offer); the
  detail view, the map and the PDF all show what the haggling won. See
  `docs/quotation-module.md`.
- **Comparison map — quotation module phase 4** (uncommitted). Scope lines as rows,
  proposals as columns, best price per row highlighted, cannot-supply / not-quoted /
  substitute cells marked, footers equalizing lines → freight → taxes → discount → total,
  the benchmark restricted to complete unexpired offers, saving measured inside the
  comparable set, split-award and budget comparisons, designed empty state, and a landscape
  PDF. `App\Services\QuotationComparisonService` builds the one shape the screen and the PDF
  share. See `docs/quotation-module.md`.
- **Four bugs found and fixed in a review pass** (see the changelog section of
  `docs/quotation-module.md`): a blank price stored as R$ 0.00, stale line totals when a
  scope quantity changed, a round stuck in `comparing` after its last proposal was removed,
  and vendor actions still accepted on cancelled rounds. Plus `getLocationDisplay()` reading
  a column that does not exist (`jobSite->name`).
- **Proposal entry — quotation module phase 3** (uncommitted). A full-page screen per
  vendor: price per line with server-computed line totals, cannot-supply and substitute
  handling, terms (freight CIF/FOB, taxes, discount, lead time, payment terms, validity),
  how the proposal arrived, the vendor's PDF on their own row, and the equalized total live
  on screen. The round moves to `comparing` on the first proposal. See
  `docs/quotation-module.md`.

## 4. Shipped 2026-08-18

- **Quotation rounds + the RFQ e-mail — quotation module phase 2** (uncommitted). Project
  and job-site pages, full-page form and detail, the shared scope, invited vendors with
  how-they-were-asked tracking, the 2/3-proposal rule shown from the start, **Quote it**
  from an approved requisition, and the **request e-mailed from the app** — one message and
  one priceable scope PDF per vendor, per-vendor failure logging, PDF fallback when the
  install has no mail server. See `docs/quotation-module.md`.
- **Purchase requisitions — quotation module phase 1** (uncommitted). Project and job-site
  pages, full-page form and detail modals, approve/reject by admin **or manager**, status
  history, attachments, catalog and budget-item pickers, a `quotations` module switch, and
  the pt_BR strings. See `docs/requisition-module.md`.

### Committed earlier the same day

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

## 5. Every module now ends with a Review and Improvements phase

Owner's standing rule, added to `CLAUDE.md` on 2026-08-19: a module is not finished when its
features are. The extra final phase reviews the whole module, walks every screen in both
themes and locales, closes the gap between what the screens promise and what the code
enforces, and works the backlog collected while building.

**The backlog lives in `docs/review-and-improvements.md`** — sixteen items are already on it
for the quotation chain, and it is where mid-build observations go instead of derailing the
feature in hand. For the quotation module this is **phase 9**.

## 6. Permissions — a decision the owner has to take before much more is built

`docs/permissions-notes.md` is the running list. The trigger: a requisition must be approved
before it can be quoted, but that control only holds if a lesser user cannot go around it —
and today a round can be raised standalone with no requisition at all, anyone can submit or
cancel someone else's draft, a manager can approve their own requisition, and nobody is
confined to their own projects.

The owner has already said a lesser employee should be able to **duplicate a requisition**
rather than bypass the approval step. **Nothing has been changed** — the file lists the
observations, the options and the seven decisions needed.

## 7. Engineering items still open

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

## 8. Things worth knowing when picking this up

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

## 9. Documentation index

| Topic | File |
|---|---|
| **Documentation module (the in-app library)** | `docs/documentation-module.md` |
| **Meetings — how to use it (the first shipped guide)** | `docs/meetings-module-guide.md` |
| **Meetings / minutes / tasks plan + build log** | `docs/meetings-module-plan.md` |
| **Quotation module plan (phases 2–8 next)** | `docs/quotation-module-plan.md` |
| **Requisitions — phase 1, as built** | `docs/requisition-module.md` |
| **Quotation rounds — phases 2-5, as built** | `docs/quotation-module.md` |
| **Permissions — running notations (nothing built)** | `docs/permissions-notes.md` |
| **Review and Improvements — the standing final phase + backlog** | `docs/review-and-improvements.md` |
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
| What shipped 2026-08-19 (header search, cost code dialogs) | `docs/changelog-2026-08-19.md` |
| Header search, as built | `docs/header-search.md` |
