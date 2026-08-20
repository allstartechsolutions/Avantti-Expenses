# Open Items — handoff for the next session

Rewritten **2026-08-20**, after the meetings / minutes / tasks module was built through phase 7
and the in-app documentation library shipped. Read this first; every finished piece of work has
its own file (index at the bottom).

---

## 1. State of the repo

- **`main` is at `5d05f97` and the working tree is clean.** Everything described in this file is
  committed. The quotation chain, the document repository, the meetings module, the documentation
  library and the cost code / change order work are all in.
- **Nothing is half-built.** The two modules with work outstanding (meetings, quotations) are
  outstanding at the *phase* level — every screen that exists, works.
- **Deploy needs:** `php artisan migrate` (47 additive migrations since `985089c`, listed by
  `git diff --name-only --diff-filter=A 985089c..HEAD -- database/migrations`) then
  `php artisan view:clear`. No migration drops or rewrites a column; the two that touch existing
  tables (`allow_file_uploads_without_an_owner`, `make_meeting_attendance_unmarked_by_default`)
  only relax a NOT NULL.
- **The scheduler must be running in production** for the task e-mails, and it is worth checking:
  `routes/console.php` now schedules `tasks:notify-overdue` daily at 07:00 and
  `tasks:send-weekly-digest` hourly (the command itself decides whether this is the configured
  day and hour, so moving the digest in System Settings needs no deploy). Both are idempotent —
  the notification log stops anyone being mailed twice.
- **Process rules (user-set):** never commit, never merge, never push — the user does all three.
  Leave finished work in the working tree and report it.

---

## 2. Next up

Three modules are each sitting on their standing final phase, plus one feature phase.

### 2a. Cost codes on expenses and change orders — phase 7 (the review)

**Phases 1–6 are done and committed** (2026-08-19/20). A project or job-site change order used
to move only the money billed to the client; it now carries a cost side per cost code with an
approval that gates it, `CostCodeLedger` answers Original → Changes → Revised → Committed →
Actual → Remaining for every budget screen, a drill-down opens the records behind any figure,
**expenses can finally be edited** (so a wrong cost code can be corrected) with the change
written to history, and the financial reports and their PDFs report against the revised budget.

- **Deploy:** `php artisan migrate` (3 additive migrations, `2026_08_19_180000/1/2_*` — the change
  order ones, not the meeting ones with the same timestamps) then `php artisan view:clear`.
  **No live figure moves on deploy** — existing change orders default to `approved` with no cost
  lines, and the client contract value still counts every change order as it always did.
- **Read:** `docs/changelog-2026-08-20-costcodes-changeorders.md` for the deploy-facing summary,
  `docs/expense-changeorder-costcode-plan.md` for the design (§8–§13 are the per-phase build logs).
- **Phase 7 is written out as a checklist in §14 of the plan**: what exists, the twelve screens to
  walk, the backlog grouped by cost, the wording claims to verify. Nothing in it has been done —
  in particular **the screen walk has never happened** for any of these screens.
- **Highest-value items in that backlog:** `change_orders.co_number` is not unique, approval has no
  permission guard (`permissions-notes.md` §4b), and the legacy expense modal in `JobSiteShow` /
  `ProjectShow` is now dead for create and edit but still serves view mode.

### 2b. Meetings — phase 8, then phase 9

**Phases 0–7 are done** (`docs/meetings-module-plan.md` §12 is the build log). Tasks can be
raised anywhere, meetings can be created, agendas built with carry-forward, minutes run,
published, corrected, filed as a PDF into the project repository, e-mailed to attendees, and the
four notification triggers all work.

**Phase 8 — what is left to build:** the dashboard widget, the All Tasks page (filters + CSV),
and the two reports (open items by owner, aging). §5.6 and §5.8 of the plan describe them.
Note **M6** below: `MyTasks::stats()` runs five counting queries, and the All Tasks page is where
that shape must become one grouped query rather than being copied.

**Phase 9 — the standing review**, per `CLAUDE.md`. Four backlog rows are still open (M4, M6, M7,
M10, in `docs/review-and-improvements.md`), and **the screen walk has never been done** — both
themes, both locales, a phone, with empty / partial / error states and long names. Seven other
rows (M1, M2, M3, M5, M8, M9, M11) were worked on 2026-08-20; M12 is the owner's *won't fix*.

