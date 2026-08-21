# Permissions module — plan

**Status: planned, nothing built.** Written 2026-08-20 from `docs/permissions-notes.md`
(notations N1–N9) and an audit of what the code enforces today.

This document is the design. It is the answer to N4 and N6, and it settles N1, N2, N3, N5,
N7 and N9 along the way.

---

## 1. What is wrong today

| | Today |
|---|---|
| Roles | Three, flat, one per user (`users.role_id`), compared by **name** in code |
| Helpers | `$user->is_admin`, `$user->is_manager`, plus four `canX()` methods on `User` |
| Guards | `EnsureUserIsAdmin` middleware (3 route groups), `AuthorizesAdmin` trait (28 calls in 14 files), and ~65 inline `is_admin` / `is_manager` reads across components, models, services and Blade |
| Policies / gates | None. `app/Policies` does not exist |
| Per-project access | **None.** Every signed-in user can open every project and every job site |
| Ownership | None. Anyone may act on anyone's draft |
| PDFs and files | Behind `auth` only — any signed-in user can fetch any document by id (N5) |
| Global search | Queries every project and job site in the install (N9) |
| `module_access` | An **install-level** switch (whole module off for the customer), not a user permission |

`projects.project_manager_id` and `job_sites.supervisor_id` exist but are **reporting
labels** — they grant nothing.

The gap the owner needs closed: *invite a person to one project (or one job site) and give
them the modules they actually work in, nothing else.*

## 2. Decisions taken (2026-08-20, owner)

1. **Scope is a per-user switch.** Every user is either **Company-wide** (sees everything,
   as today) or **Assigned only** (sees just the projects and job sites they are a member
   of). Existing users all migrate as Company-wide, so deploy day changes nothing; people
   are confined one at a time.
2. **Granularity is an action matrix.** Per area, per person: **View / Create / Edit /
   Approve / Delete** (plus a small number of area-specific actions such as *Award*,
   *Share*, *Mark paid*). One readable grid, rich enough for real jobs.
3. **Staff and external guests.** Guests (client, engineer, vendor) get a login confined to
   one project or job site — typically documents, tasks and daily reports, read-only. A
   guest never sees the sidebar's company-wide screens, any index of all projects, or the
   global search.
4. **The approval gaps N1–N3 are settled in this module**, not deferred: self-approval
   blocked, a submitted requisition locked, a quotation round requires an approved
   requisition, and *duplicate requisition* added for lesser users.

## 3. Vocabulary

| Term | Meaning |
|---|---|
| **Ability** | One thing a person may do, named `area.action` — `expenses.create`, `requisitions.approve`. The catalogue lives in `config/permissions.php`, not in the database. |
| **Area** | A module as the user experiences it — the tabs of the project/job-site nav, plus the company-wide screens. |
| **Role** | A company-wide preset of abilities on a user (`admin`, `manager`, `employee`, plus any the customer creates). Governs company-wide screens and, for Company-wide users, every project. |
| **Membership** | A person attached to **one project or one job site**, carrying its own ability list. This is what an invitation creates. |
| **Template** | A named, reusable ability list — *Site Supervisor*, *Procurement*, *Accounting*, *Client (read only)*. Copied onto a membership on invite, then editable per person. |
| **Scope** | `company` or `assigned` — the per-user switch from decision 1. |

Four layers, checked in this order, all of which must allow:

```
install module switch  →  role (company-wide)  →  membership (this project / job site)  →  record rules (ownership, status, self-approval)
```

## 4. The ability catalogue

`config/permissions.php` — a static declaration, so a deploy adds abilities without a data
migration and the database only ever stores **grants**. Each area declares which actions it
supports and at which levels it can be granted.

```php
'expenses' => [
    'name'    => 'Expenses',
    'module'  => 'projects',                       // ties to config/modules.php
    'levels'  => ['global', 'project', 'job_site'],
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'pay'       => ['name' => 'Mark as paid'],
        'edit_paid' => ['name' => 'Edit a paid expense', 'sensitive' => true],
    ],
],
```

