# Permissions module — as built

**Status (2026-08-20): the engine is complete (E1–E4) and three module passes are done —
M1 Access & Users, M2 Project & Job Site shell, M3 Company & Settings. Seven of thirty areas
are enforced by the new rules: `users`, `access`, `team`, `project`, `projects`, `company`,
`settings`.**

What that means in practice today:

| Works now | Does not work yet |
|---|---|
| Somebody confined sees only the projects they are on — in the list, the search, the dashboard and by URL | What is *inside* a project: expenses, budget, documents, tasks, reports all still ignore memberships |
| A project or job site they are not on answers 403 on every tab and PDF | A confined member sees only Overview and Job Sites inside a project, because the other tabs' modules are unconverted |
| Roles, templates, memberships, invitations, guests, the Users screen, Company and Settings | Payments, contract payments, batches, estimates and invoices are still open to anybody signed in |

Every screen that records a permission which is not yet enforced says so on the screen, and
those notices remove themselves as each pass lands.

The plan is `docs/permissions-module-plan.md`; this file records what is actually in the
code, and grows one section per step. Read the plan for *why*, this for *what is there*.

---

## Deploying this

Every step of this module is its own deploy. The command is always the same pair:

```bash
php artisan migrate --force
php artisan permissions:sync
```

Both are safe to run again. `permissions:sync` creates what is missing, never overwrites a
template or role somebody has edited (unless `--force`), and finishes by printing where the
build is up to:

```
Catalogue: 29 areas, 133 abilities. Swept: 0/29.
Still on the legacy bridge: dashboard, company, users, …
Grants: 188 role, 121 template, 58 membership.
```

**Deploying E1 changes nothing on screen.** No route, view, component or existing guard was
touched. The tables are filled and the catalogue is declared, but every area is still
`swept => false`, so the application keeps making the decisions it makes today.

---

## E1 — Catalogue and schema

### The catalogue: `config/permissions.php`

Every permission is `area.action`, declared in one file — 29 areas, 133 abilities. The
database stores only grants, so adding an ability is a deploy and not a data migration.

An area declares its `name`, the `module` in `config/modules.php` that must be enabled for
it, the `levels` it can be granted at (`global`, `project`, `job_site`), whether it puts
`money` on screen, its `nav` entry, its `actions`, and `swept`.

**`swept` is the legacy bridge** (`docs/permissions-module-plan.md` §9.1) and the reason
this can be deployed a module at a time. False means the area has not had its permission
pass yet: today's role checks still decide for company-wide users, and assigned users and
guests are denied outright. Each module pass flips exactly one flag. When they are all true
the bridge is deleted.

Two flags on an action are hints rather than rules:

- `sensitive` — the matrix shows a warning next to it; templates do not grant it by default.
- `limited` — the action is capped by a membership's `approval_limit`.

### Reading it: `App\Services\AbilityCatalog`

Everything that needs to know what abilities exist asks this class, so the shorthand in the
config (`'view'` versus `'pay' => ['name' => …]`) is normalised in exactly one place.
`areas()`, `abilities()`, `has()`, `label()`, `areasForLevel()`, `isGrantableAt()`,
`isSwept()`, `unsweptAreas()`, `showsMoney()`, `filter()`. It never touches the database.

`filter()` is the one to remember: every list of abilities arriving from a form, a template
or an invitation goes through it, so a typo cannot create a phantom grant.

### Schema — eight migrations, all additive