### 2c. Quotations — phase 9

**Phases 1–8 are done** — the whole chain from the requisition to the purchase orders and
contracts that get paid, with awarded prices taught back to the catalog
(`docs/requisition-module.md`, `docs/quotation-module.md`).

Phase 9 works the 22 improvement rows in `docs/review-and-improvements.md` plus whatever the
owner's own end-to-end run turns up. The owner intends to run the chain themselves first; those
findings come in here.

### 2d. The decision that is blocking neither but shadows both

**Permissions.** `docs/permissions-notes.md` is the running list and **nothing has been built**.
The trigger: a requisition must be approved before it can be quoted, but that control only holds
if a lesser user cannot go around it — today a round can be raised standalone with no requisition,
anyone can submit or cancel someone else's draft, a manager can approve their own requisition, and
nobody is confined to their own projects. Seven decisions are waiting on the owner. The meetings
module added nothing to this file: its guards are on the models and were built as it went.

---

## 3. Shipped 2026-08-20

- **Cost codes on expenses and change orders — phases 1 to 6.** Change orders gained a cost side
  (`change_order_items`, signed per code) and an approval that gates it, while the revenue side
  they always had is untouched, so no live total moves on deploy. `CostCodeLedger` became the
  single source for budget-versus-actual and `Budget::costCodeGrid()` was deleted. Three copies
  of the change order form and three of the expense form collapsed into one each
  (`ManagesChangeOrders`, `ManagesExpenseForm`). New: the cost code drill-down, `ExpenseEdit`
  (the app had no real expense editor), a landscape cost-grid PDF, and a Budget by Cost Code
  section in both financial reports and their PDFs. 3 additive migrations. Phase 7, the review,
  has not been started — see §2a. Full write-up:
  `docs/changelog-2026-08-20-costcodes-changeorders.md`.

- **Meetings, minutes and tasks — phases 0 to 7.** A meeting-minutes module (*ata de reunião*)
  with a real task system behind it. Minutes are frozen records, tasks are living work, and a
  `meeting_items` row is the join between them — which is what makes "open items from the last
  meeting show up on the next agenda" work without copying anything. Tasks raised outside a
  meeting (project, job site, standalone) never appear on an agenda on their own; somebody has to
  put them there. 20 migrations, 12 models, 8 services, the R2 uploader shared with the document
  repository, a dompdf minute, four mailables and two scheduled commands.
  **`docs/meetings-module-plan.md`** is the plan and the build log; **`docs/meetings-module-guide.md`**
  is the user guide, with nine screenshots served from R2.
- **The in-app documentation library.** Shipped markdown guides *and* database articles
  (`doc_articles`), readable by everyone signed in, images on R2, a `SyncDocumentationImages`
  command that migrated the existing screenshots. Last item in the menu, by the owner's request.
  See `docs/documentation-module.md`.
- **The M sweep** — seven backlog rows worked in one pass on 2026-08-20: task-specific status
  words (M1), the N+1s on task lists (M2), the progress roll-up as a model event (M3), attendance
  starting blank (M5), task deletion for admins and never for anything in a published minute (M8),
  drag-to-reorder on the agenda (M9), and the `strip_tags` / stored-XSS sweep across ten echo
  sites (M11). Each is written up at the bottom of `docs/review-and-improvements.md`.
- **Two defects in editing an agenda line** (phase 5e in the build log). A task raised outside a
  meeting has an optional due date; adding it to an agenda turned it into an action item with no
  date, which then failed validation on *every* subsequent save — so renaming it silently did
  nothing. The agenda now flags such a line, the form says why it refused and keeps what was
  typed, and the rule stands. Found alongside it: editing a line whose task is **closed** renamed
  the agenda item and silently dropped the task changes; that now refuses by name.

## 4. Shipped 2026-08-19

- **Quotation module phases 2–8** — rounds and the RFQ e-mail, proposal entry, the comparison map,
  negotiation rounds, the award, conversion to draft POs and contracts, and the catalog/budget
  feedback. See `docs/quotation-module.md`.
