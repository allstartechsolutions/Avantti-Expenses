# Task assignment ignores confinement

**Found** 2026-08-26, while doing the query pass on the meeting screens.
**Not fixed** — it needs one fact from production and one decision from you.
**Waiting on** the two queries in *Is this live?* below.

---

## In one sentence

The owner and assignee pickers offer **every active person in the company**, so you can hand
work to somebody who cannot see it — and nothing anywhere says so.

## What actually happens

Verified end to end against a confined user (an `employee` with `access_scope = assigned`) and
a task on a project they hold no membership on:

| | |
|---|---|
| Offered in the owner / assignee picker | **yes** |
| Task appears in their My Tasks | **no** |
| They can open the task | **no** — 403 |

No error, no warning, nothing in the log. The task simply does not exist from their side. The
manager who assigned it sees a perfectly normal task with a perfectly normal owner.

Why: `Task::scopeVisibleTo()` (`app/Models/Task.php`) gives a confined user only tasks whose
`project_id` is null, or whose project / job site they hold a membership on. `MyTasks` filters
through it, and `tasks.view` is refused for the same reason, so both the list and the detail
view agree the task is not theirs to see. The picker never asked.

## It also contradicts a written assumption

`docs/meetings-module-plan.md` §11, open decision 1, states: *"an employee sees tasks they own
or are assigned to, plus everything on projects they can already see."* The first half is not
what the code does — a confined employee does **not** see a task they own if the project is not
theirs. That section now carries a pointer back here, but the assumption should be either made
true or rewritten once this is settled.

## Who counts as confined

`User::effectiveAccessScope()` — the user's own `access_scope`, else their **role's**, else
company-wide. So somebody is confined if:

- they are a **guest** (hard-coded, always), or
- their **user** row says `assigned`, or
- their **role** says `assigned` and their user row says nothing.

Two things make this more likely than it looks:

- **The local `employee` role is set to `assigned`** — set by hand on 2026-08-20 when
  confinement was built. `RoleSeeder` does *not* set it, so a fresh install is company-wide and
  unaffected. **Local and a fresh install disagree, which is exactly why production has to be
  checked rather than guessed.**
- **`user_invitations.access_scope` defaults to `'assigned'`**
  (`2026_08_20_140006_create_user_invitations_table.php`), so people who arrive through an
  invitation may land confined without anyone choosing it.

## Is this live? — run these on production

```sql
SELECT name, access_scope FROM roles;
SELECT COUNT(*) FROM users WHERE access_scope = 'assigned' OR is_guest = 1;
```

- **Both come back empty / all `company` / count 0** → nobody is confined, the bug is dormant,
  and this belongs in the permissions **F3** review with the rest of the confinement sweep.
  Not urgent.
- **Anything says `assigned`, or the count is above zero** → it is live, and managers can
  currently assign work into a void. Worth fixing on its own.

## What a fix has to touch

One method feeds all three pickers:

- `ManagesTasks::assignableUsers()` — `app/Livewire/Concerns/ManagesTasks.php`
  - task form **owner** — `resources/views/livewire/task/partials/form-modal.blade.php:124`
  - task form **assignees** — same file, `:137`
  - meeting agenda **item form** owner — `resources/views/livewire/meeting/partials/item-form.blade.php:79`

Because it lives in the shared trait, a change reaches **My Tasks, the project and job-site task
pages, and both meeting screens** at once.

**A picker change alone is not enough.** `saveTask()` validates the owner as
`['required', 'integer', 'exists:users,id']` and nothing more — there is no server-side rule
about *who* may be made owner. Hiding a name from a dropdown is not protection; the
`wire:click` behind it is a public endpoint. Both halves are needed, per `CLAUDE.md`.

## Options

### A. Filter the picker, and say who was left out — *recommended*

Offer only people who can see the task's scope: anyone with a membership on the selected
project or job site, plus company-wide people (who see everything anyway). Add the matching
validation rule so the endpoint refuses what the picker no longer offers.

- The project can change while the form is open, so this must be a `#[Computed]` keyed on
  `task_project_id` / `task_job_site_id`. The form already drives those with
  `wire:model.live`, so the plumbing exists.
- Pair it with a line under the picker — *"2 people aren't on this project. Add them to the
  project team to assign work here."* Silently dropping names from a list will read as a bug
  to whoever is using it, and `CLAUDE.md` asks for partial states to be designed, not blank.
- **What it costs:** you can no longer assign work to a confined person before adding them to
  the project. That is a real workflow change — but the thing it removes does not currently
  work, it just fails silently instead of loudly.

### B. Warn instead of filter

Leave everyone in the list, mark the ones who will not be able to see the task, and say so
again after saving. Cheaper, changes nobody's capability, no validation rule needed — but it
leaves the broken outcome one click away, chosen rather than prevented.

### C. Grant a membership automatically on assignment

Seamless, and the worst of the three: assigning somebody would silently widen who can reach a
project. `CLAUDE.md` — *a change that quietly widens or narrows who can do something is a bug
even when the new behaviour seems more sensible.* Recorded only so it is not re-proposed.

### D. Leave it

Defensible **only** if the queries above show nothing confined in production.

## Recommendation

Run the two queries first, then:

- **nothing confined** → park it in permissions **F3**, no rush;
- **anything confined** → build **A**, both halves, with a test that reproduces the table at
  the top of this file: a confined user offered in the picker, assigned a task, and unable to
  see it.

## How this was verified

A throwaway Livewire test created an `employee` with `access_scope = ASSIGNED` and no
membership, had the admin create a task on a project, then checked three things: that the
employee appeared in `assignableUsers`, that `Task::visibleTo($employee)` excluded the task,
and that `PermissionResolver::allows($employee, 'tasks.view', $task)` was false. All three
confirmed. The probe was deleted; the table above is its output.

**One trap if you re-run this:** a `User::factory()` employee is *not* confined on a
freshly-seeded database, because `RoleSeeder` leaves `access_scope` null. Set
`access_scope => AccessScope::ASSIGNED` on the user explicitly, or the probe will report that
everything is fine.
