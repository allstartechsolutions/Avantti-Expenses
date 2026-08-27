# RFI + Aprovações — Discovery findings

**Deliverable of Phase 0 of [`RFI-Submittals-modules.md`](./RFI-Submittals-modules.md).**
**Date:** 26 Aug 2026 — read-only pass over the codebase, no schema or code changed.
**Purpose:** answer the eleven Phase 0 questions with citations, and list every assumption in
the plan that the codebase contradicts, so the plan can be corrected before the first migration.

The plan's own instruction was *"do not guess; cite file paths and line numbers"* and *"stop and
report before proceeding if items 1, 2, 7 or 8 differ materially"*. **Items 1 and 7 differ
materially.** Both are corrected in revision 3 of the plan.

---

## Summary — what changed in the plan because of this

| # | Finding | Effect on the plan |
|---|---|---|
| A | Database is **MySQL**, not Postgres | No advisory locks. Gapless numbering uses `SELECT … FOR UPDATE` on the sequence row inside a transaction. |
| B | **Not multi-tenant.** Each customer is a separate installation, one company, one country | `LabelResolver` deleted — it reinvents `__()`. No per-project market column. Market is `config('app.country')`, a deploy-time constant. |
| C | The **external-user system already exists** — `is_guest`, `memberships`, `UserInvitation`, guest permission templates | Phase 2's `project_user` table, `is_external`, `must_change_password` and the separate `/portal` stack are all dropped. Guests use the same screens, gated by abilities. |
| D | **`BudgetItem` is the applied cost code** and carries no `cost_code_id` | A `requires_approval` flag on `cost_codes` cannot be read from a budget line. It must be copied onto `budget_items` when a template is applied, or Phase 5 seeding cannot work. |
| E | **Two attachment systems** — legacy `attachments` and the newer `file_uploads` on R2 | New modules use `file_uploads` / `FileUploadService`, the one Meetings uses. |
| F | **No shared numbering service**, and the closest thing is the anti-pattern the plan forbids | `collaboration_number_sequences` is genuinely new. Build it generic; do **not** refactor existing numbering in this effort. |
| G | The plan omits all three of CLAUDE.md's "every module ships with" rules | Permissions declared before the first screen, `__()` + pt_BR in the same change, and a final Review and Improvements phase are now explicit phases. |

---

## 1. Country / market flag — **differs materially**

`config/app.php:124`

```php
'country' => env('APP_COUNTRY', 'US'),
```

One environment variable, read in **99 places** across `app/` and `resources/views/`. There is
no tenant table; `Company` is a single record, fetched as `Company::first()`
(`app/Services/MeetingMinuteRenderer.php:74`). Country is not on the company, the project or
the job site — it is configuration, fixed at deploy time.

**Each customer runs a separate installation.** Confirmed with the owner, 26 Aug 2026.

The established pattern for country-dependent rendering is a direct config check, prescribed in
CLAUDE.md and used throughout — for example address formats
(`app/Livewire/Project/ProjectCreate.php:117`), phone formatting
(`app/Models/Concerns/HasFormattedPhone.php:23`) and date formats
(`app/Mail/MeetingMinuteMail.php:38`).

**Contradicts the plan.** Revision 2 assumed a per-tenant or per-project market, and gave the
example of *"a BR tenant serving a US owner"*. That is not possible in this architecture and
would require multi-tenancy — a far larger change than these two modules.

**Consequences:**

- **`LabelResolver` is deleted.** Its three-tier precedence (project override → market default →
  `lang/`) collapses to `__()`, which already carries 2,828 keys in `lang/en.json` and 4,319 in
  `lang/pt_BR.json`. "RFI" vs "Solicitação de Informação (SI)" is a translation, not a resolver.
  A second label path means two places to look when a word is wrong.
- `collaboration_response_codes` keeps its `market` column and seeds both sets — it is five rows
  each and it documents the canonical mapping — but the active set is chosen once from
  `config('app.country')`. `project_id` stays nullable for a future per-project override; no UI
  for it now.
