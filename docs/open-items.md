# Open Items — handoff for the next session

Rewritten **2026-08-21**, after the permissions module was completed and deployed — engine,
eighteen module passes and the three closing phases. Read this first; every finished piece of
work has its own file (index at the bottom).

---

## 1. State of the repo

- **The permissions module is COMPLETE and DEPLOYED (2026-08-21).** Engine E1–E4, every
  module pass M1–M18, and the closing phases F0 (per-person access), F1 (confinement live)
  and F2 (the bridge removed) are all in production. **Only F3 — review and improvements —
  remains**, and its backlog is in `docs/review-and-improvements.md`. See §1a.
  **The owner is testing it over the following days**; expect findings to come back.
- Everything else described in this file is in. The quotation chain, the document repository,
  the meetings module, the documentation library and the cost code / change order work are all
  deployed.
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

### 1a. Permissions module — COMPLETE and deployed (2026-08-21)

**All 30 areas and 147 abilities are enforced. The legacy bridge is deleted. Only F3
(review and improvements) is left.**

Deployed with `php artisan migrate --force && php artisan permissions:sync`. **The deploy
changes nothing on the day**: every pass reproduced the behaviour it replaced, and the new
tables start empty — no `user_abilities` rows, no `approval_limit` anywhere, `access_scope`
null on every user and role. Confinement, per-person exceptions and ceilings are things
somebody switches on, one person at a time.

**If you are adding a NEW module, read `docs/permissions-for-new-modules.md` before writing
its first screen.** The rule is also in `CLAUDE.md` under *Every Module Ships With Its
Permissions*.

- **Where it is:** engine complete (E1–E4); passes **M1** Access & Users, **M2** Project &
  Job Site shell, **M3** Company & Settings, **M4** Expenses, **M5** Income,
  **M6** Budget & Cost Codes, **M7** Requisitions, **M8** Quotations, **M9** Purchase Orders,
  **M10** Change Orders, **M11** Contracts & Payments, **M12** Documents, **M13** Tasks &
  Meetings, **M14** Daily Reports, **M15** Estimates & Invoices, **M16** Reference data,
  **M17** Reports and **M18** Dashboard & search done. **EVERY MODULE PASS IS COMPLETE —
  29 of 30 areas enforced, and nothing inside a project is open.** The one area left on the
  bridge is the documentation library, read-only to everybody signed in by design; F3 decides
  whether it moves at all. What remains is **F1** (confinement live), **F2** (delete the
  bridge) and **F3** (review).**
- **What to read:** `docs/permissions-module-plan.md` for the design and the remaining pass
  order; `docs/permissions-module.md` for what is actually built, step by step.
- **Deploy:** `php artisan migrate --force` then **`php artisan permissions:sync`** — the
  second one matters, it seeds templates and hands new areas to existing roles.
- **New migrations:** 12 — 10 from the engine (`2026_08_20_140000` … `2026_08_20_150001`) plus
  two from M6 (`2026_08_21_120000` adds `budgets.locked_at` / `locked_by`, `2026_08_21_120001`
  creates `budget_lock_histories`) and one from M8 (`2026_08_21_130000` adds
  `quotation_vendors.priced_by`) and three from M9 (`2026_08_21_140000` drops stale legacy
  foreign keys on **non-MySQL drivers only** — a no-op on production; `2026_08_21_150000` adds
  `purchase_order_items.received_quantity`; `2026_08_21_150001` creates
  `purchase_order_receipts` and `purchase_order_receipt_lines`). All additive; every existing
  budget is unlocked, every existing proposal carries a null `priced_by`, and every existing
  order line starts at zero received, so none of them changes anything anyone can see until
  somebody uses the feature.
- **Tests:** 463 in `tests/Feature/Permissions/`, 496 in the suite. Three failures elsewhere
  are stale Laravel scaffold tests that predate this work (`RegistrationTest` ×2 — the public
  `register` route was removed from this app — and `ExampleTest`, which expects `/` to
  return 200 where it redirects to login).
