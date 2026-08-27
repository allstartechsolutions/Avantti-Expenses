# Despesas — RFI + Aprovações (Submittals) Implementation Plan

**Target codebase:** Despesas (Laravel 12 / Livewire 3.7 / MySQL)
**Owner:** Jr — AllStar Tech Solutions
**Primary market:** Brazil (commercial + residential). US compatibility preserved by design.
**Revision:** 3 — corrected against the codebase. See
[`rfi-aprovacoes-discovery.md`](./rfi-aprovacoes-discovery.md) for the evidence behind every
change.

> **Revision 3 changed the shape of this plan.** Phase 0 discovery found that four assumptions in
> revision 2 do not hold. In short: the database is MySQL, not Postgres; each customer is a
> **separate installation** with one company and one country, so there is no per-tenant market;
> the external-user system revision 2 proposed to build **already shipped on 21 Aug 2026**; and
> the plan omitted all three of CLAUDE.md's "every module ships with" rules. The phase list below
> is re-cut accordingly.

---

## 0. Objective

Add two collaboration features to Despesas:

1. **RFI** — a formal question to the projetista/owner with a tracked answer, ball-in-court, and
   impact flags.
2. **Aprovações (Submittals)** — material, sample, shop-drawing and certificate approval cycles
   with revisions and coded responses.

Both are built on **one shared engine**. Market differences (US vs BR) are expressed as **data,
configuration and translation**, never as branching business logic.

Both are usable by **external parties** (projetista, fornecedor, fiscalização). In Brazil the
party answering an RFI is normally external, so external access is not optional polish — it is
what makes the features work. **That access already exists**: guests are `users` rows with
`is_guest = true` and a project- or job-site-scoped `Membership`. This module grants them
abilities; it does not build them an account system.

**Non-goals for this effort** (do not build, do not stub):

- FVS / FVM (Fichas de Verificação) — these are inspections, not approvals. Separate future
  module.
- Spec-book parsing or MasterFormat submittal-register generation.
- Drawing markup/annotation tooling.
- BIM/IFC anything.
- Self-registration for external users. Invitation only.
- Refactoring the numbering of existing modules (POs, change orders, meetings) onto the new
  sequence service. It buys no feature here and is its own change with its own review.

---

## Resolved decisions

| # | Decision |
|---|---|
| 1 | Documents are scoped to **both**: `project_id` required, `job_site_id` nullable. Numbering sequences are per project, not per site. Both project-level and job-site-level screens ship together, per `docs/project-jobsite-parity-rule.md`. |
| 2 | BR register seeding is **manual multi-select, pre-filtered by two signals**: a nullable per-project value threshold OR a `requires_approval` flag. Never auto-published. |
| 3 | **External access uses the existing guest + membership system.** No second membership table, no second auth guard, no separate portal. Guests reach the same screens, gated by abilities they must be explicitly granted. |
| 4 | Numbering **start value is configurable per project per document type**, editable only while the sequence is unused. |
| 5 | **One installation, one company, one country.** `config('app.country')` is a deploy-time constant. Country decides what is rendered; it never decides what the code does. |
| 6 | `requires_approval` is **copied forward onto `budget_items`** when a cost-code template is applied, because a budget line carries no `cost_code_id`. Additive; does not touch the budget read path. |

---

## Phase 0 — Discovery ✅ complete

**Deliverable:** [`docs/rfi-aprovacoes-discovery.md`](./rfi-aprovacoes-discovery.md), written
26 Aug 2026.

Findings that changed this plan, in one table:

| Finding | Effect |
|---|---|
| Database is **MySQL** | Gapless numbering uses `SELECT … FOR UPDATE`, not a Postgres advisory lock |
| **Not multi-tenant** — separate install per customer, `config('app.country')` read in 99 places, `Company::first()` | `LabelResolver` deleted; no per-project market column |
| **Guest system already deployed** — `users.is_guest`, `memberships`, `UserInvitation`, `InvitationService`, guest permission templates, `scopeVisibleTo()` | Old Phase 2 dropped; replaced by a small delta folded into the RFI phases |
| **`BudgetItem` is the applied cost code**, with no `cost_code_id` | Decision 6 |
| **Two attachment systems** | Use `file_uploads` / `FileUploadService` on R2, the one Meetings uses |
| **No shared numbering service**; `Meeting::nextNumber()` is `max + 1` | `collaboration_number_sequences` is new; build generic, wire only the two new types |
| Plan omitted CLAUDE.md's three ship-with rules | Phase 1 (permissions), translation in every phase, Phase 8 (review) |