- No `market` or `regional_profile` column on projects.
- **The rule that survives:** country decides what is *rendered*, never what the code *decides*.
  Showing `drawing_ref` instead of `spec_section` on a BR install is presentation. A service
  asking the country whether a revision closes is not — that branches on `canonical`.

---

## 2. Project / job site relationship — matches the plan

A project has many job sites; `JobSite` belongs to a `Project` via `project_id`. Both are
first-class permission scopes: `Membership.scopeable` is a `morphTo` reaching either
(`app/Models/Membership.php`), and both models carry `scopeVisibleTo()`
(`app/Models/Project.php:122`, `app/Models/JobSite.php:66`).

The convention for a record that can belong to either level is exactly what the plan proposes:
`project_id` required, `job_site_id` nullable. `ChangeOrder` is the reference case
(`app/Models/ChangeOrder.php:41`), as are `Expense`, `Income` and `DailyReport`.

**`docs/project-jobsite-parity-rule.md` applies:** when one level gains a UI improvement, the
other gains it too. RFI and Aprovações must ship both project-level and job-site-level screens,
not project only.

---

## 3. Change orders — matches, with one ambiguity to settle

`app/Models/ChangeOrder.php:41`

```php
'project_id', 'job_site_id', 'co_number', 'title', 'requested_date',
'status', 'approved_at', 'approved_by', 'description', 'amount',
'file_path', 'created_by',
```

`amount` is **signed cents** — a negative value is a deductive change order
(`app/Models/ChangeOrder.php:63`). See `docs/monetary-storage.md`. `ChangeOrderItem` carries the
per-cost-code breakdown added in the Aug 2026 work
(`docs/expense-changeorder-costcode-plan.md`).

**Ambiguity:** there is also a `ContractChangeOrder` model, which is the contract-side record.
The RFI's `change_order_id` should point at `ChangeOrder` (the project/job-site record), but this
needs one line of confirmation from the owner before the migration is written.

The plan's rule — surface a *"Gerar aditivo"* action on close, never auto-create — is correct and
consistent with how the rest of the app treats money-touching artifacts.

---

## 4. Cost codes and budget lines — **a trap the plan walks into**

Two models, and they are not what the plan assumes:

- `CostCode` (`app/Models/CostCode.php:11`) — `template_id`, `parent_id`, `code`, `name`,
  `description`, `sort_order`. A hierarchy belonging to a `CostCodeTemplate`. This is the
  **library**, not the project's codes.
- `BudgetItem` (`app/Models/BudgetItem.php:12`) — `budget_id`, `parent_id`, `code`, `name`,
  `description`, `budgeted_amount`, `sort_order`, `is_default`.

Per `docs/budget-costcode-system.md`, cost codes are **copied from the template into the budget**
when a template is applied. The `BudgetItem` *is* the project's applied cost code. It carries its
own `code` string and **there is no `cost_code_id` column on `budget_items`** — the link back to
the library row is by convention, not by foreign key.

**Consequence for Phase 5.** The plan puts `requires_approval` on `cost_codes` and then seeds
approvals by reading budget lines. That cannot work: from a `BudgetItem` there is no reliable way
to reach the `CostCode` it came from. Two options, to be decided before Phase 5 starts:

1. **Copy the flag forward.** Add `requires_approval` and `default_approval_type` to *both*
   `cost_codes` and `budget_items`, and copy them across when a template is applied. Consistent
   with how `code` and `name` already work. Existing budgets get the flag as `false` and can be
   marked up by hand.
2. **Add `cost_code_id` to `budget_items`** and backfill by matching `code`. Cleaner long term,
   riskier now — it touches a table that Expenses, POs, Change Orders and `CostCodeLedger` all
   read from, in a production system.

**Recommendation: option 1.** It is additive, it matches the existing copy-forward design, and
it does not touch the budget's read path. Option 2 is a separate piece of work with its own
review.

Also note **budgets now lock** (`2026_08_21_120000_add_locking_to_budgets_table.php`, with
`BudgetLockHistory`). Phase 5 seeding must read a locked budget without attempting to write to it.