| Table | What it holds |
|---|---|
| `users.access_scope`, `users.is_guest` | Company-wide (today's behaviour, the default for every existing user) or Assigned-only; guests are always confined |
| `role_abilities` | What a company-wide role may do. `admin` holds none and needs none — it is allowed everything before this table is read |
| `permission_templates` | Named reusable ability lists, with their `level`, `is_guest`, `can_see_money` and `approval_limit` defaults |
| `permission_template_abilities` | What each template hands out |
| `memberships` | A person on one project **or** one job site (`scopeable_type`/`scopeable_id`), with `can_see_money`, `approval_limit`, status and the invitation trail |
| `membership_abilities` | What that person may do there |
| `user_invitations` | Pending invitations; only the SHA-256 of the token is stored, and `payload` carries the memberships to create on acceptance |
| `permission_audits` | Who changed whose access and when. Deliberately not foreign-keyed to memberships: removing somebody from a project must not erase the record that they were on it |

Abilities are rows rather than a JSON column so that "who may approve a change order?" is
one query — that report is part of the module.

### Models

`Membership`, `PermissionTemplate`, `UserInvitation`, `PermissionAudit`, and the three thin
ability models. Enums: `AccessScope`, `MembershipStatus`.

`Role`, `PermissionTemplate` and `Membership` all carry `abilities()` and
`syncAbilities()` with the same semantics. `User` gains `memberships()`,
`activeMemberships()`, `isConfined()` and `isCompanyWide()`; `Project` and `JobSite` gain
`memberships()`.

`Membership::accessLabel()` is what the Team tab will show: the template's name, or
*Custom (based on Site Supervisor)* once somebody has tweaked it.

### Seeding — `Database\Seeders\PermissionSeeder` / `php artisan permissions:sync`

Three jobs, all idempotent:

**1. Seven system templates.**

| Key | Level | Abilities | Money |
|---|---|---|---|
| `project-manager` | project | 58 | yes |
| `procurement` | project | 18 | yes |
| `accounting` | project | 11 | yes |
| `client-project` | project, **guest** | 4 | no |
| `site-supervisor` | job site | 19 | no |
| `site-team` | job site | 8 | no |
| `client-job-site` | job site, **guest** | 3 | no |

Created if missing, never overwritten, so a customer's edits survive a deploy.

**2. The ability lists of `manager` (98) and `employee` (90).** Seeded only while a role has
no abilities at all. `admin` gets none by design.

These reproduce **what the application enforces today**, which is what makes flipping an
area to `swept` a no-op for staff. Two exclusion lists carry the whole rule:

- `ADMIN_ONLY_ABILITIES` — what the `admin` middleware and `authorizeAdmin()` hold back
  today (the deletes, `expenses.edit_paid`, users, cost codes, reports, settings), plus the
  abilities with no counterpart in the current code (`access.*`, `team.*`, `budget.lock`,
  `payments.refund`) which start closed and are placed by their module's own pass.
- `MANAGER_ONLY_ABILITIES` — admin **or manager** today: the requisition review, the award
  and conversion, the document repository's write side, and the meeting series.

The catalogue's `sensitive` flag is deliberately **not** used for seeding. It is a hint for
the matrix, not a statement about who holds an ability today — a manager can create a share
link right now, and that had to survive the seed.

Everything else is granted to both roles, including several actions that are simply ungated
today. That looseness is listed in the seeder with the pass that will decide it —
`change-orders.delete` and `change-orders.unapprove` (M10), `contracts.delete` (M11),
`clients.delete` and `catalog.delete` (M16), `estimates.delete` and `invoices.delete` (M15),
`payments.pay` and `payments.batch` (M11).

**3. Backfill.** Every `projects.project_manager_id` becomes a *Project Manager* membership
and every `job_sites.supervisor_id` a *Site Supervisor* membership — the two reporting
labels become real access. Existing memberships are never touched.

### Tests — `tests/Feature/Permissions/`

`AbilityCatalogTest` checks the catalogue's shape: every area names a module that exists in
`config/modules.php`, every level is valid, every action normalises to a labelled ability,
every nav entry points at a route that exists, and no ability is declared twice.

`PermissionSeederTest` is the line-by-line proof that the seeded roles reproduce today's
rules — 35 cases, each named after the rule it protects ("manager shares a document",
"employee cannot approve a requisition") — plus the backfill, its idempotency, and the
`Custom (based on …)` label.

**50 tests, 1,144 assertions.**

### Side-fix: the test suite ran nowhere

The suite could not run at all before this step — eight migrations were MySQL-only and died
under sqlite, which is what `phpunit.xml` points at. Six of them widen an enum with
`MODIFY COLUMN` (meaningless on sqlite, where the column is text), seven seed `module_access`
with a non-nullable `created_by` on a database that has no users yet, and the vendor
unification reads `information_schema` and uses `UPDATE … JOIN`.

Each is now guarded by a driver or emptiness check. **No MySQL behaviour changed** — the
guards only take effect where the statement could not have run anyway. The `module_access`
guard also fixes a latent bug on a genuinely fresh install, where those migrations run
before the setup wizard has created the first user.

Three failures remain in the suite and are **not** related to this module: two in
`RegistrationTest` (the public `register` route was removed from this application) and one
in `ExampleTest` (it expects `/` to return 200, where the app redirects to the login page).
Both are stale Laravel scaffold tests.

---

## E2 — Resolver, gate and the guard surfaces

Built, wired, and **used by nothing**. Every existing screen still makes its own decisions;
this step only puts the machinery in place and proves it changed nothing.

### `App\Services\PermissionResolver`

The one place that answers "may this person do this?". The Gate, the policies, the Livewire
trait, the middleware and (next) the navigation builder all ask it; nothing else reads
`role_abilities`, `memberships` or `membership_abilities` to make a decision.

```php
$resolver->allows($user, 'expenses.approve', $project);   // or a job site, or null
```

The order of the checks:

1. **No user, or a user who is not active** → no.
2. **The module is switched off for this customer** → no, for everybody, administrators
   included. The install switch wins over every permission there is.
3. **Administrator** → yes.
4. **The area has not had its pass** → the legacy bridge: a company-wide user gets whatever
   their role gives (and the E1 seeds were written to reproduce today's code exactly), and a
   confined user or guest is denied outright. That denial is what stops a half-converted
   module leaking to somebody who is supposed to be confined.
5. **Swept** → grants only. A company-wide user is allowed if their role allows it *or* a
   membership here grants it; a confined user is answered by the membership alone.

Scope resolution: a job site is answered by its own membership if it has one, and by the
parent project's if not — specific beats general, and a project membership cascades down.
Only an `active` membership grants anything.

Alongside the yes/no:

- `canSeeMoney($user, $scope)` — the membership's `can_see_money` where there is a
  membership, the role's `finance.view_amounts` otherwise. This is the one deliberate
  subtraction in the model: a membership can take money away from somebody whose role would
  show it.
- `approvalLimit()` / `withinApprovalLimit($user, $amountInCents, $scope)` — the ceiling for
  the actions flagged `limited` in the catalogue. Null means no ceiling.

Answers, role abilities and memberships are memoised for the request; there is deliberately
**no cross-request cache**, because a revoked ability has to be gone on the next click.
`flush()` clears it.

### The four ways it is reached

| Surface | Use |
|---|---|
| **Gate** — `Gate::before` in `AppServiceProvider` | `@can('expenses.create', $project)`, `$user->can(...)`, `$this->authorize(...)`. Only catalogue abilities are intercepted; anything else falls through to policies exactly as before |
| **`App\Policies\ModulePolicy`** | The abstract base each module's policy extends during its pass. A policy declares its `$area` and, when the route to the project is longer than `project_id` / `job_site_id`, overrides `scopeFor()`. Everything else is inherited |
| **`AuthorizesAbility`** (Livewire trait) | `authorizeAbility('expenses.create', $project)` in `mount()` **and at the top of every action method** — a hidden button's `wire:click` can still be invoked directly. Also `allowsAbility()`, `allowsMoney()` and `authorizeAbilityWithin()` for the limited actions. Replaces `AuthorizesAdmin`, which is deleted at F2 |
| **`ability` middleware** | `->middleware('ability:expenses.view')` for a company-wide screen, `->middleware('ability:expenses.view,project')` for a scoped one, naming the route parameter that holds the project or job site |

`@money($project) … @endmoney` was added next to `@admin`, for the figures that
`can_see_money` hides.

### Tests

`PermissionResolverTest` — 18 cases over the rules themselves: the bridge in both directions,
a project membership cascading to its job sites, a job-site membership overriding it, a
confined user reaching nothing on a project they are not on, a membership adding to a
company-wide user's role, suspended memberships, the module switch beating an administrator,
inactive users, money masking, approval ceilings, and the Gate agreeing with the resolver.

`LegacyBehaviourTest` — **E2's proof.** It enumerates every parameterless GET screen behind
`auth` (60 of them) and opens each one as all three roles, asserting the answer recorded
before the engine existed: 180 assertions, one table.

That table is also the regression net for the eighteen module passes. A pass that
deliberately changes who may open a screen changes one line in it, in the same commit, where
it is visible in review. A pass that changes it by accident fails.

Three report routes are skipped there: `reports.payment-schedule` and its two PDFs use
MySQL's `DATE_FORMAT`, so they cannot run under sqlite. They are unaffected in production.

**Suite: 101 passing.** (The three failures are the stale scaffold tests noted below.)

### Fixed on the way

`UserFactory` did not set `status`, and a factory model is never reloaded, so a freshly
created user was inactive in memory — every permission check against one answered "no". It
now sets `status`, `access_scope` and `is_guest`.

---

## E3 — The menu is generated

The sidebar was 583 lines of hand-written markup with `@admin` and
`ModuleAccess::isEnabled()` sprinkled through it. The two nav bars were 213 more, each
carrying its own copy of the tab list. All of it is now declared once and rendered from the
declaration.

### What moved into `config/permissions.php`

| Section | Holds |
|---|---|
| `groups` | The five collapsible sidebar groups, with their icon and the route patterns that light them up. Groups and top-level items share **one ordering space**, which a test enforces |
| `menu` | 26 sidebar and header entries: label, group, order, route, **ability**, active patterns, icon. `header: true` puts an entry in the top bar instead |
| `tabs` | The 14 project / job-site tabs, each with its ability, its icon, and a route and order **per level** — the two bars order themselves differently today and both orders are kept. `job_site_route` is null for a project-only tab (Job Sites) |

One area can own several entries — Catalog owns *All Items* and *Categories* — and an entry
can name any ability of its area: *Meeting Series* is a meetings entry gated on
`meetings.manage_series`, which is what the inline `is_admin || is_manager` used to do.

Areas no longer carry navigation metadata at all. The consequence worth stating: **a module
cannot appear in the menu without declaring the ability that opens it.**

### `App\Services\Navigation`

`sidebar($user)`, `header($user)`, `projectTabs($user, $project)`, `jobSiteTabs($user, $jobSite)`.

An entry survives three conditions: its route exists, its module is switched on for this
customer, and the person holds its ability — through the resolver, so the legacy bridge
still applies and a confined user still sees nothing from an unswept area. A group whose
children are all gone is dropped rather than rendered as a heading that opens onto nothing.

### The views

| File | Before | After |
|---|---|---|
| `inc/sidebar.blade.php` | 608 lines | 127 |
| `components/project-nav.blade.php` | 109 | 34 |
| `components/jobsite-nav.blade.php` | 104 | 34 |
| `inc/nav/item.blade.php`, `inc/nav/group.blade.php` | — | the two markup patterns, once each |

The markup itself is unchanged — the rail flyouts, the Alpine state, the transitions, the
active-state classes and the icons are all exactly as they were. Only the source of the
list changed.

`inc/header.blade.php` renders `Navigation::header()`, and the Settings entry in the profile
dropdown in `layouts/app.blade.php` is behind `@can('settings.view')`.

### The one deliberate change

**The settings gear.** It was rendered for every user while its route was admin-only, so a
non-admin who clicked it got a 403 page. It is now only rendered for people who hold
`settings.view`.

Measured against the menus captured before the rewrite:

| Role | Links before | Links after | Difference |
|---|---|---|---|
| admin | 27 | 27 | none |
| manager | 19 | 18 | the gear |
| employee | 18 | 17 | the gear |

### Also in this step

**`documentation` joined the catalogue** — a module that existed in the sidebar but not in
E1's areas. Reading it is open to everybody today, writing is admin-or-manager, deleting is
admin; the seed lists say so.

**`permissions:sync` learned to fill in new areas.** Adding an area to the catalogue is
routine from here — every module pass does it — but `seedRoleAbilities()` only ever seeds a
role that has *no* abilities, so on an existing install a new area's abilities would never
reach anybody. `grantAbilitiesOfNewAreas()` now hands a role the abilities of an area it
holds **nothing** from. The moment somebody has granted or revoked anything in an area, that
area is theirs and is left alone, so a deploy can never quietly restore a permission an
administrator took away. Running it here gave the manager three documentation abilities and
the employee one.

### Tests

`NavigationTest` — 10 cases. The centrepiece is the menu each role sees, written out group
by group as it was before the rewrite; plus the gear, a switched-off module taking its
entries and its tabs with it, an empty group disappearing, a guest seeing nothing at all,
both tab bars in their own order, a site supervisor's job-site tabs once the relevant areas
are swept, and the project and job-site pages rendering their bars end to end.

`AbilityCatalogTest` gained three: every menu entry names a real ability, an existing route,
a declared group and an icon; every tab names an ability that can actually be granted at
that level; and no two menu entries claim the same order.

**Suite: 113 passing.**

---

## E4 — Roles & Access

`Settings → Company → Roles & Access` (`/access`), guarded by
`ability:access.view` — the first route in the application written against the new engine
rather than the `admin` middleware. Until somebody grants `access.view` to another role,
that means administrators only.

### The list

Every role with what it costs to hold: how many people have it, how many of the 138
abilities it grants, and chips for the areas it touches. `admin` is shown but marked
*Allowed everything* — it holds no ability rows and needs none.

Underneath, the **recent access changes** — every grant, revoke, creation and deletion from
`permission_audits`, with who did it and when. Empty state says so plainly rather than
showing a blank panel.

### The editor

A full-page modal (the matrix is a hundred-odd repeating rows with running totals — the
modal size rule in `CLAUDE.md`), opened by `dispatch('open-modal', …)` like every other
modal in the codebase.

- **Name and description.** Built-in roles cannot be renamed: their names are compared in
  code that has not had its pass yet, so the field is disabled until F2.
- **See monetary figures** — the role-level twin of a membership's `can_see_money`, on its
  own because it is not an area.
- **The matrix**, in two sections: *Company-wide screens* (the left menu and what is behind
  it) and *Projects and job sites* (granted here, they apply on every project; granting them
  on one project is the Team tab's job). Grant-all / clear per section and per area, a filter
  box, a running ":count of :total abilities granted" in the header and the footer.
- **Two legends that tell the truth about the build.** An amber dot marks a `sensitive`
  action. A grey dot marks an area that is **not enforced yet** — still on the legacy bridge
  — and a banner at the top of the screen says how many areas are enforced so far. Without
  it the screen would promise something the code does not do, which `CLAUDE.md` counts as a
  bug.
- The admin role opens read-only, explaining why there is nothing to choose.

Rules the screen enforces: a built-in role cannot be renamed or deleted, a role somebody
holds cannot be deleted, names are unique, and **nothing that is not in the catalogue can be
granted** — the whole `granted` array is filtered through `AbilityCatalog::filter()` on the
way out, because it arrives from the browser.

A saved grant takes effect on the next click: the resolver is flushed, and there is no
cross-request cache to go stale.

### Bug caught by the tests

The matrix first bound `wire:model="granted.expenses.create"`, treating the ability as a flat
array key. Livewire reads the dots as a **path**, so every checkbox was writing
`granted['expenses']['create']` while the component looked for `granted['expenses.create']`
— nothing would ever have saved. The state is now nested by design, flattened back to
`area.action` on save, and `finance.view_amounts` is a separate `$seeMoney` flag rather than
a fake area.

### Tests

`AccessScreenTest` — 17 cases: who may open it, the matrix rendering both sections with a
non-CRUD action and the *not enforced yet* marker, the filter, granting and revoking with the
audit row it writes, the money flag surviving the catalogue filter, grant-all/clear on an
area and a section, the admin role staying empty even when the browser sends grants, built-in
roles resisting rename and delete, a held role resisting delete, unique names, a grant taking
effect immediately, a view-only user being refused on save, and the screen reading in pt_BR.

The two baseline tables were updated deliberately, in this step: `access.index` joined
`LegacyBehaviourTest` (200 / 403 / 403) and *Roles & Access* joined the admin's Company group
in `NavigationTest`.

**Suite: 130 passing.**

---

## The engine is done

E1–E4 are complete. What exists now: the catalogue, the tables, the resolver with its legacy
bridge, the four ways to reach it, the generated menu, and the screen to hand abilities out.
What does **not** exist yet: any module actually enforcing it. Every one of the 30 areas is
still `swept => false`.

That is the whole point of the ordering. From here it is eighteen module passes, each one
converting a single module, each one deployable on its own, each one tested against five
personas — and only after the last of them does `access_scope = assigned` get offered to
real users.

---

## M1 — Access & Users *(complete)*

The first module pass, and the one that grants all the others. Being built in visible pieces.

### M1a — Templates *(done)*

`Roles & Access` gained tabs: **Roles** and **Templates**.

A template is a ready-made ability list for one project or one job site. Inviting somebody
copies it onto their membership, and **their access is theirs from that moment** — editing a
template later never changes what an existing member can already do. The screen says so, in
the list, in the editor's header, and in a banner when a template is in use.

The seven built-in templates ship as before: *Project Manager* (58 abilities), *Procurement*
(18), *Accounting* (11), *Client (read only)*; and at job-site level *Site Supervisor* (19),
*Site Team* (8), *Client (read only)*.

The editor is the same full-page matrix as the role editor, with three differences:

- **it only offers what can be held at that level** — a job-site template cannot hand out
  Users, Settings or Estimates, and changing the level mid-edit drops the grants that no
  longer apply rather than keeping ones the resolver would ignore;
- **an approval limit** in money, stored in cents, capping the actions flagged `limited`;
- **a guest switch** — a guest template can never see monetary figures and cannot hold a
  `sensitive` action; both are enforced on save, not just hidden.

Built-in templates can be edited and duplicated but not deleted. Deleting a custom one that
is in use is allowed and safe: the membership keeps every ability it was given and simply
starts reading as *Custom*.

Shared with the role editor: `App\Livewire\Concerns\HasAbilityMatrix` (the nested
`granted` state, `toggleArea`, `toggleSection`, the flatten-through-the-catalogue on save)
and `partials/ability-matrix.blade.php`.

`TemplateManagerTest` — 12 cases, including the level filter, the level change dropping
grants, the guest rules, duplication leaving the original untouched, an edit not reaching
existing members, and deletion leaving them with their access.

### M1b — The Team tab *(done)*

A **Team** tab on every project and every job site — the last tab on both bars, so the
daily-use ones stay where people expect them. `projects.team` and `jobsites.team`, both
behind `ability:team.view` **on that record**, which is the first time a route in this
application is guarded by something other than a role.

`App\Livewire\Concerns\ManagesTeam` holds it; `ProjectTeam` and `JobSiteTeam` are thin
components over it, the same shape as the requisition and quotation pairs.

**The list.** Each member with their photo initials, name, e-mail, title on this project,
what their access is called (*Site Supervisor*, or *Custom (based on Site Supervisor)* once
it has been tweaked), how many abilities that is, their approval ceiling, whether monetary
figures are hidden from them, their status, and who added them and when. Area chips give the
shape of it at a glance. Somebody added but given nothing says so in amber rather than
looking finished.

**The cascade, on screen.** The job-site tab has a second panel — *From the project* —
listing the people who reach that site through the project and are not overridden there.
Without it the list would look wrong to anybody who knows the project has a team. Each row
offers **Give this site its own**, which opens the editor pre-loaded with what they inherit,
filtered to what a job site can actually hold. Save it and they move out of the inherited
list and into the team, because the site membership now overrides the project's.

**The editor.** Full page. Pick a person who already has a login (a search box over the
staff not already on the list), optionally start from a template, then adjust anything.
Title, approval limit, the money switch, and the same matrix — filtered to the level, so a
job-site membership cannot be handed Users, Settings or Estimates however the browser asks.

Above the matrix, a plain sentence of what is about to be saved: *"14 abilities across
Expenses, Requisitions, Daily Reports, Documents, Tasks. Monetary figures are hidden."*

**Suspend** keeps somebody on the list with their history and grants nothing; **Remove**
deletes the membership and keeps the audit row, which says what they held when they left.
Every change writes to `permission_audits` against both the person and the record.

Two guards worth naming: every lookup is scoped to the tab's own record, so one project's
Team tab cannot reach another's membership by id; and `team.view` / `team.invite` /
`team.manage` are separate, so somebody can be allowed to see who is on a project without
being able to change it.

`TeamTabTest` — 13 cases, including the level filter dropping company-wide grants, adding
the same person twice updating rather than duplicating, the access given being exactly what
the resolver then uses, suspension taking effect immediately, the inherited panel, the
override starting from what is inherited, and the cross-project guard.

### M1b addendum — the matrix follows the level's own tabs

Raised by the owner, and right: **a project does not have the same modules as the system
does**, so a project invitation must not offer the same list.

It was half true already — `buildMatrix($level)` never offered a company-wide area on a
project — but two things were wrong:

1. The section carried the **role editor's wording** — *"these apply on every project;
   granting them to one project only is done on that project's Team tab"* — shown to
   somebody standing on that Team tab.
2. `meetings` appeared among the tabs with no tab to match it, and the rows were in
   catalogue order rather than the order of the bar the person will actually see.

Now a project or job-site editor renders **one row per tab of that level, in that level's own
tab order** — and the two orders genuinely differ, so each editor follows its own (a job
site puts Change Orders and Contracts before Requisitions; a project does not). The heading
is *What they may do on this project* / *…on this job site*, and the hint explains the
cascade at project level and nothing misleading at site level.

Anything scoped to the level but **not** a tab there — the minutes covering a project — is
kept in a second section, *Related screens*, that says what it is, rather than sitting
unexplained among the tabs.

Four tests pin it: the exact project row list in tab order, the job site's own different
order, no company-wide area reachable from either (checked against all fifteen of them), the
*Related* section holding meetings, and the role editor's wording never appearing here.

### The honesty notice on the Team tab

Also from the owner's question — *is any of the security in place?* — the Team tab was
letting somebody configure access that is not switched on, and said nothing about it. The
roles screen had its banner; this one did not. That is the same bug the banner exists to
prevent, so both Team tabs now open with **"This team list does not restrict anybody yet."**,
the count of converted modules and a link to see which. The summary sentence in the member
editor ends with *"Not enforced yet: Expenses, Requisitions, Daily Reports…"*, naming the
areas. Both disappear on their own as areas are swept.

`SecurityStateTest` was added at the same time: ten assertions recording exactly what is and
is not enforced today, including the uncomfortable ones — *any signed-in person can still
open any project and its money*, *the unguarded money screens are still unguarded*, *a
membership grants nothing in the application yet*. **Every module pass moves cases from the
second group to the first**, so "is it secure yet" is always one test run away.

### M1c — "Only the projects they are added to", on the role

Asked for by the owner: the per-user switch existed but setting it one person at a time is
no way to run a company. **The role now carries the answer and users inherit it.**

`roles.access_scope` is `company` or `assigned`; `users.access_scope` became **nullable**,
and null — the normal case, and what every existing user was migrated to — means *follow the
role*. A guest is confined whatever either says, and the admin role can never be set to
`assigned` (it is allowed everything before memberships are consulted, so it would be a
lie). `User::effectiveAccessScope()` resolves it.

The role editor opens with the choice, first thing under the name, because it is the most
consequential setting on the screen:

> **Which projects and job sites can they see?**
> **Every project and job site** — how the system works today.
> **Only the ones they are added to** — they see a project only after somebody adds them to
> its team, or to one of its job sites. Everything else is as if it did not exist.

Choosing the second shows how many people hold that role and follow it — *"2 people hold
this role and follow it. Add them to the projects they work on, or they will see nothing."*
— and says that anybody with their own setting keeps it. The roles list carries the badge.

### The bug the owner's testing found

While the owner was trying the new screens, they revoked every Expenses, Estimates,
Invoices, Payments, Clients and Company ability from the `employee` role. A later
`permissions:sync` **handed 24 of them straight back**.

`grantAbilitiesOfNewAreas()` decided an area was new by asking whether the role held
anything from it. That cannot tell a genuinely new area from one somebody deliberately
emptied — so the conservative rule the command was built on ("a deploy can never quietly
restore a permission an administrator took away") was exactly what it broke.

Fixed properly: `roles.seeded_areas` records what a role has actually been offered, and an
area is offered **once**. Existing roles were backfilled with the whole catalogue, so
nothing already on offer can ever be re-granted; only a genuinely new area reaches anybody.
Two tests pin both halves — a revoked area stays revoked, a new area is handed over exactly
once — and the owner's role was restored to the 61 abilities they had left it at, with an
audit row recording the correction.

Two smaller things fell out of the same afternoon: `users.access_scope` and `users.is_guest`
were never added to `$fillable` (an E1 edit that silently missed, hidden because factories
bypass mass-assignment protection), and `UserFactory` was pinning `access_scope` to
`company`, which would have made every future test blind to role inheritance.

### M1d — Invitations and guests *(done)*

Two buttons joined **Add Member** on both Team tabs: **Invite by e-mail** and **Invite a
client**. The same modal does all three — the abilities are chosen the same way whether the
person already has a login or not.

**Nothing is written to `users` until somebody accepts.** An invitation that is never taken
up leaves no account behind, and no half-created person cluttering the user list.

`user_invitations` carries the role, the guest flag and the memberships to create.
**Only the SHA-256 of the token is stored** — the plain token exists once, in the e-mail —
and the abilities are **re-checked against the catalogue at acceptance**, not trusted from a
payload that may have been written weeks earlier.

**The pending panel.** Invitations sent for this project or job site, with when they were
sent, how many times, when they expire and who sent them, plus *Send again* (a fresh token,
so the old link dies) and *Withdraw* (the link stops working immediately). It says plainly
that nobody there can sign in or hold anything yet.

**The accept page** (`/invitations/{token}`, public and throttled) asks for a name and a
password, creates the account and its memberships, signs the person in and lands them on the
project they were given. A link that cannot be used says **which** of the four things went
wrong — already used, expired, withdrawn, or you already have an account — and what to do
about it, rather than a blank "invalid link".

**Guests.** Ticking *This is an outsider* switches the template list to the guest presets,
forces money off and strips any sensitive ability on save — enforced in the service, not
just hidden in the form. A guest gets **no company-wide role at all** (`role_id` null), is
always confined, and signs in to a stripped shell: no sidebar, no global search, nothing
company-wide. Their top bar carries the company, a switcher when they have more than one
place, and their own account. Staff invited from a Team tab get the `employee` role and are
confined to what they were given — widening somebody is a deliberate act on the Users
screen, never a side effect of being invited.

`InvitationTest` — 14 cases: no user written until acceptance, one e-mail, the address
normalised, the token stored only as a hash, the payload carrying exactly the abilities
that are holdable at that level, a guest invitation that cannot carry money or a sensitive
action however the form is driven, an existing login refused, acceptance creating the
account and memberships, a used link refused twice, expiry and withdrawal each reporting
themselves, a resend killing the previous link, abilities re-checked at acceptance, and a
guest landing on their project with an empty sidebar and 403s on the company-wide screens.

### Model correction — the membership replaces the role on its own scope

From the owner, after trying "Only the ones they are added to" on the `employee` role. Three
things were wrong, and the first was the model itself.

**1. A membership now REPLACES the role on the scope it covers.** It used to add to it —
"grants only", the rule §6 of the plan was written around. That is not what people mean by
giving somebody a job on a project: being made a Site Supervisor on one site means being a
site supervisor *there*, not a site supervisor plus whatever the role happened to give.

The rule is now the same one that already governed job site versus project: **specific beats
general**, all the way up. A job-site membership beats the project's, a project membership
beats the role, and everywhere the person has no membership their role applies untouched.

**2. Confinement no longer empties the left menu.** Choosing "only the projects they are
added to" was denying *everything* to a confined person, including the Dashboard, the
Catalog and Documentation — screens that have no project at all. Confinement is about which
**projects** somebody can reach; company-wide screens stay the role's business. A guest is
the exception and holds nothing company-wide whatever role they carry (invitations give them
none, so this is the second lock rather than the first).

Asked **without a record in hand** — "may they do this anywhere?", which is what a menu entry
or an index needs to know — a confined person is answered from all of their memberships at
once. Asked **about a specific project**, they are answered by that project alone.

**3. Scopes are no longer named in the resolver.** `App\Contracts\PermissionScope` —
`parentScope()`, `scopeLevel()`, `scopeLabel()` — is implemented by `Project` and `JobSite`,
and the resolver walks the tree instead of asking "is this a JobSite?". Another kind of
scope, when one earns its own membership list, implements the interface and declares its
level in `config/permissions.php`; the resolver, the matrix and the Team tab need no changes.

**What is still not true, and now says so.** The project and job-site *lists* are not scoped
yet — that is M2, the next pass — so a confined person still sees every project in the index.
The scope chooser in the role editor now carries that plainly ("recorded but not enforced
yet… it takes effect the moment they are"), and the member editor states the override rule
where somebody will actually read it: *"On this project this replaces what their role gives
them. Everywhere else they are unchanged."*

Five tests cover the new rules, including the two complaints as named cases —
`test_confinement_does_not_touch_the_company_wide_screens` and
`test_a_membership_replaces_the_role_on_the_project_it_covers`.

### M1e — The Users screen, and the first sweep *(done)*

**The Users screens moved off the `admin` middleware and onto abilities.** `users.view`,
`users.create`, `users.edit` and `users.suspend` guard the routes and the component actions.
The answer is deliberately identical to before — an administrator holds all four and nobody
else does — but they can now be **granted**, which a middleware could never do: an office
manager can be given `users.view` and `users.edit` without `users.create` or `users.suspend`.

Suspending somebody is held apart from editing them, because it is what stops a person
working rather than a change of details.

**The per-person scope override finally has a UI.** On the edit form, under Role & Status:

> **Which projects and job sites can they see?**
> *Follow their role (Every project and job site)* · *Every project and job site* · *Only the
> ones they are added to*

Following the role is the normal case and what everybody was migrated to; the other two are a
deliberate override on one person. A guest cannot be widened here and the field says so.
Every change writes a `scope-changed` audit row naming who did it.

**An Access panel on the user's detail page.** The screen could say somebody was confined but
never what they were confined *to* — which is the question anybody asks next. It now shows
their effective scope and where it comes from (the role, or set on them), every project and
job site they have been added to with what their access is called there, whether money is
hidden, their title, and the access history for that person. A confined person on nothing at
all is called out plainly.

**Wording aligned.** The same setting had three phrasings — *Assigned only*, *Only projects
they are added to*, *Only the ones they are added to*. `AccessScope::label()` is now the one
source and every badge, dropdown and radio reads from it.

**`users`, `access` and `team` are the first areas swept.** Three of thirty. The two
baseline tests that asserted "no module has been converted yet" were updated to name exactly
which are, in the same commit — `LegacyBehaviourTest::CONVERTED` and the matching case in
`SecurityStateTest`. Every later pass edits those two lines.

`UsersScreenTest` — 12 cases: the answer matching the old middleware, each action grantable
separately, following the role by default, confining one person without touching their role,
widening one person when the role is confined, going back to following the role, a guest who
cannot be widened, the audit row, the change taking effect on the next check, and the access
panel showing what somebody is confined to — including its warning that the scope is
recorded but not enforced yet.

The screen's own strings were brought level in pt_BR at the same time; it had never been
translated.

### Still to come in M1

Nothing — M1 is complete. **M2, the project and job-site shell, is next**, and it is the
pass that makes "only the ones they are added to" true: `visibleTo()` on the project and
job-site lists, `ability:project.view` on the project routes, the header search scoped, and
`project` / `projects` swept.

---

## M2 — The project and job-site shell *(done)*

**The pass that makes "only the ones they are added to" true.** It decides which projects
somebody sees and which they may open — not what they may do inside one, which is each
module's own pass.

### One middleware, forty-eight routes

`EnsureScopeIsVisible` runs on every route carrying a `project` or a `jobSite`, rather than
being repeated on each of the forty-eight of them. It asks one question — *may this person
open this project at all?* — so a project route written next year is guarded the moment it
exists, including the report PDFs, which is a slice of N5 closed early.

It stands down entirely while the `project` area is unswept, so it can never be the second
answer to a question the old rules are still deciding.

### The lists

`Project::visibleTo($user)` and `JobSite::visibleTo($user)`, applied to the projects index,
the header search (**N9**) and the dashboard counts. Company-wide people see everything, as
they always did. A confined person sees a project when they hold a membership on it **or on
any of its job sites** — being put on one site is being told about that project, and its
breadcrumbs would be nonsense otherwise — and every job site of a project they are on.

### One rule added to the resolver

**Being on a project is being able to open it.** A membership grants `project.view`
implicitly, so a membership that forgot it — or one backfilled from `project_manager_id`
before the area existed — cannot lock somebody out of the very project they were added to,
and every tab of it with them.

### Also in this pass

`ProjectPolicy` and `JobSitePolicy`; `ability:projects.view` on the project list and
`ability:projects.create` on the create screen (the list was reachable by URL even when the
menu entry was hidden — a guest could open it); `ability:project.edit,project` on the edit
screen.

**`project` and `projects` are swept. Five of thirty areas converted.**

### Tests

`ProjectScopeTest` — 14 cases: the list showing one project to a member and everything to
everybody else, a job-site membership pulling its project into view, a project membership
pulling in all its sites, a confined person on nothing seeing nothing, a suspended
membership taking the project away, the URL of another project refused, **every tab of it
refused**, its report PDF refused, a membership without `project.view` still opening its own
project, the header search unable to enumerate, and a guest reaching their project and
nothing else.

One of them is there to record what this pass does **not** do:
`test_this_pass_does_not_decide_what_happens_inside_a_project` — a member holding nothing but
`project.view` still reaches the expenses and budget tabs of the project they are on. M4, M6,
M12 and the rest close those, one at a time.

The two baseline tables were updated in the same commit, and the *"recorded but not enforced
yet"* warning on the Users screen removed itself — the test for it now asserts its absence.

---

## M3 — Company & Settings *(done)*

Small, and it closes the widest-open hole in the application: **until now every signed-in
person could open *and save* the company record** — name, address, logo, tax details.

**Company** now runs on `company.view` and `company.edit`, held apart on purpose. Somebody
with only `view` gets the screen with no save button and a line saying why — and the action
behind the button refuses them, which is the half that matters.

**System Settings** moved off the `admin` middleware onto `settings.view`, `settings.edit`
and `settings.manage_modules`. The answers are identical to before — an administrator holds
all three, nobody else does — but they can now be granted, which a middleware could never
do: a bookkeeper can be given the tax rates without being made an administrator.

`settings.manage_modules` is separate from `settings.edit` because switching a module off
takes it away from the whole company, which is not the same kind of act as changing a tax
rate.

**Three hard-coded `is_admin` checks were deleted** from `NotificationSettings` rather than
left sitting behind the new guards — the pass says convert, not wrap.

Cost-code templates stay on the `admin` middleware; they belong to M6.

**`company` and `settings` are swept. Seven of thirty areas converted.**

`CompanyAndSettingsTest` — 10 cases: the company screen answering as it did, view and edit
as separate grants (including the missing button *and* the refused action), somebody with no
company ability shut out entirely, the logo needing edit, settings matching the old
middleware exactly, settings granted to a non-administrator, module switching held apart
from editing, a reader unable to change a tax rate or a notification, and the menu following
the grants — no settings ability, no gear.

### M2/M3 correction — a button nobody can use is not shown

From the owner: the *Add Project* button was still on the screen for somebody who could not
add one. The guard worked — they got a 403 — but being offered something and then refused is
worse than not being offered it.

Step 4 of the module pass says `@can` on every action in the module's views, and M2 had done
it for the tabs and not for the buttons. Now gated:

| Control | Ability |
|---|---|
| *Add Project* (header and empty state) | `projects.create` |
| *Edit Project* (project header — the shared layout, so every project page), the overview's Quick Actions, and the row action on the list | `project.edit` on that project |
| *Delete* on a project | `projects.delete` |
| *Add Job Site*, and the Edit and Delete on each job-site row | `project.edit` on that project |

The actions behind them are guarded too — `openJobSiteForm`, `editJobSite`, `saveJobSite`,
`confirmDeleteJobSite`, `deleteJobSite`, `confirmDeleteProject` — because hiding a button is
not protection: the `wire:click` behind it can still be invoked directly. Four tests cover
both halves, including calling the hidden actions straight.

Two `@admin` blocks on the project screen became `@can('projects.delete')`; the ones left on
the job-site screen belong to expenses and go in M4.

**Found on the way:** `lang/en.json` mapped **"Edit Project" → "Edit Job Site"**, so that
button has been showing the wrong label on every project page. Corrected; pt_BR was already
right.

**Then the same report again, and worse.** The Delete button in the project and job-site
headers was still there for an employee — and behind it, `confirmDeleteProject`,
`deleteProject`, `confirmDeleteJobSite` and `deleteJobSite` on the two overview components
had **no guard of any kind**, not even the old admin check. An employee could genuinely
delete a project or a job site, and its expenses, budgets and daily reports with it.

All four now require `projects.delete`, the destructive grant the seeds keep to
administrators, and the buttons are hidden without it. The job-site row on the Job Sites tab
was split accordingly: Edit needs `project.edit`, Delete needs `projects.delete`.

This **tightens** what the application did rather than reproducing it — deliberately. An
unguarded delete of a whole project is not behaviour worth preserving for the sake of the
rule, and the grant is one tick away for anybody who wants it back.

Three more tests: the button hidden and both actions refused for somebody with
`project.edit` but not `projects.delete`, the same on a job site, and an administrator still
having both.

---

## M4 — Expenses *(built 2026-08-20)*

The first pass that changes what somebody sees **inside** a project, the first
with `pay` and `edit_paid`, and the first with money masking. Eight of thirty
areas converted.

### What the six grants mean

| Ability | What it opens |
|---|---|
| `expenses.view` | The Expenses tab on a project and on a job site, the detail modal, and the receipt behind it |
| `expenses.create` | *Add Expense* on both levels, and the job-site modal's create mode |
| `expenses.edit` | The Edit screen, the job-site modal's edit mode, and changing an installment's due date |
| `expenses.pay` | *Mark Paid* and *Overdue*, on the expense and on each installment |
| `expenses.edit_paid` | Correcting an expense whose money has moved, reverting a paid expense or installment, and reading the change history |
| `expenses.delete` | Deleting an expense and its items, installments and receipt |

`pay` is separate from `edit` because settling money is not correcting a typo,
and `edit_paid` is separate from `edit` because the first is a correction and
the second is an amendment to a closed record. Both are ordinary toggles in
Roles & Access — nothing about them is hard-coded.

### The engine grew one thing

Until M4 every ability was asked about a project or a job site. Expenses are
asked about **a record**: `@can('expenses.pay', $expense)`. So
`PermissionResolver::scopeOf()` now walks from any model to the scope that
governs it — `job_site_id`, then `project_id`, then a model's own
`permissionScope()` where it has neither (an `ExpensePayment` points at its
expense and the walk continues). `ModulePolicy` delegates to it, so there is one
implementation and every later pass gets record-level `@can` for free.

### Money — roll-ups are hidden, records are not

`can_see_money` hides what a project or a job site **adds up to**, not what an
individual expense cost. A Site Supervisor who filed a receipt for R$ 1.250
knows it was R$ 1.250; hiding it from them would be theatre. What is genuinely
theirs to not see is the project's total spend, and that is what the flag now
takes away.

- **Hidden** — the three summary cards (Total / Paid / Pending), rendered as
  `——` with *"Totals are hidden for your access on this project."*
- **Shown** — each expense's amount, its line items, its installment schedule.
- **Untouched** — the create and edit forms, so Site Supervisor and Site Team
  can do the one job they exist for.

`<x-ui.money :amount :scope rollup />` is the single place that decides, so M6,
M11 and M17 mark a figure `rollup` and inherit the rule.

**Stated plainly because it decides who the flag is worth setting on:** somebody
who can see every row can add the rows up. The masking is only *real* where
their list is also narrow — a job-site member totals one site and not the
project. On a **project**-level membership with `expenses.view`, clearing
`can_see_money` buys almost nothing.

### Holes closed on the way

Four of these were not permission gaps in the plan; they were found by reading
the module and are fixed here because the pass is what made them visible.

1. **Any expense by id, from any project.** `Expense::findOrFail($id)` on the
   project and job-site screens accepted the id of *any* expense in the
   database — delete, mark paid, revert, open. Every lookup is now narrowed to
   the screen's own project or job site first, so a foreign id is a 404 before
   any permission is even asked.
2. **Job sites of other projects.** The Location picker validated
   `exists:job_sites,id` with no project clause, so an expense could be filed
   against another project's job site. Now `Rule::exists(...)->where('project_id', …)`.
3. **Receipts were readable by anyone signed in.** `files.show` and
   `files.download` took a path in the query string and served it. The
   `expenses/` directory is now resolved back to its owning expense and
   answered with `expenses.view`; a receipt no record claims is a 404. The
   other directories keep the old rule until their own pass.
4. **`deleteJobSite` on `JobSiteShow` had no guard of any kind.** M2 closed the
   identical pair on `JobSiteOverview` and missed this one, which is live on six
   routes — so any signed-in user could delete a job site and its expenses,
   change orders, budgets and daily reports. Now `projects.delete`, matching
   `JobSiteOverview`, with the button hidden to match.

### Screens converted

`ProjectExpenses`, `JobSiteShow` (expenses tab), `ExpenseCreate`, `ExpenseEdit`,
`ProjectShow` (the legacy `projects/{project}/show` alias, which carries a full
second copy of the expense CRUD), `ManagesExpenseForm`, and the
`expense-modal` / `expense-history` partials.

`JobSiteShow` and `ProjectShow` each serve several tabs from one component, so
the guard is on the **tab** and not the route — `setActiveTab` is callable
straight from the browser, and switching tab is not a fresh request. Only the
swept modules answer there; the rest keep their old rules.

`Expense::isEditableBy()` no longer reads `is_admin`; it asks for
`expenses.edit_paid`.

### Templates

Already correct from E1 and verified by a test that will fail if they drift:

| Template | Expense grants | Money |
|---|---|---|
| Project Manager | view, create, edit, pay | yes |
| Accounting | view, pay | yes |
| Site Supervisor | view, create, edit | no |
| Site Team | view, create | no |
| Procurement | — | yes |
| Client (read only) | — | no |

No template hands out `delete` or `edit_paid`; both stay with the administrator
role and are one tick away for anyone who should have them.

`ExpensesTest` — 29 cases: the three company-wide roles answering as before; the
tab disappearing without the grant; a job-site member reaching their site and
neither the project screen nor a sibling site; a guest holding nothing; every
button hidden *and* its action refused, one grant at a time; the Location picker
offering only sites the person may write to, and refusing a sibling; a foreign
project's expense unreachable by id; the tab guard against `setActiveTab`;
receipts served only to somebody who may see the expense; roll-ups masked while
the expense's own amount stays; the legacy `projects/{project}/show` screen held
to the same grants; and the six templates' grants pinned.

**`expenses` is swept. Eight of thirty areas converted. M5 — Income — is next,
and mirrors this pass almost line for line.**

---

## M5 — Income *(built 2026-08-21)*

The mirror of M4 on both levels, plus the one grant expenses has no equivalent
of. Nine of thirty areas converted.

### The five grants

| Ability | What it opens |
|---|---|
| `income.view` | The Income tab on a project and on a job site, the detail modal, its attachments, and a job site's window onto its share of a project-level payment |
| `income.create` | *Add Income* on both levels |
| `income.edit` | The pencil on a row, and **Mark as received** — booking expected money as cash is a correction to the record, not a separate act |
| `income.distribute` | The *Split across locations* mode: the grid, its bulk helpers, and any save that carries shares |
| `income.delete` | Deleting an income record |

**Why `distribute` is held apart from `create`.** Recording that money arrived
and deciding which job site's report it lands on are different acts. A split is
project-level money by definition — the shares are what say where it went — so
somebody can be trusted to enter a payment without being trusted to allocate it
across sites.

The guard is on **six** methods, not just the save: `updatedIncomeLocationMode`,
`splitEvenly`, `assignRemainder`, `clearAllShares`, `toggleAllSites` and
`updatedDistributionRows` are all callable from the browser, and the split radio
is not rendered without the grant. The save asks again, so setting
`income_location_mode` directly on the component still refuses.

One wrinkle worth recording: leaving split mode used to call `clearAllShares()`,
which is now guarded — so somebody who was never allowed into split mode would
have been refused on the way *out* of it. The clearing work moved to an
unguarded `resetShares()`, and `clearAllShares()` is the guard plus that.

### Money, attachments, scoping

The M4 rule applies unchanged: `can_see_money` hides the four roll-up cards
(Total Received / This Month / Expected / Overdue) and leaves every record's own
amount, its distribution rows and its share figures alone.

Income files are **polymorphic attachments** rather than a column, so
`FileController::authorizeFile()` grew a second shape: `income/` resolves the
path to its `Attachment`, checks the attachable is an `Income`, and asks
`income.view` on that record. The directory map is now a `match` so the third
module's pass is one line either way.

The lookups were already sound — `ProjectIncome` and `JobSiteIncome` both went
through `$this->project->income()` / `$this->jobSite->income()` before this
pass, so there was no cross-project id hole to close as there was in M4. The
tests pin that anyway.

### Templates

| Template | Income grants |
|---|---|
| Project Manager | view, create, edit, distribute |
| Accounting | view |
| Procurement, Client, Site Supervisor, Site Team | — |

No template hands out `income.delete`.

`IncomeTest` — 20 cases: the three company-wide roles unchanged; the tab
disappearing without the grant; a job-site member held to their own site; a
guest holding nothing; each button hidden *and* its action refused, one grant at
a time; **Mark as received** refused without `income.edit` and working with it;
the split option not offered, its six methods refused, and a direct
`income_location_mode = split` still refused at save; the distribute grant
producing a split that adds up; income of another project unreachable by id
through four different actions; attachments served only to somebody who may see
the record, and an unclaimed file 404ing; roll-ups masked while amounts stay;
and the templates pinned.

**`income` is swept. Nine of thirty areas converted. M6 — Budget & Cost Codes —
is next.**

---

## M6 — Budget & Cost Codes *(built 2026-08-21)*

The first pass that **builds** something rather than only guarding it: budget
locking existed as a toggle in the matrix and as nothing at all in the code.
Eleven of thirty areas converted.

### Two halves, deliberately not the same shape

**Budgets** live on a project or a job site, so every one of their five grants
can be given four ways: company-wide on a role, on a permission template, on one
project or job site, or to one person on one project. Nothing is wired to
`is_admin`.

**Cost code templates** are the company-wide library a budget is built from.
They belong to no project — one chart of accounts, used everywhere — so they are
held by role, appear in the role editor's *Company-wide screens* section, and
appear in **neither** project editor. A project membership granting every budget
ability still cannot reach them, and a test says so.

| Ability | Level | What it opens |
|---|---|---|
| `budget.view` | project / job site | The Budget tab, a budget, its cost grid, a cost code's detail |
| `budget.create` | project / job site | Creating a budget; adding cost codes and child codes |
| `budget.edit` | project / job site | The budget's own record, editing a cost code, the default-code star, importing a template |
| `budget.delete` | project / job site | Deleting a cost code, and deleting the budget |
| `budget.lock` | project / job site | Freezing the plan, and reopening it |
| `cost-codes.*` | **global** | The template library: view / create / edit / delete |

### Budget locking — what was built

A locked budget's **plan** stops moving; everything that **reports** against it
carries on. That distinction is the whole feature: freezing a baseline is not
closing the job.

- **Frozen** — adding, editing and deleting cost codes, the default-code star,
  importing a template over the codes, editing the budget's own record, and
  deleting the budget.
- **Untouched** — expenses, purchase orders, contracts and change orders still
  code to it, and every figure on the screen keeps updating. The screen says so
  in as many words, because a locked budget whose numbers still move would
  otherwise look broken.

Two migrations, both additive: `budgets.locked_at` / `locked_by` for the state,
and `budget_lock_histories` for what happened. The state alone was not enough —
a budget frozen, reopened and frozen again would leave no trace of the middle,
and a baseline that can be reopened is only worth having if reopening is on the
record. Each line keeps the action, the person, the moment and an optional
reason, and the screen shows them.

Locking goes through `Budget::lock()` / `unlock()` and the two columns are
deliberately **not fillable**, so it can never happen without a history line
beside it. Both are no-ops when the budget is already in that state.

**The refusal is about the record, not the person.** `refuseIfLocked()` runs
after the ability check on every write, so holding `budget.edit` does not make a
locked budget editable — and neither does being an administrator. Whoever may
unlock it has to do that first, deliberately, so reopening a baseline is a
visible act rather than a side effect of typing.

### Holes closed on the way

1. **The six budget screens had no guard of any kind.** Not an admin check, not
   a role check — any signed-in user could create, edit and delete a budget.
   `BudgetEdit::deleteBudget()` in particular, with `budget_items.budget_id`
   cascading, wiped every cost code that expenses, POs and change orders are
   coded against. All six now run on abilities.
2. **`BudgetShow::openEditForm` and `deleteItem` used `BudgetItem::findOrFail()`
   unscoped** — the same class of hole as M4's expenses. Every lookup goes
   through `itemInScope()` now, so a cost code id from another budget is a 404.
3. **The cost-code template routes came off the `admin` middleware** onto
   `ability:cost-codes.*`. The answer is identical for the three seeded roles —
   the seeds keep `cost-codes.*` to administrators — but it is a grant now, and
   the chart of accounts can be handed to whoever keeps it.

### Templates

No permission template grants `budget.lock`, `budget.delete` or any
`cost-codes.*`; the seeds keep all three with the administrator role. Every one
of them is one tick away on a role, a template, a project or a person.

`BudgetTest` — 21 cases, including four that exist only to prove the owner's
rule: `budget.lock` granted on a **role**, on **one project only**, on **one job
site**, and through a **permission template**, each working and each not
reaching anything it was not given. Plus: a locked budget refusing all four plan
writes *to an administrator*; the budget's own record and its deletion frozen
with it; figures still rendering under the lock banner; unlocking reopening the
plan; three lock changes kept in order with the right people; locking twice
being a no-op; add / edit / delete as separate grants; a cost code of another
budget unreachable through three actions; and the template library refusing a
project member who holds every budget ability there is.

**`budget` and `cost-codes` are swept. Eleven of thirty areas converted. M7 —
Requisitions — is next.**

---

## M7 — Requisitions *(built 2026-08-21)*

Twelve of thirty areas converted, and the pass where two of the notations from
`docs/permissions-notes.md` stop being notations.

### A correction to the catalogue

`requisitions` was declared `money => true` with `approve` marked `limited`.
Neither is true. **A requisition asks for things, not a sum** — its items carry
a quantity and a unit and never a price, because pricing is what the quotation
round is for. No figure on either screen is monetary, and there is nothing for
an approval ceiling to be compared against. Both flags are corrected, and a test
pins them so the claim cannot drift back. Approval limits start at M8, where
money actually arrives.

### The seven grants

| Ability | What it opens |
|---|---|
| `requisitions.view` | The Requisitions tab on both levels, the detail view, its attachments |
| `requisitions.create` | *Add Requisition*, and saving a draft |
| `requisitions.edit` | Editing a draft, returning a submission to draft, cancelling |
| `requisitions.submit` | *Submit for Approval*, and the "save and submit" half of the form's primary button |
| `requisitions.approve` | Approving and rejecting, and cancelling something already approved |
| `requisitions.approve_own` | **New.** Lifts the self-approval block (N2) |
| `requisitions.delete` | Deleting a draft, rejected or cancelled requisition |

`submit` being separate from `create` is what makes the rest meaningful: a site
can raise the ask without being the one who puts it in the queue. The form's
primary button asks both questions, so *Save as draft* works for somebody who
holds `create` alone while *Save and submit* is refused.

### N1 — a submitted requisition is locked

`canBeEdited()` was `draft` **or** `pending`, so what was being asked for could
change after somebody had been asked to approve it. The approval was a signature
on a moving document. It is now `draft` only, and the lock is about the record
rather than the person — an administrator is refused too.

The way back is **Return to Draft**, which needs `requisitions.edit` and is
either yours or a reviewer's. A raiser can always pull their own submission
back; a reviewer can send one back for more detail instead of rejecting it
outright. Either way it costs the requisition its place in the queue and writes
`pending → draft` into its history, so withdrawing is visible.

### N1 — Duplicate

The piece the owner asked for. **Duplicate** copies a requisition into a fresh
draft owned by whoever pressed it, from **any** status including approved and
rejected — the point is to raise a near-identical ask without touching a
document somebody has already signed.

Copied: title, type, location, budget code and every line. **Not** copied: the
status, the reviewer, the review notes and the needed-by date, which was
somebody else's deadline. The copy gets its own requisition number and the
original is untouched.

### N2 — the reviewer must not be the requester

Approving is refused when the approver either **keyed it in** (`created_by`) or
is **named as the person it is for** (`requested_by`) — approving your own ask
is the same act under either heading. *Rejecting* your own is not blocked; it is
not the problem self-approval is.

**It is lifted by a grant, not by a hard-coded exception.**
`requisitions.approve_own` is held back from both seeded roles and from every
template, so a company small enough that the raiser and the reviewer are the
same person ticks one box — and the fact that they did is on the record. The
detail view says *"You raised this requisition, so somebody else has to approve
it"* rather than silently dropping the button.

**What this does not do:** an administrator holds every ability by definition,
including `approve_own`, so administrators can still approve their own. Making
the block admin-proof would mean a rule outside the ability system, which is the
one thing this module exists to remove. Recorded rather than assumed.

### Holes closed on the way

1. **`FIELD(priority, …)` in both list queries is MySQL-only** and threw on
   sqlite, which is why neither requisition screen had ever been covered by a
   test. Rewritten as a portable `CASE`; the ordering is unchanged on MySQL.
2. **Cancelling had no guard at all** — any signed-in user could cancel anyone's
   pending requisition. Now `requisitions.edit`, with the approved case still
   needing `requisitions.approve`.
3. **`requisitions/` added to `FileController::authorizeFile()`**, same
   polymorphic-attachment shape as income.

`RequisitionTest` — 20 cases: the three roles unchanged; the tab gone without
the grant; a job-site member held to their site; each grant refused on its own,
including *Save and submit* while *Save as draft* is allowed; a submitted
requisition unopenable **by an administrator**; the raiser pulling their own back
and somebody else's refused without review; duplicate copying the lines and
dropping the approval; self-approval blocked by creation *and* by being the named
requester, lifted by the grant, and rejection unaffected; no seeded role or
template holding `approve_own`; attachments scoped; and the money/limited claims
pinned false.

**`requisitions` is swept. Twelve of thirty areas converted. M8 — Quotations —
is next, and is where approval limits genuinely start.**

---

## M8 — Quotations *(built 2026-08-21)*

Thirteen of thirty areas converted. The first area where money is genuinely
committed, and so the first whose actions obey `approval_limit` — the resolver
has carried `approvalLimit()` and `withinApprovalLimit()` since E2 and nothing
had used them until now.

### Seven grants, four of them held apart on purpose

| Ability | What it opens |
|---|---|
| `quotations.view` | The Quotations tab on both levels, the round, the comparison map, its files |
| `quotations.create` | Raising a round **from an approved requisition** |
| `quotations.create_standalone` | Raising one **from nothing** — the half of N1 that M7 could not close |
| `quotations.edit` | The round's own record, inviting vendors, sending the RFQ, keying in proposals, negotiating, cancelling |
| `quotations.award` | Picking the winner, and undoing that choice — **capped by the ceiling** |
| `quotations.award_own` | Awarding proposals you keyed in yourself (N3) |
| `quotations.convert` | Committing an award into a **purchase order** — **capped** |
| `quotations.convert_contract` | Committing it into a **contract** — **capped** |

### N1, finally closed

`quotations.purchase_requisition_id` is nullable by design, so a round could be
raised with no requisition at all and the whole approval chain M7 built was
walked around by starting one step further down. It is now a grant of its own,
checked in three places: the button is not rendered, `openAddModal()` refuses,
and `saveQuotation()` refuses again for any new round with no requisition
behind it — so driving the form directly does not get past it.

**This tightens what the application did**, deliberately. An employee can raise
a standalone round today and cannot after this. A manager keeps it, because a
manager can approve the requisition they would otherwise have needed — nothing
is being walked around — and it is one tick for anybody else.

### Approval limits, for real

`approval_limit` lives on a permission template and on a membership, in cents.
`authorizeAbilityWithin()` checks the ability first and then the ceiling.

- **Award** is checked against what the *proposed* winner would commit. That
  figure does not exist yet when the award form opens, so the check happens
  after the winner is chosen and before anything is written —
  `Quotation::totalForProposedAward()` computes it for both a whole award (the
  winner's equalized total) and a split (winning line prices plus each winning
  vendor's own freight, taxes and discount).
- **Convert** is checked against `awardedTotalInCents()`, the money actually
  being committed.

Both ends matter: a round awarded by somebody with no ceiling still cannot be
*converted* by somebody whose ceiling is below it. And the screen states the
figure and the ceiling rather than silently dropping a button.

### N3 — awarding proposals you keyed in

`quotation_vendors` recorded who *invited* a vendor (`created_by`) but not who
typed their prices, and those are different acts: inviting three vendors is
administration, typing what they quoted is where a number could be favoured. M8
adds `priced_by`, set on every proposal save.

The block is on the **winning** rows only — keying in a losing vendor's numbers
is no conflict — and is lifted by `quotations.award_own`, held back from both
seeded roles and every template. Existing proposals carry a null `priced_by`,
and an unknown author is never treated as the current user, so nothing already
in the system becomes un-awardable.

### Contract conversion held tighter than a purchase order

A service round becomes a **contract** — a schedule of future payments — and
anything else becomes one purchase order. `convertsToContract()` names that rule
in one place, and the two now take different grants, so a buyer can be given
purchase orders without being given the authority to commit a payment schedule.

`convert_contract` is seeded to manager and admin, reproducing today, where
whoever could convert could convert to either.

### Files

`quotations/` is the first directory with **two owners**: the round carries the
RFQ's own files, and each vendor row carries the proposal that came back.
`FileController::authorizeFile()` now accepts a list of attachable types, and
`QuotationVendor` declares `permissionScope()` so a proposal row reaches its
project through its round.

`QuotationTest` — 20 cases: the three roles unchanged; the tab gone without the
grant; standalone refused at the button, the modal *and* the save, and allowed
with the grant; the employee role not holding it while the manager role does; an
award above the ceiling refused and one within it allowed; no ceiling meaning no
ceiling; the ceiling binding conversion even when somebody else awarded; the
ceiling read from the membership of the project in hand; the winning proposal's
author blocked, a losing proposal's author not, and the block lifted by the
grant; `priced_by` recorded on save; no seeded role or template holding
`award_own`; a service round refusing `convert` alone while a material round
accepts it; award, edit, delete and cancel each refused on their own; revoking
needing award rather than edit; and the three `limited` flags pinned.

**`quotations` is swept. Thirteen of thirty areas converted. M9 — Purchase
Orders — is next.**

---

## M9 — Purchase Orders *(built 2026-08-21)*

Fourteen of thirty areas converted. Like M6, this pass **builds** the ability
that had nothing behind it.

### Six grants, and why `approve` and `receive` are not the same one

| Ability | What it opens |
|---|---|
| `purchase-orders.view` | The tab on both levels, an order, its document |
| `purchase-orders.create` | Raising an order, saving a draft, saving and submitting |
| `purchase-orders.edit` | The order's record, submitting for approval, cancelling, revise-and-resubmit |
| `purchase-orders.approve` | Approving and rejecting — **capped by the ceiling** |
| `purchase-orders.receive` | Recording a delivery |
| `purchase-orders.delete` | Deleting an order |

On a real site the office approves the spend and the storeman signs for the
lorry. Those are two people, so they are two grants — and the tests prove both
directions: somebody who may approve every penny cannot record a delivery, and
the storeman who may sign for anything cannot approve.

`approve` obeys the ceiling because approving **creates the expense** — that is
the moment the money is committed. `receive` commits nothing, so it does not,
and rejecting is not capped either: turning an order down costs nothing, and a
reviewer with a R$ 1 ceiling can still reject a R$ 4.000 order.

### Receiving, built

`purchase-orders.receive` existed in the catalogue and nothing in the code
recorded that goods had arrived. The only thing called "receipt" on an order was
a *document* attached when raising it — the supplier's confirmation — which is a
different act entirely.

Part-deliveries are the normal case on site, so what arrived is tracked **per
line**:

- `purchase_order_items.received_quantity` — cumulative, kept on the line
  because the outstanding figure is read on every screen while the deliveries
  are only read when somebody opens the history. Both are written in one
  transaction, so they cannot drift.
- `purchase_order_receipts` — one row per delivery: the date, who signed, and a
  note.
- `purchase_order_receipt_lines` — what was on that delivery.

The order screen gains **Ordered / Received / Outstanding** columns and a status
— *awaiting delivery* → *partially received* → *received* — plus a delivery
history. The columns appear only once the order is approved, because nothing
arrives against a draft.

Two rules worth stating because they are decisions rather than mechanics:
**an over-delivery is capped at what was outstanding** (999 bags against an
order for 10 books in 10 — the rest is a conversation with the supplier, not a
bigger order), and **a delivery with nothing on it is refused with a reason**
rather than silently writing an empty receipt. The receipt form pre-fills every
line with what is still outstanding, because the whole thing arriving is the
common case and typing it again is work.

### Holes closed on the way

1. **All four purchase-order components had no guard of any kind.** Not admin,
   not role — any signed-in user could raise, edit, approve or cancel an order,
   and `approve()` creates an expense. All four now run on abilities.
2. **The job-site Budget tab was never guarded.** M6 swept `budget` and
   converted the budget screens, but `JobSiteShow` serves the job-site budget
   tab and its `authorizeTab()` map only listed expenses. Both it and the
   purchase-orders tab are in the map now.
3. **P12 fixed properly.** `2026_08_21_140000` drops the legacy
   `suppliers` / `subcontractors` foreign keys on every non-MySQL driver, so
   the test database finally matches what production has looked like since the
   vendor unification. `QuotationTest`'s scaffolding row is gone, and the
   award-to-purchase-order path is now covered against a real schema.

`PurchaseOrderTest` — 18 cases: the three roles unchanged; the tab and the
detail gone without the grant; create and edit separate; approving refused
without its grant, refused above the ceiling and allowed within it; rejecting
needing review but not the ceiling; cancelling an approved order needing review;
approve and receive proven separate in both directions; a full delivery closing
the order with who, when and how many; a part delivery leaving the rest
outstanding and a second delivery closing it; an over-delivery capped; an empty
delivery refused; only an approved order taking delivery; the delivery columns
absent on a draft; the order document scoped; the templates pinned; and the
`limited` flags pinned.

**`purchase-orders` is swept. Fourteen of thirty areas converted. M10 — Change
Orders — is next.**

---

## M10 — Change Orders *(built 2026-08-21)*

Fifteen of thirty areas converted, and the four open questions in
`docs/permissions-notes.md` §4b are answered.

Approving a change order is what moves the cost budget, and until this pass
**anyone who could reach the screen could approve, reject or return one** — no
guard of any kind, and nothing separating the person who raised it from the
person who decided on it.

### Six grants, and what each one is for

| Ability | What it opens |
|---|---|
| `change-orders.view` | The tab on both levels, the detail, the signed file |
| `change-orders.create` | Raising one |
| `change-orders.edit` | Changing one, and returning a **pending** one to pending |
| `change-orders.approve` | Approving, and turning down something still pending — **capped by the ceiling** |
| `change-orders.approve_own` | Approving one you raised yourself |
| `change-orders.unapprove` | Pulling an **approved** change back out of a live budget |
| `change-orders.delete` | Deleting — and never an approved one |

### The four answers

**1. Who may approve, and up to how much.** `change-orders.approve`, seeded to
manager and administrator. That is a **tightening**: an employee can approve one
today and cannot after this. It obeys the ceiling, measured against the
**cost** side rather than the revenue side, because the ceiling is about what
somebody may commit the company to spending. A deductive change order is
measured by its magnitude — taking R$ 4.000 out of a budget is not an act a
spending ceiling should wave through unchecked just because the sign is
negative.

**2. Self-approval.** Blocked, lifted by `change-orders.approve_own` — the same
answer M7 gave for requisitions and M8 for quotation awards, which is what §4b
itself suggested. Turning down your own pending change order is *not* blocked;
that is not the problem self-approval is.

**3. Undoing an approval.** Narrower than making one, and held in its own grant.
The rule is precise about *when*: a **pending** change order's lines are not in
the budget, so turning it down is an ordinary review decision needing
`approve`. An **approved** one's lines are, and taking them back out — by
rejecting or by returning to pending — needs `unapprove`. Somebody who may
approve any amount still cannot undo one.

**4. Deleting an approved change order.** Refused outright. Deleting it would
take its cost lines out of every budget they revised, leaving no record that the
revision ever happened. Un-approve it first, which is a visible act needing
`unapprove`.

Like a locked budget in M6, **this is a rule about the record, so it binds
administrators too** — and the delete button is not rendered on an approved
change order at all.

### Also closed

The legacy `projects/{project}/show` screen's tab map was still guarding only
expenses; change orders and budget are in it now, alongside the same fix made to
`JobSiteShow` in M9. `change_orders/` is added to
`FileController::authorizeFile()`.

`ChangeOrderTest` — 21 cases: the three roles unchanged; the tab gone without
the grant; create and edit separate; approving refused without its grant, above
the ceiling, and for a deductive change measured by magnitude; the employee role
losing approval while the manager keeps it; self-approval blocked and lifted by
the grant, with rejection unaffected; an approved change refusing both undo
routes to somebody holding `approve`, and yielding to `unapprove`; a pending one
turned down with `approve` alone; an approved one undeletable **by an
administrator** and deletable once returned to pending; the delete button absent
on an approved change; a foreign project's change order unreachable through
three actions; the file scoped; no seeded role or template holding `approve_own`
or `unapprove`; and the `limited` flags pinned.

**`change-orders` is swept. Fifteen of thirty areas converted — half the
catalogue. M11 — Contracts & Payments — is next, and is the largest remaining
pass: four company-wide money screens with no guard today.**

---

## M11 — Contracts & Payments *(built 2026-08-21)*

Seventeen of thirty areas converted. The largest pass and the one held back
longest: **fifteen components, 4,265 lines, and not one guard of any kind
between them.**

That was not an oversight. E1 recorded that the payments dashboard, the contract
payments list and the payment batches were reachable by anybody signed in, and
the owner declined a stopgap so they would be fixed properly in their own pass
rather than patched ahead of the engine. This is that pass.

### The two areas

`contracts` is scoped to a project or a job site. `payments` is the company-wide
half — three screens that belong to no project.

| Ability | What it opens |
|---|---|
| `contracts.view` | The tab on both levels, a contract, its schedule, its measurements, its file |
| `contracts.create` | Raising a contract |
| `contracts.edit` | The record, its status, the schedule-of-values grid, and the *aditivos* |
| `contracts.measure` | Measurements — draft, edit, approve, cancel — and releasing a scheduled instalment |
| `contracts.pay` | Recording a payment and releasing retention — **capped by the ceiling** |
| `contracts.unpay` | **New.** Taking a payment back out |
| `contracts.delete` | Deleting a contract or an *aditivo* |
| `payments.view` | The payments dashboard, the contract payments list, its export |
| `payments.pay` | Marking a payment paid, processing them, approving a batch item |
| `payments.batch` | The batch screens: building, editing, rejecting, cancelling, deleting |
| `payments.refund` | Reserved for **invoice** payments — M15's, not this pass's |

### The rule M10 set, applied again

Doing and undoing are the same grant while nothing has moved, and undoing is
narrower once it has.

- **`measure`** covers confirming work — approving a measurement, releasing a
  scheduled instalment — *and* undoing those, because neither has paid anybody.
- **`pay`** actually moves money, and obeys the ceiling. Checked against the
  amount on the form rather than when the modal opened, because the figure does
  not exist until it is typed. Releasing retention is a payment too: it is money
  that was held back and is now being handed over.
- **`unpay`** takes a payment back out. Somebody who may pay any amount still
  cannot undo one, and no seeded role or template holds it.

### The company-wide screens: reproduced, not tightened

All three still answer for every seeded role, which is the pass rule. **The
difference is that it can now be taken away** — and the three grants are
separable, so somebody can read the payments dashboard without being able to
build a batch.

Two of them (the batch index and the batch detail) have no `mount()` to guard,
so the routes carry `ability:` middleware as well as the components carrying
their own checks.

**`payments.pay` is not `limited`, and that is a limitation rather than a
decision.** `approval_limit` lives on a membership or a permission template, and
these screens belong to no project, so there is nothing for a ceiling to read.
The same root cause as P6 and P13, and it wants deciding before F1.

### The *aditivo* is a contract's, not a change order's

`App\Livewire\Contract\ContractChangeOrders` amends a **contract**;
`change-orders` (M10) is a change to the **project's** scope. They are different
records in different tables with different files, and M10 deliberately left this
one alone so the two would not be conflated. It runs on `contracts.edit` and
`contracts.delete`, and `contract-change-orders/` joins `contracts/` in
`FileController::authorizeFile()`.

`ContractPaymentTest` — 17 cases: the three roles unchanged across four screens;
seeing contracts as a grant; create, edit and delete separate; the status change
needing edit; paying refused without its grant, above the ceiling, and allowed
within it; retention obeying the same ceiling; undoing a payment refused to
somebody who may pay anything and allowed to the narrower grant; no seeded role
or template holding `unpay`; the three money screens reproduced but revocable;
view and batch separable; the export guarded; the menu following the grants; the
contract file scoped; the `limited` flags pinned along with the note about why
`payments.pay` is not one; and the templates pinned.

**`contracts` and `payments` are swept. Seventeen of thirty areas converted.
M12 — Documents — is next.**

---

## M12 — Documents *(built 2026-08-21)*

Eighteen of thirty areas converted, and the pass that makes **reading** a grant
— which in this module it never was.

### N5, and it was worse than "reachable by id"

`Document::isVisibleTo()` returned **true for every non-internal document, to
anybody**, including a signed-out visitor. The download and preview routes sit
behind `auth` and nothing else. So any signed-in person could fetch any
project's drawings, contracts and photos by walking the ids.

It now asks two questions: may this person open documents on the project this
one belongs to, and — if it is flagged internal — may they see those.
`scopeVisibleTo()` narrows lists the same way.

**N8 needed nothing.** The plan said the presigned-URL check should move before
the mint; it was already there. `DocumentFileController::serve()` aborts on
`isVisibleTo` and only then asks the storage service for a URL, which is the
right order and was never wrong. The 60-second TTL and the "a copied URL is
access" property are inherent to serving from object storage, exactly as N8
recorded.

### Five grants where there were two

The module had its own guard trait with a coarse split: `canManageDocuments()`
for every write and `canDeleteDocuments()` for every delete, both reading
`is_admin` / `is_manager` off the user and neither asking *where*.

| Ability | What it opens |
|---|---|
| `documents.view` | The screen, a document, its versions, its download |
| `documents.create` | Uploading, new versions, creating a folder |
| `documents.edit` | Renaming, moving, tagging, recategorising, restoring a version, the bulk moves |
| `documents.delete` | Delete, restore, purge, empty trash |
| `documents.share` | Creating and revoking a share link |
| `documents.see_internal` | The documents flagged internal |

Because they are scoped, somebody can now be given documents on one project and
not another — and `see_internal` is answered per project, so an internal
document is hidden on a project where the grant is absent even from somebody
who holds it elsewhere.

### N7 — who may share

**The owner's answer: one grant, seeded exactly as it works today.** Admin and
manager can create a share link; an employee cannot. The difference is that it
is now an ordinary toggle — revocable per role, per template, per project and
per person — because it is the one place in the application where access leaves
the application.

The folder-vs-document split N7 offered as a third option was not taken; a
folder link and a document link are the same grant.

### Uploads go through a controller

`DocumentUploadController` had the same "manager or administrator" check, asked
about the person and never the location — so anybody who could upload anywhere
could upload everywhere. It now asks `documents.create` against the project or
job site the request names on `init`, and against the version's own document on
`parts`, `complete` and `abort`, because the later calls carry only an upload
id.

`SharedDocumentController` is deliberately untouched: it serves a public token,
which is the share link doing its job, and has never been a permissions
question.

`DocumentTest` — 18 cases: a foreign project's document invisible by id and a
signed-out visitor seeing nothing; the download and preview routes refusing;
the screen as a grant with the seeded roles still reaching it; internal
documents needing their own grant, hidden from lists, and answered per project;
upload, edit, delete and share each refused on their own; create not carrying
share and share not carrying create or delete; the upload endpoint refusing
another project and refusing somebody with no grant; share seeded exactly as
before and revocable on one project; `share` marked sensitive; the templates
pinned; and a guest holding `view` but never `see_internal` or `share`.

**`documents` is swept. Eighteen of thirty areas converted. M13 — Tasks &
Meetings — is next.**

---

## M13 — Tasks & Meetings *(built 2026-08-21)*

Twenty of thirty areas converted. Two things make this pass different from the
twelve before it, and both are about **where the scope comes from**.

### A task's scope is the task's, not the screen's

Every pass so far guarded a screen that belonged to one project. **My Tasks
belongs to none** — it is a cross-project list of what is assigned to you — so
there is no route to hang a scope on.

So `ManagesTasks` asks every grant about the **task**: `taskInScope()` loads it
and checks `tasks.view` before anything else happens, and the create guard asks
about wherever the form is currently pointing. A task that belongs to no
project at all — a personal one — has no scope to check and is answered by the
role, which is what an unscoped ability does.

The list itself is **filtered rather than guarded**: `Task::visibleTo()` leaves
a company-wide person untouched and narrows a confined one to the projects and
job sites they hold, plus the personal tasks that belong to nobody's project.
All three counters on the dashboard and the project filter go through it.

### A meeting has no project at all

A meeting spans several through its `meeting_items`, and the series carries its
own scopes. There is no `project_id` on the record. So the meeting grants are
asked **without a scope** — for a company-wide person the role answers, and for
a confined one the resolver answers from their memberships taken together,
which is exactly what a cross-project screen needs.

| Ability | What it opens |
|---|---|
| `meetings.view` | The list, a meeting, its minute PDF |
| `meetings.create` | Raising a meeting |
| `meetings.edit` | The meeting's record and its agenda |
| `meetings.freeze` | **Publishing** — freezing the minute and mailing it |
| `meetings.manage_series` | The meeting series screen |
| `meetings.delete` | Deleting a series |
| `tasks.view/create/edit/close/delete` | The task half |

### Holes closed

1. **`publish()` had no guard of any kind.** It freezes the minute and mails it
   to every attendee. It is `meetings.freeze` now, seeded to the same people
   who could already open the meeting form.
2. **`downloadTaskFile()` had no check at all** — any signed-in person could
   fetch any task's attachment by walking the ids, the same class of hole as
   N5. It answers from the task the file hangs on, whether directly or through
   a note.
3. **`deleteTaskFile()`** allowed "your own file, or an admin or manager" —
   asked about the person, never about which task. Now: your own, or somebody
   who may edit that task.
4. **The minute PDF is guarded** — the first instalment of P22, which records
   that every PDF controller in the application is still `auth` only.

### Reproduced exactly

Reading a meeting was open to any signed-in user; running one was manager and
above. Both are reproduced, which meant seeding `meetings.create`, `edit` and
`freeze` to manager rather than leaving them on both roles — the old guards were
tighter than the abilities would have been. Deleting a series stays
administrator-only.

`TaskMeetingTest` — 16 cases, including one that greps the seven converted
files for `is_admin` and `is_manager` and fails if either comes back.

**`tasks` and `meetings` are swept. Twenty of thirty areas converted. M14 —
Daily Reports — is next.**

---

## M14 — Daily Reports *(built 2026-08-21)*

Twenty-one of thirty areas converted, and **every tab inside a project is now
swept except the financial report**.

The site's diary is the simplest module left — four screens, no money, no
approval. What makes this pass worth its own entry is that it is the first one
whose screen matters to the two seeded templates every earlier pass barely
touched.

### The two personas, finally exercised

**Site Supervisor** and the read-only **Client** guest have shipped since E1 and
hold almost nothing outside this module. Every pass so far has been about money
and buying. This is the first where their own screen is converted, so it is the
first real proof that the templates behave.

Both are now tested end to end:

- A **Site Supervisor** on one job site reads and files the diary there, is
  refused the project above it and every other site, and cannot reopen a closed
  report.
- A **Client guest** follows the project's diary, cannot file or correct
  anything, cannot reach another project's diary, and finds the expenses and
  budget screens shut — which is the whole point of a guest.

### A report closes after seven days

`isEditable()` returns false seven days after the report's date, or as soon as
it is locked. The override was a hard-coded `is_admin` on the form; it is
`daily-reports.edit_locked` now — sensitive, held back from both seeded roles
and every template, the same shape as `expenses.edit_paid` and
`contracts.unpay`.

### P22's second instalment, and the last two file directories

**The daily report PDF was `auth` only**, so anybody signed in could fetch any
project's diary by changing the number in the URL. Both routes are guarded
against the report's own project now.

`daily_reports/` joins the file controller's map. A photo hangs off a task or a
manpower log rather than off the report, so the walk goes one step further:
the image row is looked up by its stored path — never by trusting the id in the
path — and answers from the report behind it. `temp_daily_reports/` is
deliberately left open, like `livewire-tmp/`: it holds in-flight uploads that
belong to no record yet.

### Found on the way

**The project financial report runs a MySQL-only `DATE_FORMAT`**, so its screen
500s on sqlite and has never been covered by a test — the same class as the
`FIELD()` found in M7. It belongs to M17; recorded as P28, and the two
bookkeeping tests that used to drive that route now state the fact instead.

`DailyReportTest` — 13 cases: the three roles unchanged; the diary as a grant;
filing and correcting separate; a closed report needing `edit_locked` and a
locked one closed however recent; no role or template holding it; the Site
Supervisor and the Client guest each proven end to end; the PDF refused by id
and refused across projects; and the templates pinned.

**`daily-reports` is swept. Twenty-one of thirty areas converted. Nine remain:
dashboard, clients, vendors, catalog, estimates, invoices, reports,
documentation and project-report. M15 — Estimates & Invoices — is next.**

---

## M15 — Estimates & Invoices *(built 2026-08-21)*

Twenty-three of thirty areas converted, and **the last two of the six unguarded
money screens E1 recorded are closed**. Every one of them is now a grant.

Both areas are **company-wide**: an estimate belongs to a client, not to a
project, so they are held by role and appear in neither project editor.

| Ability | What it opens |
|---|---|
| `estimates.view` / `invoices.view` | The lists, a record, its PDF |
| `estimates.create` / `invoices.create` | Raising one |
| `estimates.edit` / `invoices.edit` | The record, and marking an estimate accepted or declined |
| `estimates.send` / `invoices.send` | Sending it to the client, and the send-email panel |
| `invoices.record_payment` | Taking money in — the modal and both payment paths |
| `payments.refund` | Giving it back |
| `estimates.delete` / `invoices.delete` | Deleting |

Three things are deliberately held apart. **Sending** is not editing: it is what
puts a document in front of a client. **Recording a payment** is not editing the
invoice. And **refunding** is `payments.refund` — the ability E1 reserved and
no pass had used, because refunds exist only on invoice payments and never on
contract or expense ones.

**Converting an accepted estimate into an invoice needs `invoices.create`**, not
an estimate grant. Holding every estimate ability there is does not let somebody
raise an invoice.

### The public pay link is a token boundary, not a permissions question

An invoice carries a `payment_token`, and `/pay/{token}` lets an
**unauthenticated** visitor settle it through CardPointe. That is the same shape
as a document share link: the token is the whole credential, the route sits
outside `auth`, and it is throttled.

It is deliberately **not guarded** — the client has no account — but "not a
permissions question" is a claim worth proving, so the boundary is tested: a
wrong token 404s, a **draft** invoice's token 404s (it has been sent to nobody),
one token names one invoice and no other, a visitor holding a valid token is
still not signed in, and the amount is capped at the balance due with the
invoice refreshed immediately before the charge.

### A bug this pass introduced, and the rule that came out of it

The send-email panel is **embedded in the detail page**. Guarding its `mount()`
with `estimates.send` took the whole detail page away from anybody who could
only read — a 403 on a screen they were entitled to.

**A nested component's `mount()` must not require more than its parent's.** The
guard belongs on the embed (`@can` around the `<livewire:…>` tag) and on the
action, not on the child's mount. Every other nested component in the
application was checked: the five under Access and System Settings each ask for
exactly the ability their parent already required, so none of them had the same
fault.

### P22

The estimate and invoice PDFs are guarded in their controllers. Both were `auth`
only, so anybody signed in could fetch any client's quotation or bill by
changing the number in the URL.

`EstimateInvoiceTest` — 14 cases: the three roles unchanged; both areas
revocable and separable from each other; sending, recording a payment,
refunding and deleting each refused on their own; conversion needing the invoice
grant; the PDFs refused by id; and five on the pay link's boundary.

**`estimates` and `invoices` are swept. Twenty-three of thirty areas converted.
Seven remain: dashboard, clients, vendors, catalog, reports, documentation and
project-report. M16 — Reference data — is next.**

---

## M16 — Reference data *(built 2026-08-21)*

Twenty-six of thirty areas converted. Clients, vendors and the catalog: the
lists the rest of the application points at.

All three are **company-wide** — they belong to no project — so they are held by
role and appear in neither project editor. That is the point of them: one client
list, one vendor list, one catalog, used everywhere.

### One area, three screens

The vendor unification merged suppliers and subcontractors into a single
`vendors` table. So the **Suppliers** screen, the **Subcontractors** screen and
the **merge tool** all read and write the same rows, and all three answer to
`vendors.*` rather than pretending to be separate modules.

`vendors.merge` stays apart from everything else: somebody who may create, edit
*and delete* a vendor still cannot merge two of them, because a merge rewrites
every expense, contract and purchase order that pointed at the loser.

| Ability | What it opens |
|---|---|
| `clients.view/create/edit/delete` | The client list, a client, the quick-create panel on the estimate and invoice forms |
| `vendors.view/create/edit/delete` | Suppliers, subcontractors, their employees and documents |
| `vendors.merge` | The duplicate-merge tool — administrator-only by seed, as before |
| `catalog.view/create/edit/delete` | Items and categories |

### Reproduced exactly

Reading and writing reference data was open to anybody signed in, and only three
things were administrator-only: deleting a supplier, deleting a subcontractor
(and its employees), and the merge tool. All of that is reproduced —
`vendors.delete` and `vendors.merge` are held back from both seeded roles, and
everything else stays on both.

Nine index and form routes carry `ability:` middleware because their components
have no `mount()` to guard — the same shape as the payment batches in M11 and
the estimate and invoice lists in M15.

`ReferenceDataTest` — 12 cases: the three roles unchanged across six screens;
the merge tool still administrator-only; each of the three lists revocable on
its own; suppliers and subcontractors proven to answer to one grant; create and
edit separate from reading; deleting separate again; merging held apart from
every other vendor grant; the client quick-create panel needing `clients.create`
even though it is embedded in another module's form; and the catalogue's own
claims pinned, including that the catalog is a money area and the other two are
not.

**`clients`, `vendors` and `catalog` are swept. Twenty-six of thirty areas
converted. Four remain: dashboard, reports, documentation and project-report.
M17 — Reports — is next, and carries P28's MySQL-only SQL with it.**

---

## M17 — Reports *(built 2026-08-21)*

Twenty-eight of thirty areas converted, and **nothing inside a project is open
any more.** Two areas remain, and neither lives on a project: the dashboard
(M18) and the documentation library.

Two different shapes under one pass.

### The six company reports

They were behind the `admin` middleware — one door for all of them. Each now has
its own grant, so an accountant can be given Sales Tax, Accounts Payable and
Payment Details without being given **Company Financials**, which is the one
that shows what the business is worth and the one marked sensitive.

The seeds keep all six with the administrator, reproducing exactly what the
middleware did. The difference is that they can now be handed out one at a time.

**Every report's PDF answers to the same grant as its screen.** A PDF of a
report somebody may not open is the same disclosure by another door — that is
P22 closed for this module, and the largest share of it.

### The project's own financial report

`project-report` is scoped like every other tab: a member of one project cannot
open another's, and a job-site membership opens the site's report and not the
project's.

**Printing it needs `export`, not `view`.** Reading a project's finances on
screen and sending the PDF on are different acts, and the catalogue already
declared them as two actions — this is the first pass to make the distinction
mean something.

Holding every other grant on the project — expenses, income, budget, contracts —
still does not open the financial report. It is the one screen that puts all of
them on one page.

### P28 fixed: two screens that had never been rendered by a test

`PaymentScheduleService` bucketed rows by month with a bare
`DATE_FORMAT(due_date, '%Y-%m')`. That is MySQL-only, so the **project financial
report** and the **payment schedule report** both 500'd on sqlite — and neither
had ever been rendered in the test suite, which is how the fault survived.

`monthBucket()` now emits the right expression per driver (`strftime` on sqlite,
`to_char` on Postgres, `DATE_FORMAT` on MySQL), and both screens are covered by
a test that renders them. The MySQL output is byte-for-byte what it was.

That is the third instance of the same lesson — after the `FIELD()` in M7 and
this one found in M14. **MySQL-only SQL silently means "untested".**

### A test that had to be rewritten rather than updated

`ProjectScopeTest` carried a case from M2 recording its own boundary: a member
holding nothing but `project.view` still reached every tab of their project,
because none of those modules had had its pass. That is no longer true of
anything, so the case now asserts the opposite — twelve tabs, all refused.

`SecurityStateTest`'s equivalent does the same and pins the remaining unswept
list at exactly `['dashboard', 'documentation']`.

And the resolver's proof that the bridge denies a confined user outright had no
subject left: every scoped area is swept. It now un-sweeps `expenses` for one
test, proves the denial, sweeps it back and proves the membership answers. The
bridge is still engine behaviour and still runs on an install part-way through a
deploy, so it is still worth a test.

`ReportTest` — 13 cases: the two never-rendered screens rendering; the six
reports still administrator-only; each one its own grant and holding one giving
none of the others; the tax reports separable from the company accounts; the
PDFs following their screens; the project report scoped, separate from every
other project grant, and its export separate from its view; the job-site report
following its own membership; the menu following the grants; and the seeds and
templates pinned.

**`reports` and `project-report` are swept. Twenty-eight of thirty areas
converted. M18 — Dashboard & search — is the last one.**

---

## M18 — Dashboard & search *(built 2026-08-21)*

The last module pass. **Twenty-nine of thirty areas converted.** The one that
is left is the documentation library, which is read-only to everybody signed in
by design; F3 decides whether it is swept as it stands or stays on the bridge
for good.

The dashboard is unlike every other screen in this module, because **nothing on
it belongs to it.** Every card and every panel is a summary of some other
module. Guarding the page was never the interesting question; guarding what is
drawn on it was.

### Two abilities, because there are two questions

`dashboard.view` opens the page. **Everybody holds it**, because `config/fortify.php`
lands every login here and a 403 at the front door is no way to greet anybody.
It is still a real toggle — it can be taken away — but that is a deliberate act,
not a default.

`dashboard.overview` is what fills the page. It reproduces exactly the
`@if ($role === 'admin')` the view used to carry: seeded to administrators and
held back from both seeded roles. The difference is that it can now be handed
to somebody who is not an administrator — a finance manager, an owner's
assistant — without making them one.

### Holding the overview is not holding its contents

Each block asks for the ability of the module it summarises, and a block that is
off is **never queried for** — the work disappears with the panel, not just the
markup.

| Block | Grant |
|---|---|
| Cash to Pay, Overdue Payments, the AP bars | `expenses.view` |
| Receivables, Past-Due Invoices, the AR bars | `invoices.view` |
| Open Estimates | `estimates.view` |
| Active Projects, at-risk badge | `projects.view` |
| Open Purchase Orders, the PO half of Pending Approvals | `purchase-orders.view` |
| The batch half of Pending Approvals | `payments.view` |
| Over Budget — card and list | `projects.view` **and** `expenses.view` |

Over budget is the one that needs two: it compares a project's spend against its
contract value, so it discloses both, and either grant alone is not enough.

The two layout substitutions the view has always made — Over Budget standing in
for Receivables, Open Purchase Orders standing in for Open Estimates — are kept
exactly, with the ability standing in for the module switch. An administrator's
dashboard is unchanged by this pass, to the pixel.

There is deliberately **no separate module-switch check** in the component: the
resolver already answers `false` for every ability of a module the customer has
switched off, and asking twice could only ever disagree with itself.

### A leak by aggregate is still a leak

A card that counts money across projects somebody cannot open tells them
something the project list would not. Every figure is narrowed through
`visibleProjectIds()`, which returns **null — meaning "no filter" — for anybody
who is not confined**. Null is not an empty array: listing every project in the
install inside a `whereIn` would be an expensive way of writing `1 = 1`, so
today's SQL and today's numbers are untouched, and F1 turns the narrowing on for
everybody it applies to without a further change here.

Two figures are deliberately **not** narrowed: receivables and open estimates.
Invoices and estimates are company-wide areas — an estimate belongs to a client,
not to a project (M15) — so narrowing them here would make the dashboard
disagree with the index screens behind it. Recorded as **P37** rather than
decided silently.

### Money: the one screen whose roll-up belongs to no scope

Every figure here is a roll-up, which is exactly what `can_see_money` hides
(M4). But `canSeeMoney($user, null)` means "company-wide", and for a confined
person that is always no — the wrong answer here, because their figures have
already been narrowed to the projects they are a member of.

So the component decides once, in `canSeeDashboardMoney()`: company-wide people
get the role's finance ability as before, and a confined person's answer is
their memberships — **one project that hides its totals hides the totals here**,
because a sum cannot show half of itself.

`x-ui.money` gained an optional `visible` prop for this, and only this: a
caller-supplied answer for the rare roll-up that points at no single scope.
Leave it unset and the resolver decides, as everywhere else. The cash-flow chart
is nothing but money, so it is removed rather than masked.

### A regression this pass would have shipped, and the fix

A **guest** — an outsider invited to one project — holds no company-wide ability
at all. The resolver refuses them every one by design, so `dashboard.view` is
not something a guest can even be given. Guarding the route would therefore have
put a 403 in front of every guest on every sign-in, because Fortify lands them
here.

`mount()` redirects rather than refuses: somebody without the dashboard is sent
to `InvitationService::landingFor()` — their first project or job site — and is
refused only if there is nowhere to send them. **The front door is the one place
where "no" has to mean "not here, there".**

### The welcome panel

What everybody who is not an administrator has been seeing since this
application was written is a white box reading *"Your dashboard is coming
soon."* It is now a real screen: the person's own open tasks, soonest first,
overdue in red, and shortcut tiles to the areas they may reach.

Neither half is a new permission. The tasks are M13's `Task::visibleTo()`, and
the tiles are **the sidebar's own entries**, so a tile can never offer a screen
its owner would be refused on. Both empty states say which of the two things is
missing — no tasks, or no access — because those need different answers from
whoever reads the screen over their shoulder.

The overview has a designed empty state too, for somebody granted it while
holding none of the modules it reports on.

### N9 closed — the header search

The search was scoped back in M2, when `Project::visibleTo()` and
`JobSite::visibleTo()` landed, and it searches nothing else. What was missing
was the proof, which this pass adds: a member of one project searching a term
that matches both projects sees one of them, and the same for job sites. The
note rode on N4 and is now closed with it.

### Reproduced exactly

`DashboardTest` — 21 cases: the admin's overview and everybody else's panel
unchanged; the overview grantable to a non-administrator and revocable; each
block arriving with its own grant and the overview alone showing nothing; the
purchase-order substitution; totals narrowed to a member's own project and
unnarrowed for everybody else; a block that is off costing no query; money
masked by a membership and shown when it is not; the guest redirect; the
welcome panel's tiles and its honest empty state; the search scoped on both
models and silent under two characters; and the catalogue pinned at one
unswept area.

**`dashboard` is swept. Twenty-nine of thirty areas converted. Every module
pass is done.**

---

## Not yet built

The three closing steps. See `docs/permissions-module-plan.md` §9.