- **M4 also closed four holes that were not permission gaps in the plan** — any expense
  reachable by id from any project, the Location picker accepting another project's job site,
  expense receipts readable by any signed-in user via `files.show?path=`, and `deleteJobSite`
  on `JobSiteShow` having no guard at all (M2 fixed the identical pair on `JobSiteOverview`
  and missed this one, live on six routes). See `docs/permissions-module.md`, M4.
- **Money rule set in M4, and inherited by every later pass:** `can_see_money` hides
  **roll-ups**, not records — the summary cards go, each expense's own amount stays. Mark a
  figure with `<x-ui.money … rollup />`.
- **M5 added `income.distribute`** — splitting one payment across job sites is held apart from
  recording it, because a split decides which site's report the money lands on. Guarded on all
  six methods that touch the grid, not only the save.
- **M6 BUILT budget locking** — it existed as a matrix toggle and as nothing in the code. A
  locked budget's *plan* is frozen (cost codes, planned amounts, the budget record, deletion);
  everything that reports against it carries on (expenses, POs, change orders, all the figures).
  Two additive migrations; every lock and unlock kept with who, when and why.
- **Owner's rule, set in M6 and applying to every later pass:** no ability may be reachable only
  through a role. Anything scoped to a project must be grantable on a role, on a template, on one
  project or job site, and to one person — `BudgetTest` proves all four for `budget.lock`.
  The one place this is not yet true is company-wide areas; see P6 in
  `docs/review-and-improvements.md`, worth deciding before F1.
- **Cost code templates are the GLOBAL library** — one chart of accounts, held by role, in
  neither project editor. Do not scope them to a project.
- **M7 settled notations N1 and N2** (see `docs/permissions-notes.md`, both now marked
  settled): a submitted requisition is locked and comes back via **Return to Draft**;
  **Duplicate** copies any requisition into a fresh draft; **self-approval is blocked** and
  lifted only by the new `requisitions.approve_own` grant, which no seeded role or template
  holds. Two catalogue claims were corrected — a requisition carries **no money**, so
  `approve` is not value-limited. Limits start at M8.
- **M8 settled N3 and the rest of N1.** A round raised with no requisition now needs
  `quotations.create_standalone` (a **tightening** — employees lose it, managers keep it);
  awarding and converting obey `approval_limit` for the first time; converting to a **contract**
  is a separate grant from converting to a **purchase order**; and whoever keyed a winning
  vendor's prices in cannot pick that vendor unless granted `quotations.award_own`. New column
  `quotation_vendors.priced_by` records who typed the prices, which `created_by` never did.
- **M9 BUILT purchase-order receiving** — the ability existed and nothing recorded that goods
  had arrived. Per-line quantities, so a part-delivery is honest: Ordered / Received /
  Outstanding on the order, a status (awaiting → partially received → received), and a delivery
  history with who signed and when. `receive` is held apart from `approve` — on a real site the
  office approves the spend and the storeman signs for the lorry.
- **M9 also closed two guard gaps:** all four purchase-order components had no guard of any kind
  (`approve()` creates an expense), and the job-site **Budget** tab was never guarded — M6 swept
  `budget` but `JobSiteShow`'s tab map only listed expenses.
- **P12 is fixed** (`2026_08_21_140000`), so the sqlite test database now matches production.
- **M10 settled §4b** (all four questions, now marked settled in the notations): approving is
  manager-and-above and obeys the ceiling (**a tightening** — employees could approve until
  now); self-approval blocked and lifted by `change-orders.approve_own`; undoing an approval is
  its own narrower grant `change-orders.unapprove`; and **an approved change order cannot be
  deleted by anybody, administrators included** — un-approve it first.