---

## 5. Item catalog — matches the plan

`CatalogItem` (`app/Models/CatalogItem.php:16`) — `type`, `name`, `sku`, `description`,
`category_id`, `supplier_id`, `is_active`, `purchase_unit`, `usage_unit`, `units_per_purchase`,
`current_cost`, `billing_type`, `is_taxable`, `tax_rate_id`, `created_by`. Categories are
`CatalogCategory`; price history is tracked in `CatalogItemPriceHistory`.

Suppliers and subcontractors were unified into one `vendors` table in Aug 2026
(`docs/vendor-unification.md`) — `supplier_id` on the catalog item points there.

`requires_approval` and `default_approval_type` sit naturally on `catalog_items`. This is the
signal the plan is right to lean on: the flag compounds across projects as the company marks up
its catalogue, whereas the value threshold is a one-off guess per project.

---

## 6. Daily reports — a lock exists, no signature concept anywhere

`DailyReport` carries `locked_at` and `locked_by` (`app/Models/DailyReport.php:18`), with
`isLocked()` at line 94. `config/permissions.php:1036` documents the rule: a report closes seven
days after its date or when it is locked, and `daily-reports.edit_locked` is the grant that
overrides it.

**There is no signature mechanism in the codebase** — no signature table, no signer identity, no
payload hash. `collaboration_signatures` is genuinely new.

The closer analogue for what RFI and Aprovações need is **Meetings**, not daily reports:
`MeetingRevision` (`app/Models/Meeting.php:113`) implements "the document is frozen when
published; a later correction is a new revision, never an in-place edit", enforced through
`MeetingService::recordRevision()` (`app/Services/MeetingService.php:189`) and gated on the
`meetings.revise` ability, which `config/permissions.php:1025` flags `sensitive`. **That is the
pattern the RFI immutability rule and the approval revision cycle should copy**, including the
sensitive-ability treatment of corrections after close.

---

## 7. Users and auth — **differs materially; most of Phase 2 already exists**

One guard, one `users` table, Fortify with two-factor
(`app/Models/User.php:20`). The permission module deployed 21 Aug 2026 already contains what the
plan proposes to build.

### The external-user marker exists

`database/migrations/2026_08_20_140000_add_access_scope_to_users_table.php` — its own comment:

> `is_guest` marks somebody who is not staff — a client, an engineer, a vendor with a login for
> one project. A guest is always confined.

`User::effectiveAccessScope()` (`app/Models/User.php:130`) forces `AccessScope::ASSIGNED` for any
guest, unconditionally. A guest cannot be given company-wide access even by mistake.

### The project membership table exists

`memberships` (`2026_08_20_140004_create_memberships_table.php`, `app/Models/Membership.php`) —
`user_id`, `scopeable_type`, `scopeable_id`, `permission_template_id`, `title`, `can_see_money`,
`approval_limit`, `status`, `invited_by`, `invited_at`, `accepted_at`, `revoked_at`.

That is the plan's `project_user` with a superset of its columns, and polymorphic to project *or*
job site rather than project only.

### The invitation flow exists

`app/Services/InvitationService.php` — `invite()` at :37 (taking `is_guest` at :54), `accept()`
at :107 (sets the password, creates the membership), `resend()` at :78, `revoke()` at :92,
`landingFor()` at :208 (drops an accepted guest on their project or job-site overview),
`guestTemplates()` at :222. Backed by the `UserInvitation` model and `InvitationMail`. There is
no self-registration route.

### Guest permission templates are seeded

`database/seeders/PermissionSeeder.php:373` and `:419` seed `client-project` and
`client-job-site` — `is_guest => true`, `can_see_money => false`, abilities limited to
`project.view`, `documents.view`, `daily-reports.view`, `tasks.view`.

### Revocation is immediate and checked in the resolver

`Membership::active()` feeds `PermissionResolver::decide()`
(`app/Services/PermissionResolver.php:82`). Setting `revoked_at` removes access on the next
request — not at next login.