- **Contracts gained a `draft` status.** Contracts raised from an award start as drafts and are
  activated deliberately. `Contract::scopeCommitted()` is now the single definition of "counts as
  money" and was applied to the payment schedule, accounts payable, both financial reports, the
  job-site overview, the budget cost-code grid, the dashboard, the contract payments dashboard and
  CSV, and the payment batch screen. Hand-created contracts are unchanged.
- **The document repository (file module)** and **modal stacking in `x-ui.modal`** — a modal opened
  from inside another used to render behind it, Escape closed every open modal at once, and a child
  closing unlocked the page scroll under its parent. Touches every modal in the app.
- **Header search** (projects + job sites) and the **cost code add/edit dialogs** on budgets and
  templates. See `docs/changelog-2026-08-19.md`.
- **Four bugs found in a quotation review pass** — a blank price stored as R$ 0.00, stale line
  totals when a scope quantity changed, a round stuck in `comparing` after its last proposal was
  removed, and vendor actions still accepted on cancelled rounds.

## 5. Shipped 2026-08-18

- **Income distribution across job sites** + the **job site income page** — `a2c8639`, reviewed in
  `c9bf382` with five defects fixed. See `docs/income-module.md`.
- **Purchase requisitions — quotation phase 1.** See `docs/requisition-module.md`.
- **`CLAUDE.md` gained the Design Standard section** — full-page modals for real work, detail views
  that show every field, visible totals, bulk actions, designed empty states, both themes and
  locales, project/job-site parity. **It applies to everything from now on.**

---

## 6. Every module ends with a Review and Improvements phase

Owner's standing rule, in `CLAUDE.md` since 2026-08-19: a module is not finished when its features
are. The extra final phase reviews the whole module, walks every screen in both themes and locales
and on a phone, closes the gap between what the screens promise and what the code enforces, and
works the backlog collected while building.

**The backlog is `docs/review-and-improvements.md`** — it is where mid-build observations go
instead of derailing the feature in hand, and it is worked, not archived. It currently holds the
22 quotation rows, the 12 meetings rows (M1–M12, seven of them now done), and **five sections
from the cost code / change order work** (one per phase, plus two corrections to earlier
findings) — those are the ones phase 7 of that module has to work, grouped by cost in §14.3 of
`docs/expense-changeorder-costcode-plan.md`.

---

## 7. Engineering items still open

- **Meetings M4, M6, M7, M10** — query shapes on `Meeting::openActionCount()`,
  `MyTasks::stats()` and `MeetingAgenda::scopeCandidates()`, all acceptable today and all wrong at
  scale; plus `TaskNote::canEdit()`'s 30-minute edit window, which no screen offers even though the
  timeline renders an "edited" marker. Either wire the control or drop the marker.
- **Code review of contract phases 6 and 7** — the boletim/cronograma PDFs and the translation
  sweep never went through one. That sweep touched `ContractPayment::getPaymentMethodLabel()`,
  which the invoice and sales-tax views also render.
- **`fputcsv()` PHP 8.4 deprecation** — every report CSV export omits the explicit `$escape`
  argument. One line per call site; four call sites.
- **Stale medição baseline** — cancelling an approved medição *after* a later draft was created
  leaves that draft's `previous_percent` on the cancelled baseline. Recreating the draft is the
  workaround.
- **One batch row per contract** — `payment_batch_items` is unique per (batch, contract), so a
  batch settles at most one parcela or medição per contract. Lifting it means dropping that index.
- **`income_distributions.amount` is a signed bigint** — negatives are unreachable through the app
  but the column would accept one. Needs a second migration if it matters.
- **The legacy tabbed `ProjectShow` / `JobSiteShow` modals** are largely dead code but still serve
  *view* mode, so they cannot simply be deleted. Worth a dedicated cleanup pass. As of
  2026-08-20 this is precise for expenses: both entry points now go to `expenses.edit`, so
  `saveExpense()`, `openExpenseCreateModal()` and `openExpenseEditModal()` are dead in both
  components, and `Expense::isEditableBy()` contradicts `ExpenseEdit`'s guards.
- **`change_orders.co_number` is not unique** — the form pre-fills the next number in the
  project's series, so two people creating one at the same moment collide. The quotation chain's
  unique-index-plus-retry is the fix.
- **Every financial report is built twice**, once in its Livewire component and once in its PDF
  controller, with the arithmetic copy-pasted. Adding one figure means four edits.

---