- **M11 closed the four unguarded money screens** that E1 flagged and the owner deliberately
  left for this pass. Fifteen components, 4,265 lines, **not one guard between them**. The
  three company-wide screens are **reproduced, not tightened** — every seeded role still
  reaches them; the difference is that it can now be taken away, and view / pay / batch are
  separable. New grant `contracts.unpay`: somebody who may pay any amount still cannot take a
  payment back out.
- **P19 is the one to read before F1.** `contracts.pay` obeys the approval ceiling;
  `payments.pay` cannot, because the ceiling lives on a membership and the payments dashboard
  belongs to no project. So the same person can be stopped inside a project and then pay the
  same money from the dashboard. Same root cause as P6 and P13 — **all three want one answer,
  decided together.**
- **M12 closed the repository half of N5 and settled N7.** `Document::isVisibleTo()` returned
  **true for every non-internal document to anybody** — including a signed-out visitor — so the
  download route handed any project's files to anyone who guessed an id. Reading is a grant now,
  and `see_internal` is answered per project. **N8 needed nothing:** the permission check was
  already before the presigned URL was minted, which is where it belongs.
- **N7's answer (owner):** `documents.share` stays with admin and manager exactly as today, but
  as a revocable toggle rather than a role check.
- **P22 is the leftover to watch:** every PDF controller in the app is still `auth` only —
  quotation RFQ/map, the six reports, contract schedule and measurement, daily reports,
  estimates and invoices. **M8 should have guarded the quotation PDFs and did not.** Either the
  remaining passes pick up their own or F3 sweeps the lot.
- **M13 was the first pass where the scope is not the screen.** My Tasks is cross-project, so
  every task grant is asked about the **task** and the list is **filtered** (`Task::visibleTo`)
  rather than guarded. A meeting has no project at all — it spans several through its items — so
  its grants are asked unscoped. **Three more unguarded actions closed:** `publish()` (freezes
  the minute and mails it to every attendee — no guard of any kind), `downloadTaskFile()` (no
  check at all, any id), and `deleteTaskFile()` (asked about the person, never the task).
- **P22 progress:** the meeting minute PDFs are guarded. Still `auth` only: quotation RFQ and
  map, contract schedule and measurement, the project and job-site financial reports, the
  expense report, the six company reports, daily reports, estimates and invoices.
- **M14 was the first pass to exercise the Site Supervisor and Client-guest templates** —
  the diary is their main screen, and both are now proven end to end. A daily report closes
  seven days after its date or when locked; the `is_admin` override became
  `daily-reports.edit_locked`, held back from every role and template.