### Authorization is allow-list and record-invisible

`scopeVisibleTo()` narrows a confined user's query to their memberships
(`app/Models/Project.php:122`). A record outside them is not in the result set, so `findOrFail`
produces a 404 — which is the behaviour the plan asks for, already in place.

### Guards available

- `PermissionResolver::allows()` / `denies()`, `canSeeMoney()`, `approvalLimit()`,
  `withinApprovalLimit()`.
- Route middleware `ability:` — `ability:expenses.view` for a company-wide screen,
  `ability:expenses.view,project` for a scoped one (`bootstrap/app.php:31`,
  `app/Http/Middleware/EnsureUserHasAbility.php`).
- Per-person exceptions via `UserAbility` overrides (`app/Models/User.php:176`).

### Rate limiting

Fortify throttles logins to five per minute (`config/fortify.php:111`). Token-credential public
routes carry explicit throttles (`routes/web.php:156`–`174`). The plan's "rate-limit portal auth
routes" requirement is already satisfied for the login path.

**Contradicts the plan.** Phase 2 as written would build a second, competing membership system
beside the one deployed five days ago. Dropped: `project_user`, `users.is_external`,
`users.must_change_password`, the `/portal` route group, the portal layout, and the duplicate
Livewire components.

**What is genuinely still missing** (small, and folded into the RFI phases):

- `users.company_name` — the projetista's firm, needed for the PDF signature block.
- Guest views written to an activity log (see item 10).
- The plan's leak test — a guest's RFI response body must contain no cost fields.

**On the plan's real concern.** Revision 2 argued that `@if(!$user->is_external)` inside a shared
component is how cost data reaches a subcontractor after the fourth refactor. That is correct,
and it is why the answer is not a conditional on *who someone is*. `cost_impact` and
`schedule_impact_days` go behind their own ability — `rfis.view_impact` — which is default-deny,
declared in one file, and covered by the module's permission test. **Gate on the ability, never
on the person.** A conditional can be forgotten; an ability that must be explicitly granted, with
a test asserting a guest response carries none of those fields, cannot be.

---

## 8. File attachments — two systems; use the newer one

**Legacy** — `attachments` table, polymorphic `attachable`, `file_path` / `original_name` /
`uploaded_by`, deleted from the default disk on model delete (`app/Models/Attachment.php`).

**Current** — `file_uploads` with `FileUploadService` (`app/Services/FileUploadService.php`) over
`DocumentStorageService`, on the **Cloudflare R2** disk (`config/filesystems.php:56`,
`docs/deployment-cloudflare-r2.md`). It provides `begin()` / `complete()` / `abort()` for
multipart uploads, `temporaryUrl()` for signed reads, `canUploadTo()` for authorization,
`isAllowedFile()` and `maxBytes()` for validation, and `pruneStaleUploads()` for cleanup.

Meetings, Tasks, Quotations and Purchase Requisitions use the current one — `Meeting::files()`
is a `MorphMany` at `app/Models/Meeting.php:118`.

**New modules use the current one.** The plan's `HasAttachments` trait should wrap
`FileUploadService`, not the legacy `attachments` table.

**Do not forget** `FileController::authorizeFile()`
(`app/Http/Controllers/FileController.php:96`) — every new file directory is added to its `match`
so that files are not readable by anyone signed in who can guess a path. This is item 2 of
`docs/permissions-for-new-modules.md` and it is easy to miss.

---

## 9. Numbering — no shared service, and the closest example is the anti-pattern

There is no generic sequence generator. Each module rolls its own:

- `Meeting::nextNumber()` (`app/Models/Meeting.php:72`) — builds a prefix, then
  `->where('number','like',$prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number')`
  and adds one. This is **`max(number) + 1`**, precisely what the plan forbids, and it is not
  gapless if a record is ever deleted.
- `Task`, `ContractMeasurement` and `ManagesQuotations` each have their own variant.
- `ChangeOrder.co_number`, `PurchaseOrder` and the rest are hand-set or per-model.