Starting catalogue (each module's own areas are finalised in its pass, from its own doc):

### 4.1 Project and job-site areas — the nav tabs, so the grid reads like the screens

| Area | Actions beyond view/create/edit/delete |
|---|---|
| `project` | `archive` (the project or job-site record itself) |
| `expenses` | `pay`, `edit_paid` |
| `income` | `distribute` |
| `requisitions` | `submit`, `approve`, `duplicate` |
| `quotations` | `award`, `convert` |
| `purchase-orders` | `approve`, `receive` |
| `change-orders` | `approve`, `unapprove` |
| `contracts` | `measure`, `pay` |
| `documents` | `share`, `see_internal` |
| `tasks` | `close` |
| `meetings` | `freeze` |
| `daily-reports` | — |
| `budget` | `lock` |
| `reports` | `export` |
| `team` | `invite`, `manage` (who may add people to *this* project) |

### 4.2 Company-wide areas — the left menu, item by item

The sidebar, the header and everything behind them are permissioned by the **same catalogue
and the same matrix**. A person's menu is simply the list of areas they hold `view` on. The
table below is every entry that exists today, what it becomes, and what guards it now —
the third column is the measure of how much of this is currently open.

| Menu | Screen / route | Ability | Guarded today |
|---|---|---|---|
| Dashboard | `dashboard` | `dashboard.view` | **nothing** — any signed-in user |
| Company ▸ Company Info | `company.info` | `company.view`, `company.edit` | **nothing** — any user can open *and edit* the company record |
| Company ▸ Users | `users.index` | `users.view/create/edit/suspend` | `@admin` + `admin` middleware |
| Company ▸ **Roles & Access** *(new)* | `access.*` | `access.manage` | — |
| Projects ▸ All Projects | `projects.index` | `projects.view`, `projects.create` | **nothing** |
| Projects ▸ Subcontractors | `subcontractors.index` | `vendors.view/create/edit/delete/merge` | `authorizeAdmin()` on delete and merge only |
| Projects ▸ Clients | `clients.index` | `clients.view/create/edit/delete` | **nothing** |
| Projects ▸ Cost Codes | `cost-codes.templates.index` | `cost-codes.view/create/edit/delete` | `@admin` + `admin` middleware |
| Projects ▸ Payments | `payments.index` | `payments.view`, `payments.pay` | **nothing** — the payment dashboard is open to everyone |
| Projects ▸ Contract Payments | `contract-payments.index` | `contracts.pay` | **nothing** |
| Projects ▸ Payment Batches | `payment-batches.index` | `payments.batch` | **nothing** |
| Catalog ▸ All Items / Categories | `catalog.*` | `catalog.view/create/edit/delete` | **nothing** |
| Catalog ▸ Suppliers | `suppliers.index` | `vendors.*` | `authorizeAdmin()` on delete only |
| Estimates | `estimates.*` | `estimates.view/create/edit/send/delete` | **nothing** |
| Invoices | `invoices.*` | `invoices.view/create/edit/send/delete`, `invoices.record_payment` | **nothing** |
| Meetings ▸ Minutes | `meetings.index` | `meetings.view/create/freeze` | **nothing** |
| Meetings ▸ My Tasks | `tasks.mine` | `tasks.view` | own tasks only |
| Meetings ▸ Meeting Series | `meeting-series.index` | `meetings.manage_series` | inline `is_admin \|\| is_manager` |
| Reports ▸ 6 reports | `reports.*` | `reports.view` + one per report (`reports.sales_tax`, `reports.accounts_payable`, `reports.company_financials`, `reports.expenses`, `reports.payment_schedule`, `reports.payment_details`) | `@admin` + `admin` middleware |
| Header ▸ ⚙ Settings | `system-settings.index` | `settings.view/manage`, `modules.manage` | route is admin-only, **but the gear is rendered for everyone** — a non-admin clicking it gets a 403 page. Fixed here. |
| Header ▸ global search | — | no ability; filtered by visibility (N9) | **nothing** |
| Profile | `profile` | always | own record |

Six of those screens — the payment dashboard, contract payments, payment batches, estimates,
invoices and company info — are money screens with no guard at all beyond being logged in.
That is the strongest argument for doing the company-wide layer in the same build as the
project layer rather than after it.

**Two rules make the global layer work with confinement:**

1. **The same ability means "everything" or "mine" depending on the user's scope.** A
   Company-wide user with `payments.view` sees every payment in the install; an
   Assigned-only user with `payments.view` sees the payments **of their own projects and job
   sites**, through the same `visibleTo()` scope. Company-wide indexes are filtered, not
   merely switched off — otherwise confinement leaks through the Payments dashboard, the
   Accounts Payable report and the Contract Payments list.
2. **Money visibility has a company-wide twin.** `can_see_money` is per membership; the
   role-level `finance.view_amounts` does the same job on company-wide screens, so a
   coordinator can be given Estimates and Payments without the totals.

**Reference data** (clients, vendors, catalog, cost-code templates) is not project-scoped by
nature. It stays governed by the role alone — an Assigned user either holds `clients.view`
or does not. Guests never hold any of it.

### 4.3 The menu is generated from the catalogue, not hand-written

`sidebar.blade.php` is 583 lines of hand-written markup with `@admin` and
`ModuleAccess::isEnabled()` sprinkled through it, which is exactly why the settings gear
drifted out of step with its own route. Adding permission checks the same way would drift
the same way.

So the catalogue carries the navigation metadata, and one builder produces the tree:

```php
'estimates' => [
    'name'    => 'Estimates',
    'module'  => 'estimates',
    'levels'  => ['global'],
    'nav'     => ['group' => null, 'route' => 'estimates.index', 'icon' => '...', 'order' => 40],
    'actions' => ['view', 'create', 'edit', 'send', 'delete'],
],
```

`App\Services\Navigation::sidebar($user)` returns only the items whose module is enabled
**and** whose `view` ability the user holds; a group with no visible children is not
rendered at all, so nobody is shown an empty "Reports ▸" that opens onto nothing. The
project and job-site navs already build from arrays, so they take the same filter with a one
-line change. The consequence worth stating: **a new module cannot be added to the menu
without declaring its abilities**, because the menu is the catalogue.

Where the menu ends up empty for a guest, the shell changes rather than showing a bare rail:
guests land on a project picker with no sidebar and no global search.

Two switches that are not abilities, because they cut across every area:

- **`can_see_money`** on a membership — off, and every monetary column, total, budget figure
  and financial report is hidden or masked for that person on that scope. This is what makes
  a site supervisor or a guest genuinely safe to invite.
- **`approval_limit`** on a membership (nullable) — optional value ceiling on `approve`
  actions. Nullable everywhere means the feature is invisible until a customer wants it,
  and it is the honest answer to N3.

## 5. Data model

Additive only — no existing column changes meaning, nothing is dropped.

```
users
  + access_scope   enum('company','assigned')  default 'company'
  + is_guest       boolean default false          -- forces access_scope = 'assigned'

roles                       (existing) + is_system, + description already present
role_abilities              role_id, ability                       unique(role_id, ability)

permission_templates        id, name, description, level enum('global','project','job_site'),
                            is_system, created_by, timestamps
permission_template_abilities  permission_template_id, ability     unique(template, ability)

memberships                 id, user_id,
                            scopeable_type ('App\Models\Project' | 'App\Models\JobSite'),
                            scopeable_id,
                            permission_template_id  nullable,       -- what it was seeded from
                            title                   nullable,       -- "Engenheiro residente"
                            can_see_money           boolean default true,
                            approval_limit          decimal nullable,
                            status enum('invited','active','suspended'),
                            invited_by, invited_at, accepted_at, revoked_at, timestamps
                            unique(user_id, scopeable_type, scopeable_id)
                            index(scopeable_type, scopeable_id)
membership_abilities        membership_id, ability                 unique(membership_id, ability)

user_invitations            id, email, name, role_id, access_scope, is_guest,
                            token_hash, payload json,              -- memberships to create on accept
                            expires_at, invited_by, accepted_at, accepted_user_id, timestamps

permission_audits           id, actor_id, subject_user_id, scopeable_type, scopeable_id,
                            action, before json, after json, created_at
```

Abilities are **rows, not a JSON column**, so "who on this project may approve a change
order?" is one query — that report is part of the deliverable (F1).

## 6. How a check resolves

`app/Services/PermissionResolver.php`, wired into Laravel's Gate. One entry point:

```php
$user->can('expenses.approve', $project);   // or $jobSite, or null for company-wide areas
```

```
1. Module off in module_access?                        → deny (install switch wins over everything)
2. User inactive or suspended?                         → deny
3. User is admin?                                      → allow  (Gate::before)
4. Company-wide area (scope is null)?                  → role_abilities has it?
5. Scope is a job site?
     a. membership on THAT job site                    → its abilities decide  (specific beats general)
     b. else membership on its parent project          → its abilities decide  (cascade)
     c. else if user is Company-wide                   → role_abilities decide
     d. else                                           → deny
6. Scope is a project?
     a. membership on the project                      → its abilities decide
     b. else if user is Company-wide                   → role_abilities decide
     c. else (job-site-only member)                    → deny, except `project.view_header`
7. Record rules on top — ownership, status, self-approval, approval_limit  → may still deny

   ...and before all of it, the legacy bridge (§9.1) while the build is in progress:
   area not yet swept?  → Company-wide user: today's rule decides.  Assigned or guest: deny.
```

Rules of the model, chosen deliberately:

- **Specific beats general, all the way up** *(revised 2026-08-20, from the owner)*. A
  membership **replaces** the role on the scope it covers rather than adding to it: being
  given a job on a project means being that on that project. A job-site membership beats the
  project's, a project membership beats the role, and where there is no membership the role
  applies untouched. Confinement applies only to scoped areas — a confined person keeps
  every company-wide screen their role gives them.
- **A job-site membership overrides its project membership** for that site rather than
  adding to it. "Specific beats general" is the rule people expect.
- **A job-site-only member sees the project's name and breadcrumb and nothing else** — the
  bare minimum for the screens to make sense.
- **Resolution is memoised per request.** All memberships and abilities load in one query on
  first use. No cross-request cache: a revoked permission must be gone on the next click,
  and this is a production system.

## 7. Enforcement — every layer, not just the buttons

1. **Policies** — `app/Policies/{Project,JobSite,Expense,PurchaseRequisition,Quotation,
   PurchaseOrder,ChangeOrder,Contract,Document,Task,Meeting,DailyReport,Budget}Policy`, each
   a thin delegation to the resolver that derives the scope from the record.
2. **Route middleware** — `ability:expenses.view` for company-wide routes,
   `ability:expenses.view,project` for scoped ones (resolves the route parameter). Replaces
   the three `admin` middleware groups.
3. **Livewire** — a `AuthorizesAbility` trait replacing `AuthorizesAdmin`:
   `$this->authorizeAbility('expenses.create', $this->project)` in `mount()` **and at the
   top of every action method**, because a `wire:click` can always be invoked directly.
   `AuthorizesAdmin` is deleted in the same pass, not left as a shim.
4. **Blade** — `@can('expenses.create', $project)` around every button. The `@admin`
   directive (25 blocks in 13 views) is retired in the same pass; it is the same
   role-name-in-markup problem one level up.
5. **Navigation** — the sidebar, the header gear, the project nav and the job-site nav all
   render from `Navigation::sidebar($user)` / `::projectTabs($user, $project)`, so a menu
   entry and its route can never disagree again (§4.3).
6. **Query scopes** — `Project::visibleTo($user)`, `JobSite::visibleTo($user)`, applied to
   the project and job-site indexes, the dashboard and its cards, **the header search (N9)**,
   My Tasks, the meeting screens, and every company-wide index that reaches project data:
   the payment dashboard, contract payments, payment batches, estimates, invoices and all six
   reports. This is what stops confinement leaking through a company-wide list.
7. **Controllers** — all 20 PDF and file controllers authorize against the record's scope
   before rendering (**N5**), and a presigned R2 URL is only minted after the check (**N8**).
8. **Share links** — creating one becomes `documents.share`, granted by template rather than
   by role, which is the decision N7 was waiting for.

## 8. Screens

Built to the standard in `CLAUDE.md` — both themes, both locales, no horizontal scroll,
`x-ui.*` throughout, and project and job site get the same treatment (`docs/project-jobsite-parity-rule.md`).

**Project → Team tab** (and Job Site → Team tab, identical):
member cards with name, photo, title, template chip, module chips ("Expenses ✎, Budget 👁,
Documents ✎"), money visibility, status (Invited / Active / Suspended) and last activity;
row actions Edit access, Resend invite, Suspend, Remove. Empty state explains what a member
is and offers the invite. Header shows counts by template.

**Invite / Edit access — full-page modal.** Step 1: an existing user, or an email address
for someone new (guest toggle). Step 2: pick a template. Step 3: **the matrix** — areas down,
actions across, prefilled from the template, per-cell toggles, "all in this row", "all in
this column", "copy from another member"; a live summary sentence underneath ("Maria can see
and create expenses, approve nothing, and cannot see money on this job site."). Sticky footer
with Send invitation / Save. Deviating from the template flips the chip to *Custom (based on
Site Supervisor)*.

**Settings → Roles & Access:** three tabs — Roles (company-wide matrix), Templates (project
and job-site presets, with a *Duplicate* action), and Members (every membership in the
install, filterable by person, project or ability — the "who can approve change orders?"
report).

**Users index / show:** scope badge (Company-wide / Assigned / Guest), membership count, and
on the detail view an **Access** panel listing every membership with its abilities, plus the
permission audit trail for that person.

**Effective access inspector** (admin): pick a user and a project, see exactly what they can
do and *why* each ability is allowed — role, membership, or template. This is the screen that
makes the module supportable when a customer says "he can't see the budget".

## 9. How it gets built — engine first, then one module at a time

The build is split in two. **Stage 1 builds the engine** and changes nothing anyone can see.
**Stage 2 converts one module per pass**, each pass complete and tested before the next one
starts, exactly as `CLAUDE.md` rule 7 asks. **Stage 3** throws the switch and closes up.

### 9.1 The legacy bridge — what keeps the app working mid-build

The one mechanism that makes a module-at-a-time conversion safe:

```
resolver:  is this area declared in the catalogue AND swept?
             yes → abilities decide
             no  → Company-wide user  → today's rule decides (role name, as now)
                   Assigned / guest   → deny
```

So an unconverted module behaves exactly as it does today for everybody who works in it,
while an Assigned user or a guest simply cannot see it at all — no half-converted screen ever
leaks. Each module pass deletes its own branch of the bridge; **removing the last branch, and
the bridge itself, is the definition of done** (F2).

### 9.2 Stage 1 — the engine (four steps, nothing visible changes)

| # | Step | Deliverable | Done when |
|---|---|---|---|
| E1 ✅ | **Catalogue & schema** *(built 2026-08-20 — see `docs/permissions-module.md`)* | `config/permissions.php` with the area/action declarations (nav metadata included); all migrations from §5; models; the three seeded roles written out as ability rows checked line by line against today's behaviour | `php artisan migrate` on production changes **nothing** anyone can see |
| E2 ✅ | **Resolver & bridge** *(built 2026-08-20)* | `PermissionResolver`, `Gate` wiring, base policies, per-request memoisation, the legacy bridge above, the `AuthorizesAbility` trait and the `ability` middleware — all in place, **used by nothing yet** | A feature test proves every persona's answer is identical before and after, on every existing screen |
| E3 ✅ | **Navigation service** *(built 2026-08-20)* | `Navigation::sidebar()`, `::projectTabs()`, `::jobSiteTabs()` built from the catalogue; the three nav views re-rendered from them, reproducing today's menu exactly (including the header gear, which stops being shown to people who cannot open it) | Every user sees the same menu as before, minus the 403 gear |
| E4 ✅ | **Roles screen** *(built 2026-08-20)* | Settings → Roles & Access, the company-wide matrix over the catalogue, custom roles, `permission_audits` | An admin can create "Procurement"; it has no effect yet because no module is swept, and that is correct |

Data migration in E1 also turns the two reporting labels into real access:
`projects.project_manager_id` → a *Project Manager* membership, `job_sites.supervisor_id` →
a *Site Supervisor* membership. Inert until their modules are swept.

### 9.3 Stage 2 — the module pass

**Every module gets the same nine-step pass.** No module moves on until its pass is finished
and walked on screen.

1. **Declare** its areas and actions in `config/permissions.php`, with nav metadata.
2. **Policies** for its models, delegating to the resolver.
3. **Server guards** — `authorizeAbility()` at the top of `mount()` *and every action method*;
   `ability:` middleware on its routes; its `authorizeAdmin()` / `is_admin` / `is_manager`
   reads deleted, not wrapped.
4. **Buttons** — `@can` on every action in its views; its `@admin` blocks retired.
5. **Menu** — its nav entry moves under its ability; the tab disappears for people without it.
6. **Scoping** — `visibleTo()` on its lists and its share of any company-wide list; its PDFs
   and file routes authorize against the record's scope.
7. **Money** — `can_see_money` / `finance.view_amounts` honoured on its figures.
8. **Templates** — what each seeded template grants in this module, added to the seeds.
9. **Close the pass** — its own doc updated, pt_BR added, the legacy branch deleted, anything
   noticed parked in `docs/review-and-improvements.md`.

**Tested with the same five personas, every pass, both themes, both locales, on a phone:**
admin · manager · employee · **Assigned member** (holding only part of this module) · **guest**.
The question each pass answers: *can any of the five reach something they were not given, by
URL, by list, by search, by PDF link or by a `wire:click` on a hidden button?*

### 9.4 Stage 2 — the order of the modules

Access first because it is what grants everything else; then the project shell, because every
scoped module hangs off it; then modules in order of how much money they move.

| # | Module | Why here / what is special |
|---|---|---|
| M1 ✅ | **Access & Users** *(built 2026-08-20)* | Templates, memberships, the Team tab on both levels, invitations, guests, the guest shell. The module that grants all the others, so it is converted first and tested hardest |
| M2 ✅ | **Project & Job Site shell** *(built 2026-08-20)* | `project.view/edit/archive`, the overview screens, the indexes, the breadcrumb rule for job-site-only members. Every later pass depends on this scope existing |
| M3 ✅ | **Company & Settings** *(built 2026-08-20)* | Small, currently wide open (any user can edit the company record), and quick to prove the pattern on a non-scoped module |
| M4 ✅ | **Expenses** *(built 2026-08-20)* | The daily module, the most users, `pay` and `edit_paid`, the first real money masking |
| M5 ✅ | **Income** *(built 2026-08-21)* | Mirrors expenses, including the cross-job-site distribution |
| M6 ✅ | **Budget & Cost Codes** *(built 2026-08-21)* | `budget.lock`, and the templates screen that is admin-only today |
| M7 ✅ | **Requisitions** *(built 2026-08-21)* | Plus **N1/N2**: submitted requisition locked, self-approval blocked, *duplicate requisition* added |
| M8 ✅ | **Quotations** *(built 2026-08-21)* | Plus **N3**: `award`, `convert`, `approval_limit`, and the rule that a round needs an approved requisition |
| M9 ✅ | **Purchase Orders** *(built 2026-08-21)* | `approve`, `receive` |
| M10 ✅ | **Change Orders** *(built 2026-08-21)* | Approval separated from raising; un-approve and delete-approved held tighter (§4b of the notes) |
| M11 ✅ | **Contracts & Payments** *(built 2026-08-21)* | Contracts, measurements, the payments dashboard, contract payments, payment batches — four company-wide money screens with no guard today |
| M12 ✅ | **Documents** *(built 2026-08-21)* | `share` (**N7**), `see_internal`, and the presigned-URL check moved before the mint (**N5/N8**) |
| M13 | **Tasks & Meetings** | `manage_series` off the inline role check; My Tasks scoped |
| M14 | **Daily Reports** | Site-level module, the natural home of the guest and the supervisor persona |
| M15 | **Estimates & Invoices** | Client-facing money, unguarded today; includes the public pay link's boundary |
| M16 | **Reference data** | Clients, vendors, subcontractors, suppliers, catalog — role-governed, not scoped |
| M17 | **Reports** | All six, plus their PDF controllers; the heaviest `visibleTo` work |
| M18 | **Dashboard & search** | The cards obey the abilities of the modules they summarise; the header search scoped (**N9**) |

Each pass is a day-to-a-few-days of work, ships on its own, and can be deployed on its own.
If the order needs to change because a customer needs a module confined sooner, it can —
the passes do not depend on each other beyond M1 and M2.

**Progress (2026-08-21): M1–M12 are done — 18 of 30 areas enforced. M13 is next.**

### 9.5 Stage 3 — closing up

| # | Step | Deliverable | Done when |
|---|---|---|---|
| F1 | **Confinement live** | `access_scope = assigned` offered in the UI, guests enabled for real customers, the effective-access inspector, the "who can approve what" report | An Assigned user cannot reach another project's data by any URL, list, search, report or PDF |
| F2 | **Bridge removed** | The legacy bridge, `AuthorizesAdmin`, the `@admin` directive and the `admin` middleware deleted | `grep -rn "is_admin\|is_manager\|authorizeAdmin\|@admin" app resources` returns nothing but the resolver |
| F3 | **Review and improvements** | The module's own review phase per `CLAUDE.md`: full re-read, both themes, both locales, phone, long names, many members; the notations in `docs/permissions-notes.md` closed or scheduled; docs and pt_BR level with what was built | Nothing in the notes file is still `open` without a recorded decision |

Until F1, `access_scope` stays `company` for every real user and is set by hand on one test
user. That is what makes every pass in Stage 2 testable without exposing a half-built system
to anybody's staff.

## 10. What this does to N1–N9

| | Notation | Answer |
|---|---|---|
| N1 | Approval bypassable | M7 + M8 — pending requisition locked, duplicate action added, and a round requires an approved requisition |
| N2 | Self-approval | M7 — blocked; recorded in history where an install chooses to allow it |
| N3 | Award / conversion authority | M8 — abilities `quotations.award`, `quotations.convert` + `approval_limit` |
| N4 | No per-project scoping | The whole module — M1, M2, then every pass, live at F1 |
| N5 | Documents reachable by id | Each pass authorizes its own PDFs; M12 and M17 carry the bulk |
| N6 | Roles are a flat field | E1–E4 — abilities with roles as presets |
| N7 | Share links | M12 — `documents.share` ability, granted by template rather than role |
| N8 | Presigned URLs are bearer access | Unchanged by design; M12 moves the check *before* the URL is minted |
| N9 | Header search unscoped | M18 |

## 11. Still to decide (nothing here blocks the engine)

1. **Do guests get email notifications** (a document filed, a task assigned), or is the
   login enough?
2. **Approval limits** — per membership only, or also a company-wide ceiling per role?
3. **Suspending a person** — does removing a membership keep the history rows (yes) and what
   happens to their open drafts and assigned tasks (reassign prompt?).
4. **Two-person rule beyond approvals** — should deleting an approved change order require a
   second person, or is an ability enough?

## 12. Related

- `docs/permissions-notes.md` — the notations this plan answers; keep adding to it.
- `docs/module-access.md` — the install-level switch, layer 0 here.
- `docs/project-jobsite-parity-rule.md` — why every screen here is built twice.
- `docs/review-and-improvements.md` — where mid-build finds go.