## 8. Things worth knowing when picking this up

- **Local data:** MariaDB `test_despesas` via Herd; `mysql` CLI is **not on PATH** — use a
  bootstrap script (below) or `php artisan tinker`. The app runs in **English** locally with a
  terminology remap (Project displays as "Job Site", Job Sites as "Lots"); pt_BR is the other
  install, where income is **"Entrada"**, never "Receita". **The owner has ruled the EN translation
  file off limits** (2026-08-20) — see M12; write around it, do not "fix" it.
- **Verification pattern that works here.** `php artisan tinker --execute` mangles `use`
  statements; instead write a script in the scratchpad and bootstrap Laravel by hand:
  ```php
  $base = "/Users/jr/Lerd/Despesas";
  require $base."/vendor/autoload.php";
  $app = require $base."/bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  ```
  Then exercise components with `Livewire::test(...)` inside `DB::beginTransaction()` /
  `DB::rollBack()` so live data is never touched, and render pages through the HTTP kernel to catch
  view errors. `->get('x')` reads a **property**; view data needs `->viewData('x')`. Flash messages
  set inside `Livewire::test` are not visible via `session()` — assert on state instead.
- **The Livewire test harness swallows `abort()`.** A guard that looks like it did not fire under
  `Livewire::test` may be firing perfectly well; verify those over real HTTP, or by reading the
  database state afterwards. Several "NOT REFUSED" readings during the meetings build were
  artifacts of this.
- **Livewire public arrays are client-controlled.** A row index the server never built can arrive,
  and Livewire writes it **before** the `updated` hook runs. Guard every path that walks such an
  array, including the one feeding the view.
- **Every translation sweep ends with a full-view compile check** — `Blade::compileString()` over
  all of `resources/views`, then `php -l` on the output. A sweep once wrapped a PHP property and
  500'd three pages. See `docs/translation-system.md`.
- **R2 is billed for what is on it.** Incomplete multipart uploads and trashed documents are swept
  by scheduled commands; anything new that writes to R2 needs to be known to
  `FileUploadService::pruneStaleUploads()` and the orphan sweep, or it will either leak storage or
  — worse — get swept while still in use.
- **Never list-and-delete R2 objects in one command.** List first, read the list, then delete only
  what you have identified. Six orphans were deleted this way once when only one was actually an
  orphan.
- **dompdf stamps a creation date**, so re-rendering the same document produces different bytes.
  Checksums cannot be used to answer "did this change?" for a PDF — `MeetingMinuteDistributor`
  keys off whether a correction was recorded since the filed version instead.
- **Reports must agree.** The out side of `CompanyFinancialService` is cross-checked against
  `PaymentScheduleService`; `Contract::openPayableItems()` and `Contract::getUnscheduledRemaining()`
  are shared on purpose so the rules cannot drift.

---

## 9. Documentation index

| Topic | File |
|---|---|
| **Cost codes on expenses + change orders — design, build log (phases 1–6), phase 7 checklist** | `docs/expense-changeorder-costcode-plan.md` |
| **That work's deploy-facing changelog** | `docs/changelog-2026-08-20-costcodes-changeorders.md` |
| **Meetings / minutes / tasks — plan + build log (phases 0–7 done)** | `docs/meetings-module-plan.md` |
| **Meetings — the user guide, also published in-app** | `docs/meetings-module-guide.md` |
| **Documentation module (the in-app library)** | `docs/documentation-module.md` |
| **Review and Improvements — the standing final phase + the whole backlog** | `docs/review-and-improvements.md` |
| **Permissions — running notations (nothing built)** | `docs/permissions-notes.md` |
| Quotation module plan (phase 9 next) | `docs/quotation-module-plan.md` |
| Requisitions — phase 1, as built | `docs/requisition-module.md` |
| Quotation rounds — phases 2–8, as built | `docs/quotation-module.md` |
| File repository (documents module) — plan + build log | `docs/file-repository-plan.md` |
| Cloudflare R2 setup (bucket, token, CORS) | `docs/deployment-cloudflare-r2.md` |
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
| Header search, as built | `docs/header-search.md` |
| What shipped 2026-08-17/18 | `docs/changelog-2026-08-18.md` |
| What shipped 2026-08-19 | `docs/changelog-2026-08-19.md` |