**`collaboration_number_sequences` is genuinely new work.** Build it generic — keyed on
`(project_id, document_type)` — so POs, change orders and meetings *can* migrate onto it later,
but wire only `rfi` and `approval` in this effort. Refactoring live numbering in a production
system is a separate change with its own review; it buys no feature here.

On MySQL, gapless means: inside a transaction, `SELECT … FOR UPDATE` the sequence row, read
`current_value`, increment, write, commit. Never `max + 1` over the document table.

---

## 10. Notifications and e-mail — a clear pattern to reuse

`app/Mail/` holds nine Mailables: `EstimateMail`, `InvitationMail`, `InvoiceMail`,
`MeetingMinuteMail`, `QuotationRfqMail`, `TaskAssignedMail`, `TaskClosedMail`, `TaskOverdueMail`,
`TaskWeeklyDigestMail`.

The distribution pattern the plan wants already exists twice:
`MeetingMinuteDistributor` + `MeetingMinuteRenderer` render a document and mail it to a list, and
`TaskNotifier` handles per-event notification. `MeetingService::distribute()`
(`app/Services/MeetingService.php:99`) is the shape Phase 6 should follow.

Per-user delivery preferences live in `users.notification_preferences`
(`2026_08_20_120001_add_notification_preferences_to_users_table.php`) and `NotificationSetting`.

**Signed URLs:** Laravel signed routes are not used. Public access is by **random token** with an
explicit throttle — `DocumentShare` (`app/Models/DocumentShare.php`) generates a 48-character
token, with `expires_at`, optional `password_hash`, `max_downloads`, `download_count` and
`revoked_at`; `SharedDocumentController` and `PublicInvoicePay` serve them, throttled at
`routes/web.php:156`–`174`. Not needed for RFI, since guests have real logins, but it is the
established pattern should a no-login link ever be wanted.

**Activity logging:** there is a per-module precedent rather than a global one — `DocumentActivity`
for the file repository, `ExpenseChangeHistory`, `EstimateStatusHistory`,
`ContractStatusHistory`, `PermissionAudit`, `ModuleAccessHistory`, `BudgetLockHistory`.
`collaboration_activity_log` is consistent with that and is new.

---

## 11. Localization — fully in use, with rules already written down

`lang/en.json` (2,828 lines) and `lang/pt_BR.json` (4,319 lines), plus `lang/en/` and
`lang/pt_BR/` directories carrying `validation.php` and friends. The locale directory is
**`pt_BR`**, not `pt-BR` as the plan writes it.

The pt_BR sweep completed 24 Aug 2026 — `docs/pt-br-translation-audit.md`,
`docs/translation-system.md`. It found 773 unwrapped strings across eighteen modules and its
conclusions are now binding in CLAUDE.md. The ones that bear on this module:

- Every user-visible string through `__()`, including PDF templates, CSV exports, e-mail subjects
  and bodies, `abort()` messages and empty-value fallbacks.
- One key with placeholders per sentence — never concatenation, never a sentence split around a
  tag.
- `trans_choice()` for counted nouns, never `Str::plural()`.
- **Never print a stored enum.** RFI and approval statuses need `getStatusLabel()` on the model
  with a `static …Label(?string $value)` beside it, so filter values and history rows can be
  labelled without an instance.
- **Grammatical gender matters.** The shared status words are masculine. *Aprovação*, *revisão*
  and *solicitação* are feminine and need their own keys — `Expense::getStatusLabel()` is the
  worked example.
- Validation messages and field names come from `lang/pt_BR/validation.php`; declare a
  `validationAttributes()` **method** only where a name differs.
- Check the glossary in `pt_BR.json` before inventing a term.

**A key-diff cannot see `__($variable)`.** Area names in `config/permissions.php`, nav labels and
enum `label()` methods look translated and are not — check those sources directly.

---

## What the plan omits entirely

Revision 2 does not mention any of CLAUDE.md's three "every module ships with" rules. All three
are now explicit phases in revision 3.