- **P22 progress:** the daily report PDFs are guarded (they were `auth` only, so anybody could
  fetch any project's diary by changing the id). Still open: quotation RFQ and map, contract
  schedule and measurement, the financial reports, the expense report, the six company reports,
  estimates and invoices.
- **P28 found in M14:** the project financial report runs a MySQL-only `DATE_FORMAT`, so it
  500s on sqlite and **has never been covered by a test** — same class as the `FIELD()` found
  in M7. M17's to fix; worth grepping for the rest of the MySQL-only SQL at the same time.
- **M15 closed the last two unguarded money screens.** Sending, recording a payment and
  refunding are each held apart from editing; `payments.refund` — reserved since E1 and unused
  until now — belongs here, because refunds exist only on invoice payments. Converting an
  accepted estimate needs `invoices.create`, not an estimate grant.
- **The public pay link is deliberately NOT guarded** — the client has no account. It is a token
  boundary like a document share link, and that boundary is now tested: wrong token 404s, a
  draft's token 404s, one token names one invoice, a visitor with a valid token is still not
  signed in, and the amount is capped at the balance.
- **P30 — a rule worth carrying into every later pass:** a nested component's `mount()` must not
  require more than its parent's. M15 guarded the embedded send-email panel's mount and took the
  whole detail page away from readers. Every other nested component was checked and is clean.
- **M16 swept clients, vendors and the catalog.** One area covers three screens: the vendor
  unification means suppliers, subcontractors and the merge tool all read the same `vendors`
  table, so all three answer to `vendors.*`. `vendors.merge` stays apart from everything else —
  somebody who may create, edit *and delete* a vendor still cannot merge two, because a merge
  rewrites every expense, contract and PO that pointed at the loser.
- **P34 joins P6, P13 and P19 — four notations now point at the same missing piece:** there is
  no company-wide way to hold or withhold a grant per person. The newest symptom is that the
  catalog is declared a money area and nothing masks its costs, because `can_see_money` only
  reaches project scopes. **Decide this before F1.**
- **M17 split the six company reports** off the `admin` middleware onto one grant each, so an
  accountant can have Sales Tax and Accounts Payable without **Company Financials**. Every
  report's PDF answers to the same grant as its screen. The project's own financial report is
  scoped like any tab, and **printing it needs `export`, not `view`**.
- **P28 is fixed.** `PaymentScheduleService` bucketed by month with MySQL-only `DATE_FORMAT`, so
  the project financial report and the payment schedule report both 500'd on sqlite and **neither
  had ever been rendered by a test** — which is how the fault survived. Now driver-aware, MySQL
  output unchanged, both screens covered.
- **P22 is nearly closed.** Two sets of PDFs are left and **no pass is coming for them**: the
  quotation RFQ and comparison map (M8's) and the contract schedule and measurement (M11's).
  Pick them up deliberately or let F3 sweep them.
- **M18 gave the dashboard two abilities.** `dashboard.view` opens the page — everybody holds
  it, because the login lands here — and `dashboard.overview` fills it, reproducing exactly the
  `$role === 'admin'` the view used to carry. Every card then obeys the ability of the module it
  summarises, and every figure is narrowed to the projects the reader may see. **N9 is closed**
  (the search was scoped in M2; M18 added the proof).
- **M18 caught a regression before it shipped:** a guest holds no company-wide ability by
  design, so guarding the dashboard route would have put a 403 in front of every guest on every
  sign-in. `mount()` now redirects them to their project instead of refusing.
- **M18 replaced "Your dashboard is coming soon"** — what every non-administrator has been
  seeing — with a real welcome screen: their own open tasks and shortcut tiles built from the
  sidebar, so a tile can never offer a screen its owner would be refused on.
- **F0 — per-person access — closed P6, P13, P19 and P34**, the four notations that all pointed
  at one missing piece. The owner chose: exceptions that can **add and take away** (a new
  `user_abilities` table, one row per ability a person differs from their role on), and the
  approval ceiling **on the role with a per-person override** (`roles.approval_limit`,
  `users.approval_limit`, both nullable, null = no ceiling). `PermissionResolver::companyAllows()`
  is now the only thing that consults a role. The screen is **Users → Access**; it is two-state to
  edit and three-state in storage, so only real differences are written.
- **F1 — confinement is live and proved.** `ConfinementTest` walks **every** GET route whose only
  parameter is a project or a job site — enumerated from the router, not a list — and refuses a
  confined non-member on all of them while admitting a member to all of them. Plus the lists,
  the search, the reports and the PDFs.
- **F1 added the two screens the plan owed:** the **effective-access inspector** (a second tab on
  Users → Access: every ability with the answer and the reason — *From their role*, *Never allowed
  — set here*, *Module switched off*) and **"Who can approve what"** (a third tab on Roles &
  Access: who may approve, up to how much, and where that ceiling comes from).
- **F1 found the last of P19 while building that report.** `payments.pay` was not marked
  `limited`, so the payments dashboard was still the one way round a ceiling that bound
  everywhere else. It is capped now, against the payment's own amount and the project it belongs
  to. An M11 bookkeeping case had to be **rewritten rather than updated** — the third time in
  this module, which is the sign those cases were doing their job.
- **Worth knowing:** taking an ability off a confined person *company-wide* changes nothing on
  their own projects — their membership grants it, and specific beats general. The place to take
  it away is that project's Team tab. Pinned by a test so a customer does not discover it.
- **F2 removed the legacy bridge**, and with it `AuthorizesAdmin`, the `@admin` Blade directive,
  the `admin` route middleware, `EnsureUserIsAdmin` and four helpers on `User` that asked what
  role somebody held. **`is_manager`, `@admin` and `authorizeAdmin` now appear nowhere in the
  application.** `is_admin` survives in nine places, for one reason — an administrator is allowed
  everything, is never confined and is never capped — and `BridgeRemovedTest` pins the list so a
  tenth has to be a decision rather than a habit.
- **The documentation library was swept last.** Reading it is still open to everybody signed in,
  writing is still manager-and-above and deleting is still administrator-only — but all three are
  grants now, so an install that writes its own procedures into the library can keep an outsider
  out of them.
- **F2 caught a trap and a hole.** The trap: `Task::canDelete()` was a hard-coded `is_admin` and
  `tasks.delete` was **not** in the seeder's admin-only list, so converting it without noticing
  would have handed task deletion to everybody. The hole: `Attachments` had **no guard on
  uploading at all** — the record id came from the browser — so anybody signed in could attach a
  file to any expense, purchase order, requisition or quotation in the install.
- **New ability `tasks.edit_any`** ("change somebody else's task"), because the task guards are
  two layers and reusing `tasks.edit` for the senior half would have collapsed them into one.
  **New ability `meetings.revise`**, for correcting a minute already signed off and mailed out.
- **Next and last: F3 — review and improvements.** Per `CLAUDE.md`'s standing rule, every
  module ends with one, and this backlog is worked rather than archived:
  - **P35 + P37 together** — what a company-wide money area (the six company reports,
    invoices, estimates) shows a **confined** reader. Nothing limits it today; it only bites
    once one of those is granted to a confined person, which F1 made possible.
  - **P39** — on a purchase order, deleting an attachment moved from admin-only to whoever
    holds `purchase-orders.delete`. Leave it, or give attachments their own grant.
  - **P36** — `reports.view` / `reports.export` are declared and used nowhere.
  - **P38** — ~40 catalogue ability names have no pt_BR. The permission matrix and template
    editor are **the only English left in a pt_BR install**. Half an hour.
  - **P22 leftovers** — the quotation RFQ + comparison-map PDFs and the contract schedule +
    measurement PDFs are still `auth`-only. **One line each; no other pass will pick them up.**
  - The review proper: walk the real screens in both themes, both locales and on a phone;
    long names, many members, empty and error states.
- **Documentation of the whole module:** `docs/permissions-module.md` (what was built, step by
  step, with a one-page summary at the top), `docs/permissions-module-plan.md` (why),
  `docs/permissions-notes.md` (the notations N1–N9, all closed or decided),
  `docs/review-and-improvements.md` (P1–P39), and
  **`docs/permissions-for-new-modules.md` (how to do this for a new module)**.
- **Two things to decide before F1 (confinement going live), both the same root cause:**
  P6, no per-user company-wide grant; and P13, `approval_limit` has no company-wide home, so a
  company-wide user currently has no ceiling at all.
- **Two standing rules the owner set during this work:**
  1. Every report says what to expect **and what still will not work** — they test straight
     after reading.
  2. A control whose action would be refused is not rendered; and every destructive action
     needs a guard, including ones that never had an admin check.

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
| **Permissions — HOW TO DO IT FOR A NEW MODULE** | **`docs/permissions-for-new-modules.md`** |
| Permissions — what was built, step by step (complete, deployed) | `docs/permissions-module.md` |
| Permissions — the design and why | `docs/permissions-module-plan.md` |
| Permissions — the notations N1–N9, all closed or decided | `docs/permissions-notes.md` |
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
