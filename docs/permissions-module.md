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

## Not yet built

The eighteen module passes M1–M18, then the three closing steps. See
`docs/permissions-module-plan.md` §9.