### Permissions — declared before the first screen

`docs/permissions-for-new-modules.md` is the checklist, and CLAUDE.md is explicit that
retro-fitting is not allowed: *"retro-fitting permissions onto eighteen modules took a week and
found forty-odd holes that had been live in production."* Two areas are needed — `rfis` and
`approvals` — declared in `config/permissions.php` with `swept => false` until the rest is done.

The area shape, from the Meetings declaration at `config/permissions.php:1014`:

```php
'meetings' => [
    'name' => 'Meetings',
    'module' => 'meetings',
    'levels' => ['global', 'project', 'job_site'],
    'swept' => true,
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'freeze' => ['name' => 'Freeze the minutes'],
        'revise' => ['name' => 'Correct a published minute', 'sensitive' => true],
        'manage_series' => ['name' => 'Manage meeting series'],
    ],
],
```

Menu entries go in the `menu` block (`config/permissions.php:118`); project and job-site tabs go
in the `tabs` block (`:418`), each with `project_route`, `project_order`, `job_site_route` and
`job_site_order`. There is no menu markup anywhere else — an entry and its route cannot disagree.

The checklist itself (`docs/permissions-for-new-modules.md:284`) covers the traps: every action
method guarded and not only the destructive ones, records fetched by id checked against their own
scope, routes without a `mount()` carrying `ability:` middleware, **every PDF controller guarded
with the same grant as its screen**, file directories added to `FileController::authorizeFile()`,
`visibleTo()` on anything listed across projects with aggregates narrowed too, money through
`<x-ui.money>` with `rollup` on totals, and `@can` used for cosmetics but never *instead of* a
guard.

### Module registration

Every area names a key in `config/modules.php`, and if the customer has that module switched off
nothing in the area is reachable regardless of permissions. A new module needs its entry there
plus a `module_access` migration — the pattern is
`2026_08_19_180013_add_meetings_module_to_module_access.php`. Without it nothing appears in the
sidebar.

There are 26 PDF controllers in `app/Http/Controllers/`; the two this module adds must each carry
the same grant as the screen they render.

### Translation in the same change

Covered in item 11. A string added to `en.json` without its `pt_BR.json` counterpart is
unfinished work.

### A Review and Improvements phase

CLAUDE.md requires one, planned from the start and never skipped. Revision 2 ended at Phase 6
with no review. Mid-build findings go to `docs/review-and-improvements.md` rather than derailing
the feature in hand.

---

## Open questions for the owner

1. **Change order target** (item 3) — RFI links to `ChangeOrder`, not `ContractChangeOrder`?
   Assumed yes.
2. **Budget flag approach** (item 4) — copy `requires_approval` forward onto `budget_items`
   (recommended, additive), or add `cost_code_id` to `budget_items` and backfill (cleaner,
   riskier, separate work)?
3. **FVS / FVM** stays out of scope, per the plan's guardrail. Confirmed by silence; worth one
   explicit yes before Phase 4, since *laudo/certificado* approvals sit close to it.

---

## Related documents

- [`RFI-Submittals-modules.md`](./RFI-Submittals-modules.md) — the plan this informs
- [`permissions-for-new-modules.md`](./permissions-for-new-modules.md) — the checklist every phase
  of this module obeys
- [`permissions-module.md`](./permissions-module.md) — what the permission engine does
- [`pt-br-translation-audit.md`](./pt-br-translation-audit.md) — the translation rules
- [`budget-costcode-system.md`](./budget-costcode-system.md) and
  [`expense-changeorder-costcode-plan.md`](./expense-changeorder-costcode-plan.md) — the budget
  and cost-code design behind item 4
- [`meetings-module-guide.md`](./meetings-module-guide.md) — the structural sibling: numbering,
  revisions, freeze-after-publish, distribution
- [`project-jobsite-parity-rule.md`](./project-jobsite-parity-rule.md) — why both levels ship
  together
- [`review-and-improvements.md`](./review-and-improvements.md) — where mid-build findings go