Open questions carried forward are listed at the end of the discovery document and repeated in
[§ Open questions](#open-questions) below.

---

## Phase 1 — Permissions and module registration ✅ complete

**Built 26 Aug 2026.** What actually shipped is recorded at the end of this phase; the
nav entries moved to phases 3 and 5, for the reason given there.

**Before the first screen, before the first migration that a screen reads.** CLAUDE.md is
explicit: retro-fitting permissions onto eighteen modules took a week and found forty-odd holes
that had been live in production. This module joins the engine as it is built.

Read `docs/permissions-for-new-modules.md` and copy its checklist (`:284`) into the working notes
for each phase below.

### Declare two areas — `config/permissions.php`

```php
'rfis' => [
    'name' => 'RFIs',
    'module' => 'collaboration',
    'levels' => ['global', 'project', 'job_site'],
    'money' => false,          // see below — the RFI holds no currency
    'swept' => false,          // flips to true at the end of Phase 4
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'answer'      => ['name' => 'Answer an RFI'],
        'close'       => ['name' => 'Close an RFI'],
        'view_impact' => ['name' => 'See cost and schedule impact'],
        'export'      => ['name' => 'Export and print'],
        'distribute'  => ['name' => 'E-mail to the distribution list', 'sensitive' => true],
        'revise'      => ['name' => 'Correct a closed RFI', 'sensitive' => true],
    ],
],

'approvals' => [
    'name' => 'Approvals',
    'module' => 'collaboration',
    'levels' => ['global', 'project', 'job_site'],
    'money' => true,           // an approval hangs off a budget line, and the
                               // seeding screen lists those lines with values
    'swept' => false,          // flips to true at the end of Phase 5
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'submit'          => ['name' => 'Submit a revision'],
        'respond'         => ['name' => 'Record a response'],
        'seed'            => ['name' => 'Generate approvals from the budget'],
        'manage_packages' => ['name' => 'Manage approval packages'],
        'export'          => ['name' => 'Export and print'],
        'distribute'      => ['name' => 'E-mail to the distribution list', 'sensitive' => true],
    ],
],
```

`view_impact` is the mechanism that keeps cost and schedule figures away from a projetista. It is
default-deny, declared in one file, and covered by the Phase 4 test. **Gate on the ability, never
on who the person is** — a `@if(! $user->is_guest)` can be forgotten in a refactor; an ability
that must be explicitly granted cannot.

### The rest of Phase 1

- **Menu entries and tabs — deferred to phases 3 and 5.**
  `AbilityCatalogTest::test_every_menu_entry_is_wired_to_something_real` asserts every declared
  entry points at a route that exists, which is a good test and not one to weaken so that nav
  can be declared before its screens. The `collaboration` sidebar group, the two menu entries
  and the two tabs therefore land with their routes: RFIs in phase 3, Approvals in phase 5.
  There is no menu markup anywhere else, so nothing else needs doing when they land.
- **`config/modules.php`** — a `collaboration` module entry with its `route_prefixes`, plus a
  `module_access` migration following
  `2026_08_19_180013_add_meetings_module_to_module_access.php`. Without this nothing appears in
  the sidebar regardless of permissions.
- **`PermissionSeeder`** — add the new abilities to the system templates where they belong, and
  seed one new guest template:

  ```php
  'projetista-project' => [
      'name' => 'Projetista (external)',
      'description' => 'An external designer answering RFIs and reviewing approvals on one project.',
      'level' => 'project',
      'is_guest' => true,
      'can_see_money' => false,
      'abilities' => [
          'project.view',
          'rfis.view', 'rfis.answer',
          'approvals.view', 'approvals.respond',
          'documents.view',
      ],
  ],
  ```

  Note what is absent: `rfis.view_impact`. That is the point.
- **`ADMIN_ONLY_ABILITIES` / `MANAGER_ONLY_ABILITIES`** updated, each with a reason.
- pt_BR strings for every area name, action label and menu entry, in the same change. Remember a
  key-diff cannot see `__($variable)` — these labels come from config and must be checked
  directly.

**Acceptance:** the areas resolve through `PermissionResolver`; the menu and tabs render for a
user holding the abilities and vanish for one who does not; the module switch hides everything;
`swept => false` keeps existing behaviour unchanged for company-wide users.

### As built, 26 Aug 2026

| | |
|---|---|
| `config/modules.php` | `collaboration` declared **before** `projects` — the module check stops at the first matching prefix and `projects.*` would otherwise claim `projects.rfis` |
| `config/permissions.php` | `rfis` (10 actions, `money => false`) and `approvals` (10 actions, `money => true`), both `swept => false` |
| Migration | `2026_08_26_100000_add_collaboration_module_to_module_access.php` |
| `PermissionSeeder` | 3 abilities held to admin, 6 held to manager, each with its reason; abilities added to Project Manager and Site Supervisor; new `projetista-project` guest template |
| `lang/en.json`, `lang/pt_BR.json` | 15 keys each, added in the same change |
| Tests | 534 permission tests green |

Catalogue after the sync: **32 areas, 167 abilities** (was 30 / 147). Grants landed as
intended — manager 17 of the 20 new abilities, employee 11, admin all, and the projetista
template 6, **without `rfis.view_impact`**, which is the whole point of that ability.

Two things were corrected along the way rather than shipped as they were, both recorded in
`docs/review-and-improvements.md` (C2):

- The team tab's rollout notice keyed off `unsweptAreas()` and said "This team list does not
  restrict anybody yet… every signed-in person can still reach it". Declaring two areas would
  have resurrected it on every project, saying something untrue about 30 enforced modules.
  Deleted, with the test inverted to assert the file is gone.
- `SyncPermissions::report()` said "Still on the legacy bridge"; there is no bridge. It now
  reads "Declared, not enforced yet".

`tests/TestCase.php` gained `AREAS_UNDER_CONSTRUCTION`, which the five suites asserting the
catalogue is wholly enforced now compare against. **Empty it as each area flips** and those
assertions go back to proving `[]` with nothing to edit.

One finding parked: module names and descriptions render untranslated from the `module_access`
table for all twelve modules — pre-existing, same class as P38, logged as **C1**.

---

## Phase 2 — Collaboration foundation (shared engine) ✅ complete

**Built 26 Aug 2026.** As-built notes at the end of this phase.

Everything here is shared by RFI and Aprovações. Build it first, with tests, before either
feature. **New migration files only — never edit a migration that has run.**

### Migrations

```
collaboration_number_sequences
  id, project_id, document_type (string), template (string),
  start_value (int, default 1), current_value (int),
  locked (bool, default false), timestamps
  unique(project_id, document_type)

collaboration_response_codes
  id, project_id (nullable = global default), market (string: 'us'|'br'),
  document_type (string), code (string), label_key (string),
  canonical (string), closes_cycle (bool), sort (int), timestamps

collaboration_distribution_entries
  id, distributable_type, distributable_id, user_id (nullable),
  external_name (nullable), external_email (nullable),
  role (string), timestamps

collaboration_signatures
  id, signable_type, signable_id, user_id,
  signer_name, signer_document (nullable),   -- CREA/CAU registration
  art_number (nullable), method (string),    -- 'drawn'|'gov_br'|'icp_brasil'
  signed_at, ip_address, payload_hash, timestamps

collaboration_activity_log
  id, subject_type, subject_id, user_id, action (string),
  context (json), ip_address, created_at
```

`payload_hash` is a hash of the signed document snapshot — that is what makes a signature
evidentiary rather than decorative. Compute it from a canonical serialization of the record at
sign time.

`collaboration_activity_log` records views and actions, **including guest views**. This matters
evidentially in BR and is cheap now. It follows the per-module precedent already in the codebase
(`DocumentActivity`, `ExpenseChangeHistory`, `PermissionAudit`), not a new global mechanism.

There is no signature concept anywhere in the codebase today — `collaboration_signatures` is
genuinely new. `DailyReport` has `locked_at` / `locked_by`, which is a lock, not a signature.

### Numbering — MySQL

- `template` renders patterns like `{prefix}-{discipline}-{seq:000}` → `SI-ARQ-014`.
- `start_value` is set at project setup, per document type, and defaults to 1. A project migrating
  from a spreadsheet can start RFIs at 47.
- `locked` flips to true the first time a number is issued. While `locked = false` the user may
  edit `template` and `start_value`; once locked both are read-only. **Enforce in the model with a
  clear validation message, not a silent failure.**
- Sequences are gapless. Inside a transaction: `SELECT … FOR UPDATE` the sequence row, read
  `current_value`, increment, write, commit. **Never `max(number) + 1`** over the document table —
  which is, for the record, what `Meeting::nextNumber()` does today
  (`app/Models/Meeting.php:72`). Do not copy it, and do not fix it here.
- RFI and Aprovações get separate sequences by default (separate `document_type` rows).
- Build the service generic so POs, change orders and meetings *can* migrate later. Wire only
  `rfi` and `approval` now.

### Canonical response codes

Response codes are seeded rows, not a PHP enum. **Business logic branches on `canonical`, never
on `code` or label.**

| canonical | US code / label | BR code / label |
|---|---|---|
| `approved` | A — Approved | A — Aprovado |
| `approved_as_noted` | B — Approved as Noted | B — Aprovado com comentários |
| `revise_resubmit` | C — Revise and Resubmit | C — Reapresentar |
| `rejected` | D — Rejected | D — Reprovado |
| `for_record_only` | E — For Record Only | E — Somente para conhecimento |

The seeder creates both market sets as global defaults (`project_id = null`). **Which set is
active is read once from `config('app.country')`** — not per project, because an installation
serves one country. `project_id` stays nullable so a company can override the letters later; no
UI for that now.

Workflow status uses the same approach, with canonical values
`draft | open | in_review | closed | void`.

**Never print a stored status.** Each model gets `getStatusLabel()` with a
`static …Label(?string $value)` beside it, so filter values and history rows can be labelled
without an instance. Mind gender: *aprovação*, *revisão* and *solicitação* are feminine and need
their own keys — `Expense::getStatusLabel()` is the worked example.

### Services

- `NumberSequenceService` — as above.
- `BallInCourt` — a concern giving a model a current responsible party, a due date and an overdue
  scope.

**`LabelResolver` is deleted from this plan.** Its job was project override → market default →
`lang/` file. With one country per installation that is simply `__()`, which already carries 2,828
en keys and 4,319 pt_BR keys. "RFI" vs "Solicitação de Informação (SI)" is a translation. A second
label path would mean two places to look when a word is wrong.

### Traits

- `HasSequentialNumber`
- `HasDistributionList`
- `HasSignatures`
- `LogsCollaborationActivity`
- `BallInCourt`

**`HasAttachments` was dropped.** It would have wrapped a single
`morphMany(FileUpload::class, 'attachable')` — which is exactly what `Meeting`, `Task`,
`Quotation` and `PurchaseRequisition` each declare inline today. A trait for one line, used
nowhere else in the codebase, is a new abstraction that makes this module the odd one out;
CLAUDE.md's "consistency beats novelty" and "check existing code first" both point the other
way. RFI and Approval declare `files()` like every other module. The R2 path and
`FileUploadService` are unchanged — that part of the discovery finding stands.

**Acceptance:** feature tests covering gapless numbering under concurrency, custom start values,
the locked-sequence guard, and seeded response codes resolving from `config('app.country')` for
both values.

### As built, 26 Aug 2026

| | |
|---|---|
| Migrations | 5 tables + 1 data migration seeding the response codes |
| Models | `App\Models\Collaboration\{NumberSequence, ResponseCode, DistributionEntry, Signature, ActivityLogEntry}` |
| Service | `App\Services\Collaboration\NumberSequenceService` |
| Concerns | `App\Models\Concerns\Collaboration\{HasSequentialNumber, HasDistributionList, HasSignatures, LogsCollaborationActivity, BallInCourt}` |
| Seeder | `CollaborationResponseCodeSeeder`, run by its own migration **and** registered in `DatabaseSeeder` |
| Tests | 53, in `tests/Feature/Collaboration/` |

**Numbering is verified against MySQL, not only sqlite.** The suite runs on in-memory sqlite,
which has no row locks and compiles `lockForUpdate()` away, so the cross-connection behaviour
cannot be asserted there — and `RefreshDatabase` wraps each test in a transaction that never
commits, so a second connection could not observe the first even on MySQL. The blocking was
therefore verified by hand in a scratch MySQL database: a second connection asking for the same
sequence row waited on the first and timed out at 50s rather than reading a stale counter. That
is the guarantee. What the suite keeps is the mechanism —
`test_the_counter_is_read_under_a_row_lock_inside_a_transaction` asserts the service reads with
`lockForUpdate()` inside `DB::transaction`, never `max()`, and adds the `for update` SQL check
when the driver is MySQL. The whole file was also run green against real MySQL.

**Two things changed while building:**

- **A signature must hash what is *stored*, not what is in memory.** The first version hashed
  the model instance, and a document signed immediately after creation produced a hash nothing
  loaded afterwards could match — the columns the database defaulted were not on the in-memory
  model. `storedSignatureHash()` now takes a fresh copy (without mutating the caller's
  instance, so unsaved edits are not silently discarded), and both `sign()` and
  `signatureIsIntact()` go through it. Covered by
  `test_the_hash_is_stable_across_reads_and_ignores_unsaved_edits`.
- **`morphs('distributable')` generates a 76-character index name and MySQL stops at 64.**
  Named explicitly. Worth remembering for phases 3 and 5: any `morphs()` on a
  `collaboration_*` table needs its own index name.

**Kept deliberately:** `signaturesAreIntact()` returns false for a document with no signatures.
Vacuous truth would be the wrong answer — "every signature holds" must not read as "this is
signed".

**Not done here, on purpose:** nothing existing was moved onto the sequence service.
`Meeting::nextNumber()` is still `max + 1`. Refactoring live numbering buys no feature in this
module and is its own change with its own review.

---

## Phase 3 — RFI, internal ✅ complete

**Built 26–27 Aug 2026, one screen at a time.** As-built notes at the end of this phase.

### Migration

```
rfis
  id, project_id, job_site_id (nullable)
  number (string, unique per project)
  subject, question (text)
  discipline (string, nullable)
  spec_section (string, nullable)      -- US only; hidden when country = BR
  drawing_ref (string, nullable)       -- BR: prancha + revisão, e.g. "ARQ-04 rev.C"
  status (string)                      -- canonical workflow status
  priority (string)
  ball_in_court_id (fk users, nullable), due_date (date, nullable)
  cost_impact (bool, default false)
  schedule_impact (bool, default false)
  schedule_impact_days (int, nullable)
  answer (text, nullable), answered_by_id (nullable), answered_at (nullable)
  change_order_id (nullable, fk change_orders)
  created_by_id, timestamps, soft deletes
```

### Rules

- `answer` and `answered_at` are **immutable once status is `closed`**. Later corrections append a
  new comment entry, never an in-place edit. Enforce in a saving observer, not only in the UI.
  Correcting a closed RFI needs `rfis.revise`, which is flagged `sensitive` — the same treatment
  `meetings.revise` gets (`config/permissions.php:1025`), and for the same reason.
- When `cost_impact` or `schedule_impact` is true and the RFI closes, surface a
  *"Gerar aditivo / Create change order"* action linking to the change-order flow.
  **Do not auto-create.**
- `ball_in_court_id` may point to a guest user — that is the normal BR case.
- `spec_section` is nullable and hidden on BR installs; `drawing_ref` is the BR equivalent and is
  the more prominent field there. This is a rendering decision:
  `@if(config('app.country') === 'BR')`, the pattern CLAUDE.md prescribes and 99 other places
  already use. **A service must never ask the country what to decide.**
- Discipline list for BR seed: Arquitetura, Estrutura, Hidráulica, Elétrica, Climatização,
  Incêndio, Fundações, Paisagismo, Outros. Configurable per project — this genuinely varies
  between two jobs in the same country, which is why it is data and the market is not.

### UI

- Full-page Livewire components for index, create/edit and detail, in `app/Livewire/Rfi/` with
  views in `resources/views/livewire/rfi/`.
- Inline components for the reusable pieces: ball-in-court picker, distribution list,
  response-code selector, attachment list.
- Index filters: status, discipline, ball-in-court, overdue, impact flags, job site.
- **Both levels ship together** — project and job site — per
  `docs/project-jobsite-parity-rule.md`.
- The detail view shows everything the record knows, including created by / created at / last
  updated, per the CLAUDE.md design standard. Empty, partial and error states are designed too.

### Permissions in this phase

- `mount()` guarded, and **every** action method guarded — not just the destructive ones. The
  `wire:click` behind a hidden button is a public endpoint.
- `scopeVisibleTo()` on `Rfi`, and aggregates narrowed with it too — a count across projects
  somebody cannot open is a leak by aggregate.
- Any `Rfi` fetched by id from the browser checked against **its own** project, not the screen's.
  `findOrFail($id)` proves a record exists, not that this person may touch it.
- `cost_impact`, `schedule_impact_days` and anything derived from them behind
  `@can('rfis.view_impact')`.
- Attachment directory added to `FileController::authorizeFile()`
  (`app/Http/Controllers/FileController.php:96`).
- `__()` on every string with the pt_BR value in the same change.

**Acceptance:** create → distribute → answer → close, with immutability enforced after close and
numbering correct; the right field set renders for the configured country.

### As built so far, 26 Aug 2026

Built one screen at a time, each tested before the next (CLAUDE.md rule 7).

| Slice | What landed | Tests |
|---|---|---|
| Record | `rfis` migration, `App\Models\Rfi` | 23 |
| Index | `ManagesRfis` concern + `ProjectRfis` / `JobSiteRfis`, tab declared | 21 |
| Detail | `App\Livewire\Rfi\RfiShow`, route `rfis.show`, index rows wired | 26 |
| Form | `App\Livewire\Rfi\RfiForm`, three routes, entry points wired | 22 |

**145 tests across the module**; the full suite stands at 771 passing with the three
pre-existing failures (`RegistrationTest` ×2 and `ExampleTest`, none of them touched here).

**The freeze is in the model, not the form.** `Rfi::booted()` refuses a change to
`answer`, `answered_at`, `answered_by_id`, `question` or `subject` once the status is `closed`.
Reopening stays allowed — it is a decision of its own — and `revise()` is the single route
past the freeze, unlocking for exactly one save and logging the reason together with the answer
it replaced. Housekeeping columns such as `discipline` stay writable. The component repeats the
refusal as a sentence rather than letting a 500 reach the reader, but the record is what
enforces it.

**Nav landed with its routes.** The `rfis` tab was declared in the same change as
`projects.rfis` / `jobsites.rfis`, so
`AbilityCatalogTest::test_every_menu_entry_is_wired_to_something_real` never had to be
weakened. Five bookkeeping tests were updated for the new tab, as §8 of
`permissions-for-new-modules.md` intends.

**`Rfi.php` is the eighth pinned `is_admin` site** in `BridgeRemovedTest`, which exists to make
a new one a decision rather than a habit. The decision: the resolver bypasses ability checks
for an administrator, so one given `access_scope = assigned` could open any RFI through a guard
while `visibleTo()` refused to list it. A list and a guard that disagree is worse than either
answer alone. The reasoning is in the test.

**Things worth carrying into phases 4 and 5:**

- A membership carrying only a `permission_template_id` **holds nothing**. Abilities are copied
  onto the membership, exactly as `InvitationService::createMembership()` does on acceptance.
  A test that skips the `syncAbilities()` call gets a 403 that looks like a permission bug.
- The modal component's close event takes a **positional** argument —
  `$this->dispatch('close-modal', 'name')`, not a named one — because it compares
  `$event.detail == name`.
- Every action guards against `$this->rfi->jobSite ?? $this->rfi->project`, the record's own
  scope, never one the request supplied. `test_a_member_of_another_project_cannot_open_it`
  is the case that proves it.

**Attachments are accepted in the same step as the record.** Save-then-reopen-to-attach is what
this codebase was called out for once already; somebody raising an RFI has the drawing in front
of them at that moment. The files are stored after the insert — a file needs a record to hang
from — but from the reader's side it is one action. `FileUploadService::storeLocal()` streams
server-side, so it behaves the same whether the install is on R2 or a local disk.

**Registering a new upload target is three places, not one.** `FileUploadService` has
`TARGETS`, `canUploadTo()` and `objectKey()`, and the last of those throws on an unknown target
— which is how this was found. Reads need their own guard as well: `RfiShow::downloadFile()`
checks both that the file belongs to *this* RFI and that this person may read it, because
walking the ids from a page you are legitimately on is otherwise enough. `FileController` was
**not** touched: it serves the legacy default-disk paths, and RFI files go through
`file_uploads`.

**Two Livewire behaviours worth knowing before phase 5 repeats them:**

- A class-typed `mount()` parameter the route does not fill is **resolved from the container**,
  so it arrives as an empty model rather than as null. `RfiForm::mount()` therefore tests
  `$rfi?->exists`, not truthiness — the create route would otherwise read as an edit.
- Reading a model into form properties needs null-coalescing throughout: the columns the
  database defaults are not on an instance that has not been read back, and a typed `string`
  property refuses null. Same root cause as the signature-hash finding in phase 2.

**`test_every_action_method_carries_its_own_guard`** checks mechanically that every public
action on both components calls `authorizeAbility`, because the failure it catches is one of
omission — a method added next year whose only protection is the button in front of it. It
asserts *which* seven methods it inspected, so a filter that quietly skipped everything cannot
pass it. Methods contributed by `WithFileUploads` are skipped by file, not by name: a trait's
methods report the using class as their declaring class while living in the trait's file.

**One change outside the module.** The suite runs in a single process, so memory is cumulative.
Adding ~145 cases pushed whichever PDF test ran late enough over the 256M CLI default and it
died inside dompdf. Nothing leaks — there is simply more suite than there was — so
`phpunit.xml` now sets `memory_limit` to 512M, with the reason in a comment beside it.

---

## Phase 4 — RFI for external parties ✅ complete

**Built 27 Aug 2026.** `rfis` is `swept => true`; 31 of 32 areas are now enforced.

Small, because the account system already exists. This phase is the delta.

- **`users.company_name`** (nullable) — the projetista's firm, for the PDF signature block and the
  RFI header. One additive migration.
- **`projetista-project` guest template** seeded in Phase 1, now exercised end to end.
- **Guest views and actions logged** to `collaboration_activity_log` via
  `LogsCollaborationActivity`.
- **The leak test** — assert that an RFI detail response rendered for a guest contains none of:
  `cost_impact`, `schedule_impact_days`, `change_order_id` or any budget value. This is the test
  revision 2 asked for, run against the real screens rather than a duplicate set.
- **A guest-shaped answer view** — the same detail component, with the answer form enabled by
  `rfis.answer` and everything else read-only. Mobile-first: projetistas open these on a phone.

What is **not** built, because it is already live and verified in the discovery document:

| | |
|---|---|
| Invitation by name + email + role, scoped to one project | `InvitationService::invite()` |
| Invitee sets a password, no self-registration | `InvitationService::accept()` |
| Access revocable per project, immediately | `revoked_at` → `Membership::active()` → `PermissionResolver::decide()` |
| Confinement to assigned projects | `User::effectiveAccessScope()` forces `ASSIGNED` for every guest |
| Unauthorized record returns 404, not 403 | `scopeVisibleTo()` — the record is not in the result set |
| No monetary figures | `can_see_money = false` on the membership → `<x-ui.money rollup>` |
| Rate-limited auth | Fortify, five logins per minute (`config/fortify.php:111`) |

**Then flip `rfis` to `swept => true`**, with the module permission test written to the four-part
shape the checklist requires: reproduced, revocable, scoped, separate.

### As built, 27 Aug 2026

`tests/Feature/Permissions/RfiTest.php` — 15 cases in the four groups, plus the leak test.

**"Reproduced" means something else for a module with no past.** There is no previous behaviour
to preserve, so what that group pins instead is that the seeded roles land exactly where
`PermissionSeeder`'s hold-back lists say: both deletes and `rfis.revise` held from manager and
employee alike, `rfis.close` and `rfis.distribute` held from the employee, everyday work held by
both. That is the thing that would otherwise drift silently.

**The leak test runs against the real screens**, which is the whole argument of revision 3. It
walks a guest through the index and the detail page and asserts the body carries no impact
label, no change-order reference and no rendered day count. What keeps them out is
`rfis.view_impact`, which the projetista template does not hold — not a second set of components
that would drift from the first.

Two things the test itself got wrong before it got them right, both worth remembering:

- **`MembershipStatus` has no `REVOKED`.** Revoking is `revoked_at` set and the status moved off
  `ACTIVE`; `Membership::active()` wants both.
- **A bare number is not a probe.** Asserting a page does not contain `17` fails on any real
  HTML document — class names, ids and dates all carry it — and would have failed whether or not
  anything leaked. The assertion is the *rendered phrase*,
  `trans_choice(':count day|:count days', 17)`.

**`users.company_name`** landed with the `users` migration and is settable on the user edit
screen, where a person's own details live. It shows beside the answerer on an RFI — "João Silva
· Projetos Silva Arquitetura" — and is what the phase 7 signature block will read. It was
deliberately **not** threaded through the invitation chain: that is four files in a deployed
flow for a consumer that arrives in phase 7.

**Guest views were already logged** — `RfiShow::mount()` calls `logView()` for everyone, guests
included, which is what makes "sent on the 4th, opened on the 5th" answerable.

**Two bookkeeping rolls were updated**, as §8 intends: `LegacyBehaviourTest::CONVERTED` and the
list in `SecurityStateTest`, each with a line saying `rfis` was never on the bridge — it was
declared unswept only while its screens were being written.

**Acceptance:** invite a projetista → accept → answer the assigned RFI → close; a revoked guest
loses access on the next request; a project they are not on returns 404; the leak test passes.

---

## Phase 5 — Aprovações (Submittals) ✅ complete

**Built 27 Aug 2026.** `approvals` is `swept => true` — **32 of 32 areas enforced**, and
`AREAS_UNDER_CONSTRUCTION` is empty again.

### Migrations

```
approvals
  id, project_id, job_site_id (nullable)
  number (string, unique per project), title
  type (string)   -- material | amostra | shop_drawing | prototipo
                  -- | ficha_tecnica | laudo_certificado | as_built
  spec_section (string, nullable)          -- US only
  budget_item_id (nullable)                -- BR spine: the budget line
  catalog_item_id (nullable), supplier_id (nullable)
  current_revision (string), status (string)
  ball_in_court_id (nullable), due_date (date, nullable)
  package_id (nullable)                    -- US submittal packages
  created_by_id, timestamps, soft deletes

approval_revisions
  id, approval_id, revision (string)       -- '0','1','2' or '0','A','B'
  submitted_by_id, submitted_at
  response_code_id (nullable), responded_by_id (nullable),
  responded_at (nullable), comments (text, nullable)
  timestamps

approval_reviewers
  id, approval_revision_id, user_id (not nullable), sequence (int),
  role (string), responded_at (nullable), timestamps

approval_packages
  id, project_id, number, title, status, timestamps

approval_certificate_details        -- for type = laudo_certificado
  id, approval_id, issuing_body, certificate_number,
  issued_at (nullable), valid_until (nullable), timestamps
```

> **Changed from revision 2:** `cost_code_id` became `budget_item_id`. The project's applied cost
> code *is* the `BudgetItem` — `cost_codes` is the template library. See discovery item 4.

### Rules

- `approval_reviewers.sequence`: equal values = parallel review; ascending = sequential. One
  mechanism covers the US chain (GC → Architect → Engineer) and the typical BR
  direct-to-projetista flow.
- Reviewers are real users, staff or guest.
- A revision closes when a response code with `closes_cycle = true` is recorded by the final
  reviewer in sequence. `revise_resubmit` opens a new revision rather than closing the approval.
- `laudo_certificado` is first-class, not an afterthought — INMETRO conformity certificates and
  laudos de ensaio are the most-used type in BR residential and commercial work.
- `shop_drawing` approvals require a signature (Phase 7) before a response can be recorded, when
  the project is configured to require it.
- Same permission work as Phase 3: guards on every action, `visibleTo()`, `authorizeFile()`,
  `__()` + pt_BR in the same change. **Then flip `approvals` to `swept => true`.**

**Acceptance:** a full revision cycle including a `revise_resubmit` round-trip creating revision 1;
parallel and sequential reviewer configurations both work; a guest reviewer can respond.

### As built, 27 Aug 2026

| Slice | What landed | Tests |
|---|---|---|
| Cycle | 5 tables, `Approval` + 4 supporting models | 27 |
| Index | `ManagesApprovals` + `ProjectApprovals` / `JobSiteApprovals`, tab declared | 15 |
| Detail | `ApprovalShow`, route, index rows wired | 21 |
| Form | `ApprovalForm`, three routes, entry points wired | 20 |
| Permissions | `tests/Feature/Permissions/ApprovalTest.php` | 11 |

**The approval is the subject; each round is an `ApprovalRevision`.** That split is the design:
a rejection is a fact about the submission that was rejected, not about the material, so a
second attempt cannot erase the record of the first. The detail page shows every round, and a
test asserts revision 0's comment survives revision 1 being submitted.

**One mechanism carries both review shapes.** Equal `sequence` values review together, ascending
ones in turn — the US chain is 1, 2, 3, the usual BR flow is a single row, and "both engineers,
either order" is two rows at 1. The page says which it is in words rather than leaving the
reader to infer it from a column of numbers.

Three rules the cycle tests pin down:

- **The last coded word belongs to the last reviewer.** A chain stays open until its final link
  answers; otherwise it is a race, not a chain.
- **A send-back ends the round at once**, whoever gives it. There is no sense asking the engineer
  to review a drawing the architect has already returned.
- **A rejection closes the revision, not the approval.** What follows is a fresh submission,
  which is a new cycle — so `rejected` leaves the approval open to revision 1.

Also refused: a submission naming nobody. A round nobody was asked to look at can never come
back, and would sit "in review" for ever.

**`laudo_certificado` is first-class**, as the plan insisted. Issuing body, number, issue date
and validity, with the approval warning both when a certificate has expired and when it is
about to. That surfaces three ways — on the index row, as its own summary card (only where
there are certificates at all), and at the top of the detail page. Changing an approval's type
away from a certificate **drops the row**, or an orphan validity date would keep counting
towards the lapsing total.

**Two things carried over from the RFI phases, both of which bit again:**

- Registering an upload target is **three places** in `FileUploadService` — `TARGETS`,
  `canUploadTo()` and `objectKey()`. Approvals and revisions are both targets: a revision's
  files must not appear to be the next revision's.
- A class-typed `mount()` parameter the route did not fill arrives as an empty model, so
  `ApprovalForm::mount()` tests `$approval?->exists`.

**Bookkeeping:** `Approval.php` joined the pinned `is_admin` list for the same reason as
`Rfi.php`, and `LegacyBehaviourTest::CONVERTED` and `SecurityStateTest` both record the pass.

---

## Phase 6 — Budget-driven seeding ✅ complete

**Built 27 Aug 2026.** 22 tests; the module stands at 250.

Replaces the US spec-book-driven submittal register.

### Migrations

```
projects
  + approval_seed_threshold (decimal, nullable)   -- null = no value pre-filter

cost_codes
  + requires_approval (bool, default false)
  + default_approval_type (string, nullable)

budget_items
  + requires_approval (bool, default false)
  + default_approval_type (string, nullable)

catalog_items
  + requires_approval (bool, default false)
  + default_approval_type (string, nullable)
```

> **The reason `budget_items` is in that list — read before building.** A `BudgetItem` is the
> project's applied cost code, copied out of a `CostCodeTemplate`, and it carries **no
> `cost_code_id`**. A flag living only on `cost_codes` therefore cannot be read from a budget
> line. The flag is copied forward at template-apply time, the same way `code` and `name` already
> are. Existing budgets get `false` and can be marked up by hand.
>
> The alternative — adding `cost_code_id` to `budget_items` and backfilling by matching `code` —
> is cleaner long term but touches a table that Expenses, POs, Change Orders and `CostCodeLedger`
> all read from, in production. That is separate work with its own review.

### Behaviour

A *"Gerar aprovações do orçamento"* action (`approvals.seed`) opens a multi-select screen listing
budget lines. A line is **pre-checked** if either:

- its value exceeds `approval_seed_threshold`, when the threshold is set, **or**
- its `requires_approval` flag is true.

The user confirms the selection. Selected lines create **draft** approvals. Nothing is
auto-published and nothing is created without confirmation.

**Why both signals:** value alone is the wrong filter. The highest-value lines in a BR orçamento
are concreto, aço and alvenaria — commodities with an NBR, approved by certificate if at all. The
items needing a review cycle are spec-sensitive: porcelanatos, louças e metais, esquadrias,
vidros, elevadores, climatização, impermeabilização. The threshold is a crude first pass;
`requires_approval` is what makes seeding accurate, and it compounds across projects as the
company marks up its catalogue.

**Budgets lock** (`BudgetLockHistory`, migration `2026_08_21_120000`). Seeding reads a locked
budget; it must never try to write to one.

### Default type mapping

Seed `default_approval_type` so users are not picking a type forty times:

- flagged finish/fixture items → `material`
- esquadrias, estrutura metálica, pré-moldados → `shop_drawing`
- concreto, aço, impermeabilizante → `laudo_certificado`

### As built, 27 Aug 2026

`App\Services\Collaboration\ApprovalSeeder` + `App\Livewire\Approval\ApprovalSeedFromBudget`,
reached from a **Generate from the budget** button on the project approvals page. The job-site
page does not carry it: the budget this works from is the project's.

**The copy-forward is the load-bearing part**, and it is one edit in
`Budget::applyTemplate()`. A `BudgetItem` carries no `cost_code_id` back to the library row it
came from, so a flag left on `cost_codes` could never be read from the budget line that needs it.
`test_the_flag_travels_from_the_template_into_the_budget` is the guard on that, and it also
asserts an unflagged code stays unflagged — a copy-forward that flagged everything would pass a
weaker test.

**A unit bug the tests caught in this phase's own code.** `budgeted_amount` has an accessor
dividing by 100, and `approval_seed_threshold` is stored in cents. Comparing them directly
compares reais against centavos and suggests almost nothing — a failure that looks like "the
feature is quiet" rather than like a bug. The comparison now uses
`getRawOriginal('budgeted_amount')`, and `test_the_threshold_compares_like_with_like` states the
figures in both units so the next person cannot get it wrong silently.

**Other decisions:**

- **Parent lines are not offered.** A parent is a heading; the work is on its children.
- **A line already covered is skipped, not duplicated** — seeding twice creates nothing the
  second time, and the screen greys those rows and names the approval that covers them.
- **Every id is re-checked against the project** in `seed()`, because the list came from a
  screen. A budget line from another project is ignored rather than costed here.
- **An approval follows its budget line to a job site** when the line belongs to a site's own
  budget.
- The type guess is offered so nobody picks a type forty times, but `default_approval_type` on
  the flag wins wherever it is set — that is a decision somebody already made. An unknown type
  from the browser falls back to the guess rather than being stored.
- Budget figures on the screen go through `<x-ui.money … rollup>`: a budget line is the
  company's financial picture, so `can_see_money` hides it.
- `MAX_LINES = 500`, and it is a **stated** cap rather than a silent one.

**A test-suite trap worth remembering:** a helper method named `seeder()` on a test class is
picked up by `RefreshDatabase`, which reads `$this->seeder` to decide which database seeder to
run — every test in the file then dies inside `SeedCommand` with a type error that says nothing
about the real cause. Name it anything else.

---

## Phase 7 — Export, signature, distribution ✅ complete

**Built 27 Aug 2026.** 20 tests; the module stands at 270.

- **PDF export** per document, one renderer with per-country Blade templates. The BR template
  carries the empreendimento header, RT name + CREA/CAU registration, ART number and a signature
  block; the US template is a transmittal cover sheet. Follow `MeetingMinuteRenderer`.
  **Every PDF controller carries the same grant as the screen it renders** — there are 26 of them
  in the codebase and this is the item most often missed. The abilities this phase needs
  (`rfis.export`, `rfis.distribute`, `approvals.export`, `approvals.distribute`) were declared
  in phase 1, so there is no second pass over `config/permissions.php` and no re-sync.
  **PDF templates are translated too** — they are the first of the four things the pt_BR audit
  found people forget.
- **Signature** wired to `collaboration_signatures`. Start with a drawn/typed signature plus
  `payload_hash`. Structure `method` so gov.br and ICP-Brasil can be added later without a
  migration.
- **Distribution** — e-mail the document PDF to the distribution list, following
  `MeetingMinuteDistributor` and `MeetingService::distribute()`
  (`app/Services/MeetingService.php:99`). Respect `users.notification_preferences`. Log every
  send. **Mail subjects and bodies are translated.**

### As built, 27 Aug 2026

| | |
|---|---|
| Renderer | `CollaborationDocumentRenderer` — one place for the bytes, four templates |
| Controller | `CollaborationPdfController`, four routes |
| Distributor | `CollaborationDistributor` + `CollaborationDocumentMail` |
| Screens | `SignsAndDistributes` concern, shared by both detail pages |

**The PDF guard is in the controller, not in middleware, and that was a
decision rather than a shortcut.** `ability:` middleware resolves a `project`
or `jobSite` route parameter; these routes carry the document instead, so
naming the bare ability would have been a **weaker** check than the screen's —
anybody holding `rfis.export` anywhere could print any project's RFI. The
controller asks about the record's own job site or project, exactly as
`RfiShow::mount()` does, and `test_the_export_grant_does_not_cross_projects`
proves it: the same person prints their project's RFI and is refused another's.
This module is not adding a fifth entry to the four unguarded PDF controllers
still open in `review-and-improvements.md`.

**`payload_hash` finally earns its place.** A signature is printed with its
CREA/CAU registration and ART number, and a sheet whose content has changed
since signing **says so on the page and in the PDF** rather than printing a
stale signature silently. That is the whole reason phase 2 computed a hash of
the stored record instead of just recording that a button was pressed.

**One renderer, four templates**, chosen by `config('app.country')` at render
time. The BR sheet cites the prancha and carries the signature block; the US
sheet cites the specification section. `test_the_country_decides_which_sheet_is_printed`
renders the *same record* both ways and asserts each sheet carries its own
reference and not the other's — presentation, with nothing about the record's
behaviour changing.

**Other decisions:**

- An unanswered RFI prints with ruled blank lines, so the sheet can be answered
  on paper and scanned back. That is how a good many BR sites still work.
- A draft prints stamped `DRAFT — NOT YET ISSUED`, so a paper copy is never
  mistaken for the record.
- **One bad address does not stop the rest.** A wrong e-mail on one line of a
  distribution list is no reason for the other five not to receive the drawing:
  failures are counted, logged and reported back in the flash message.
- Every send is logged with **who actually received it**, so "the projetista was
  sent this on the 4th" is answerable afterwards.
- The document is asserted as **HTML rather than PDF bytes** — what matters is
  what the sheet says, and the bytes are compressed.

**A near miss worth recording:** the first version of the renderer looked for
`$company->logo_path`. The column is `logo`, and the file sits under the public
disk — so it would have returned null on every install and quietly printed
every sheet without the letterhead. `MeetingMinuteRenderer::logo()` had it
right; this now uses the same resolution rather than a second opinion.

---

## Changes after the code review (27 Aug 2026)

Everything below post-dates the phase sections above. **Where the two disagree, this section
is what is in the code.**

### The code review — 15 findings, all fixed

`/code-review high` over the branch. The four that mattered:

- **`scopeOverdue()` hardcoded `['closed','void']`**, which are an RFI's settled statuses and
  not an approval's. An approved approval past its date was counted in the Overdue card while
  its row rendered as not overdue. The scope now reads the model's own `LIVE_STATUSES`.
- **"Due today" disagreed with itself** — `isPast()` on a midnight-cast date said late from
  00:00:01, `whereDate('<', now())` said not. Late now means *past* the due date, both ways.
- **Every signature self-invalidated.** `signaturePayload()` carried `status` (and
  `current_revision` on approvals), so closing an RFI or answering an approval reported the
  signature as broken on an untouched document. The payload is the words now.
- **Any user id could be put on a distribution list** — `distributionRows.*.user_id` had no
  rule and no scope check, so a crafted payload posted the document to somebody with no
  membership. Same hole in `passBall()`.

Plus: silent upload rejections, `reopen()` with no state check, job-site moves authorized
against the *old* scope, an N+1 inside the seeding transaction while it held the sequence lock,
`candidates()` re-scanning per call, `storedSignatureHash()` re-reading per signature, a
comment claiming a `FileController` guard that did not exist, and `Z` → `[` on revision labels.

**A sixteenth the fixes uncovered:** `Approval::LIVE_STATUSES` excluded `rejected`, but
`settleFrom()` hands a rejected approval back to its raiser and `submit()` accepts the next
round — so a rejected approval vanished from the default "Open approvals" list, the one item
on the screen that needed action. `LIVE_STATUSES` is now the exact complement of `isClosed()`.

### The suite had never run as a BR install

`phpunit.xml` pinned neither `APP_COUNTRY` nor `APP_LOCALE`, so assertions depended on the
developer's `.env` — and passed only because a cached config said US while `.env` said BR.
Both are pinned (US / en); tests that care about the other market set it explicitly.

**Run both before believing anything:**

```bash
php artisan test tests/Feature/Collaboration/
APP_COUNTRY=BR APP_LOCALE=pt_BR php artisan test tests/Feature/Collaboration/
```

English hides missing keys — `__('No approvals yet.')` returns the string itself — so 28 tests
once passed under `en` and failed under `pt_BR`. See **C4** in `review-and-improvements.md` for
the 29 pre-existing BR failures outside this module.

### Changes from real use

Reported by the owner while using it, in order:

- **Cost impact opened nothing** while schedule impact opened a Days field. It now opens an
  **estimated amount** (`rfis.cost_impact_amount`, signed cents like `change_orders.amount`).
  This made `rfis` a **money area** — `money => true`, figure through `<x-ui.money … rollup>`.
  Two protections now, both wanted: `rfis.view_impact` hides *that* there is a cost,
  `can_see_money` masks *what* it is. Both impact checkboxes are `<x-ui.toggle>`.
- **Distribution rows showed two blank greyed boxes** when a person with a login was chosen.
  They now show that person's name and e-mail.
- **Discipline was worse than untranslated** — the list returned literal words chosen by the
  install's country and *stored them*, so the value depended on where the app was deployed and
  no `__()` could ever fix a row saying "Architectural". Stable keys now
  (`Rfi::DISCIPLINES`), with `disciplineLabel()` for display and a **separate fixed
  `disciplineCode()`** for numbers — ARQ, EST, HID — because a number is printed and quoted and
  must not change when a label is reworded.
- **BR says SI, not RFI.** The numbering already issued `SI-001` while every label said RFI.
  31 pt_BR values now read SI / Solicitação de Informação. The drawing field is
  **"Prancha / revisão"**.
- **A `__()` printed literally on the BR PDF** — written without its `{{ }}`. Two guards now
  render every sheet in both markets and every screen in pt_BR and assert no raw `__(`, no
  `trans_choice(`, and no unresolved `collaboration.` key.
- **The aditivo and the SI were not linked** — `rfis.change_order_id` existed and nothing ever
  wrote it. "Criar aditivo" now carries `?fromRfi=`, the change-order form opens pre-filled
  (title, answer, estimated cost — the amount only if the reader holds `rfis.view_impact`), and
  **saving links them**. Never auto-created. `rfis.change_order_answer` snapshots the wording
  the aditivo was argued from, and the page says so when the answer has been corrected since.

### Translations are the module's own

`lang/en/collaboration.php` and `lang/pt_BR/collaboration.php`, ~290 keys grouped by family
(`rfi.status.*`, `approval.type.*`, `discipline.*`, `activity.*`, `response.*`, `label.*`,
`help.*`, `message.*`, `count.*`, `pdf.*`, `field.*`).

**Why:** a word that looks generic often is not. `Due` had been fixed as *"A Pagar"* by the
payment screens; here it is a deadline. `Overdue`, `Approved`, `Closed` and `Select all`
carried the gender and number of whichever screen defined them first, wrong for *uma
solicitação* and *uma aprovação*.

**Deliberately still global:** ~50 strings of universal chrome (Cancel, Save, Actions,
Attachments, History), plus six the module cannot own because `config/permissions.php` resolves
them as `__($variable)` — `RFIs`, `Approvals`, `Record a response`,
`Generate approvals from the budget`, `question`, `supplier`.

**Response codes moved too.** `label_key` is a column and `__($code->label_key)` resolves it,
so migration `2026_08_27_140000_…` re-runs the seeder to point existing rows at
`collaboration.response.*`.

**Audit the code, not the JSON.** An `en.json` ↔ `pt_BR.json` diff reported "0 missing" while
`Discipline` was absent from *both*. Extract every `__('…')` literal and resolve it.

### Replies (the largest change)

An SI is answered by whoever can answer it, and often that is more than one person. A single
`answer` column could not express that — a second reply overwrote the first and its only trace
was JSON inside an activity row.

- **`rfi_replies`** — body, who, when, `edited_by_id` / `edited_at`. Existing answers were
  backfilled as their first reply.
- **`rfis.valid_reply_id`** is the answer that counts. One pointer, not an `is_valid` flag per
  row, so "two replies both claiming to be the answer" is not representable.
- **The first reply is valid by default**; a later one **does not take over** — that is a
  decision, made with the ✓ button (`rfis.close`). When the newest reply is not the valid one,
  the panel says so at the top.
- **`rfis.answer` / `answered_by_id` / `answered_at` mirror the valid reply** — they are what
  the PDF prints, what search matches, what the freeze rule guards and what an aditivo is
  argued from.
- **Editing:** your own words while it is open (`rfis.answer`); somebody else's, or anything
  once closed, needs `rfis.revise`. A reason is **required once closed**. `revise()` now edits
  the valid reply — it used to write `answer` directly, which left the replies list and the
  printed answer disagreeing.
- **Attachments per reply** — `rfi_reply` registered in all three `FileUploadService` places;
  keys are `rfis/{project}/{rfi}/replies/{reply}/…`. The download guard accepts the SI's own
  files and its replies' and nothing else.
- The two reply actions are **icon-only** (`x-ui.icon-button`, check and pencil) with `title`
  and `sr-only` labels. Editing is **not** an amber button.
- The header holds **Responder / Nova resposta** (it adds a reply — it never edited anything),
  and there is **no edit button on the header**: editing lives on the reply.
- **History is an accordion, closed by default**, with its entry count in the header.

### Where it stands

**307 module tests, green in both markets. Full suite 959**, with the same three pre-existing
failures (`RegistrationTest` ×2 — there is no `register` route by design — and `ExampleTest`).
Nothing committed; everything is in the working tree on branch `rfi-aprovacoes`.

**Phase 8 is all that remains.**

---

## Phase 8 — Review and Improvements

Required by CLAUDE.md, planned from the start, never skipped. Items noticed mid-build go to
`docs/review-and-improvements.md` rather than derailing the feature in hand — but the backlog is
worked, not archived.

1. **Code review of the whole module**, not just the last change: correctness, the guards, the
   numbering under concurrency, N+1s, and anything keyed in by hand that the server should
   compute.
2. **Walk the real screens** in both themes, both locales and on a phone: empty states, partial
   states, error states, long names, many rows.
3. **Close the gap between what the screens say and what the code does** — wording that promises
   something the code does not enforce is a bug.
4. **Sweep the notations** collected while building and either fix them, schedule them, or record
   the decision not to.
5. **Docs and pt_BR** brought level with what was actually built, including the discovery
   document's open questions.
6. **Permission bookkeeping** — both areas `swept => true`, the full suite green.

---

## Translations live in the module's own file

**`lang/en/collaboration.php` and `lang/pt_BR/collaboration.php`**, referenced as
`__('collaboration.rfi.status.closed')`. 279 keys, grouped by family — `rfi.status.*`,
`approval.type.*`, `discipline.*`, `activity.*`, `response.*`, `label.*`, `help.*`,
`message.*`, `count.*`, `pdf.*`, `field.*`.

**Why, in one line: a word that looks generic often is not.** Two bugs came from sharing the
global file, and both were found by the owner rather than by any test:

- `Due` had been fixed as **"A Pagar"** by the payment screens, where it means something
  payable. Here it is a deadline — **"Prazo"**.
- `Overdue`, `Approved`, `Closed` and `Select all` carried the gender and number of whichever
  screen defined them first, which is wrong for *uma solicitação* and *uma aprovação*:
  "Vencido" where it should read **"Atrasadas"**, "Aprovado" where it should read
  **"Aprovadas"**.

A key in `collaboration.php` cannot be repurposed by another module, and this module cannot
inherit another module's meaning by accident.

**What deliberately stayed global** (50 strings): universal chrome — Cancel, Save, Actions,
Attachments, Clear filters — so one button does not end up with two translations. Plus six the
module cannot own because `config/permissions.php` resolves them as `__($variable)`: the area
names `RFIs` and `Approvals`, the action labels `Record a response` and
`Generate approvals from the budget`, and the validation attribute names `question` and
`supplier`. Those have exactly one definition, in the global file.

**Response codes moved too.** `label_key` is a column, and `__($code->label_key)` resolves it,
so the stored value *is* the key: migration
`2026_08_27_140000_move_response_code_labels_to_the_module_file` re-runs the seeder to point
existing rows at `collaboration.response.*`. Moving a module's strings without moving what the
database stores would have left every response rendering its raw key.

**Two traps this exposed, both worth remembering:**

- **A key-diff of `en.json` against `pt_BR.json` proves very little.** It said "0 missing" while
  `Discipline` was absent from *both* — an en→pt diff cannot see a key that was never added
  anywhere. Audit what the code asks for: extract every `__('…')` literal and resolve it.
- **English hides missing keys.** `__('No approvals yet.')` returns the string itself when the
  key is gone, so 28 tests passed under `en` and failed under `pt_BR`. Run the suite in the
  locale the customer actually reads.

---

## Conventions (non-negotiable — from CLAUDE.md)

- **Incremental migrations only.** Never modify a migration that has run; never `migrate:fresh`
  or `migrate:refresh`.
- **MySQL.**
- PSR-12.
- Feature-grouped Livewire component folders. Full-page components for main features, inline
  components for reusable UI. PascalCase classes, kebab-case views.
- Tailwind + Alpine.js. **No new packages without asking** — this module needs none.
- Livewire 3.7 — do **not** use Livewire v4 syntax or features.
- Reuse the existing `x-ui.*` components. Full-page modals for real work; small dialogs are for
  confirmations, not data entry.
- Every user-facing string through `__()`, with the pt_BR value added **in the same change**.
- One page at a time, tested, before moving to the next.

---

## Guardrails

- **Do not merge FVS/FVM into approvals.** If a requirement seems to call for it, stop and ask.
- **Do not branch on country in business logic.** Branch on `canonical` values and configuration.
  Country decides what is rendered — `@if(config('app.country') === 'BR')` in a Blade file is
  correct and matches 99 other places. `if ($country === 'br')` inside a service is not; raise it.
- **Do not build a second membership table, a second auth guard, or a separate portal.** The
  guest system shipped on 21 Aug 2026. Gate on abilities, never on `is_guest`.
- **Do not auto-create change orders, aditivos, or published approvals.** Every money-touching or
  externally-visible artifact is user-confirmed.
- **Never act on an id that came from the browser without checking which project it belongs to.**
- **Reproduce first, then make it revocable.** A change that quietly widens or narrows who can do
  something is a bug even when the new behaviour seems more sensible. Say what moved.
- **Do not refactor existing numbering** in this effort.

---

## Open questions

1. **Change order target** — RFI's `change_order_id` points at `ChangeOrder`, not
   `ContractChangeOrder`. Assumed yes; needs one line of confirmation before the Phase 3
   migration.
2. **Budget flag approach** — decision 6 takes the additive copy-forward route. Confirm before
   Phase 6.
3. **FVS / FVM** stays out of scope. Worth an explicit yes before Phase 5, since
   *laudo/certificado* approvals sit close to it.

---

## Where this sits in the queue

Behind **permissions F3** (the permission module's own review) and **meetings phase 8**.

F3 first because both new modules are built directly on the resolver, the membership templates
and `visibleTo()`; holes in that layer should be found before two modules sit on top of it.
Meetings next because it is this module's structural sibling — numbering, revisions,
freeze-after-publish, distribution lists, PDF rendering, e-mail distribution — and finishing it
settles the patterns these phases copy. See `docs/open-items.md`.

---

## Related documents

- [`rfi-aprovacoes-discovery.md`](./rfi-aprovacoes-discovery.md) — the evidence behind revision 3
- [`permissions-for-new-modules.md`](./permissions-for-new-modules.md) — the checklist every phase
  obeys
- [`pt-br-translation-audit.md`](./pt-br-translation-audit.md) — the translation rules
- [`meetings-module-guide.md`](./meetings-module-guide.md) — the structural sibling
- [`budget-costcode-system.md`](./budget-costcode-system.md) — the budget design behind Phase 6
- [`project-jobsite-parity-rule.md`](./project-jobsite-parity-rule.md) — why both levels ship
  together
- [`review-and-improvements.md`](./review-and-improvements.md) — where mid-build findings go
