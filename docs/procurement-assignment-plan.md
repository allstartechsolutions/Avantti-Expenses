# Who does the cotação — assignment, defaults and reminders

**Planned 2026-08-27. Not built.** Extends the buy-side chain
(`docs/requisition-module.md`, `docs/quotation-module.md`) with the one thing it has never
had: a person who owns the work, and a system that tells them.

---

## The hole this closes

`purchase_requisitions` records **who asked** (`requested_by`) and **who reviewed**
(`reviewed_by`). `quotations` records **who created the round** (`created_by`) — a row that
does not exist until somebody has *already started*. `quotation_vendors.priced_by` records
who keyed each vendor's numbers.

So the app knows who asked, who approved, and who typed. It has never known **who is
supposed to do the work**, and it has never told them.

The gap is the days between `approved` and the round being raised — the two days somebody
spends ringing three vendors. Today that period is invisible: `approveRequisition()` flashes
*"It can now be quoted"* to the approver, and the record sits in a list until a person
happens to look. Nobody is assigned. Nobody is notified. Nothing chases it.

---

## Decisions locked

| Question | Decision | Why |
|---|---|---|
| One assignee or many, on a requisition | **One** (`assigned_buyer_id`) | *"You, start a cotação for this"* is an instruction. An instruction addressed to three people is not an instruction. |
| One assignee or many, on a round | **One owner + collaborators** | Copies `tasks.owner_id` + `task_assignees` exactly, including its rule: the owner is an implicit assignee and is not duplicated in the pivot. |
| Where defaults live | **A general `default_assignments` table** | Buyer today; RFI ball-in-court, approval reviewer and task owner are all queued behind it. A row per new module, not a migration — the reasoning `notification_settings` was already built on. |
| Resolution order | **job site → project → install → unassigned** | The install has no `company_id` on projects, so the top tier is install-wide and belongs in System Settings, not on a `Company` row. |
| Unassigned allowed | **Yes**, with a visible bucket | A silent fallback to nobody is how these rot. It must be a state you can *see*, not a state you discover. |
| Who assigns | **The approver**, on the approve action | The site person raising it rarely knows who buys steel this month; the manager approving it does. Approve and hand off in one act. |
| Stall reminder | **7 days**, configurable | Owner's number. |
| Due-date reminders | **Approaching and past due**, both | Owner's call. |

---

## Data model — four migrations, all additive

### 1. `default_assignments` (new table)

```
id
context_type   enum('global','project','job_site')
context_id     unsignedBigInteger default 0     -- 0 when context_type = 'global'
role_key       string(40)                       -- 'quotation_buyer' to start
user_id        FK users nullOnDelete
set_by         FK users nullOnDelete
timestamps

unique(context_type, context_id, role_key)
index(role_key)
```

**Built as `default 0`, not nullable** (phase 1). The plan said nullable, but MySQL treats
NULLs as distinct inside a unique index, so a nullable `context_id` would let two `global`
rows exist for one `role_key` — an install-wide default that resolves to whichever row came
back first. That is precisely the silent hole this design is written against, so the
sentinel 0 is what makes `default_assignment_unique` actually enforce one default per role
per context.

Not a morph: the `global` tier has no row to point at, and a morph id that means nothing
is a worse lie than a plain integer with an enum beside it.

**Where the three panels live** (decided at phase 1): one Livewire component,
`App\Livewire\Assignment\DefaultAssignmentsPanel`, rendered in three places — above the
member list on **Projects → Team** and **Job Site → Team**, and as an **Assignments** tab in
System Settings. On the Team page because it names one of the people in the list, and it is
one component rather than three near-identical screens because that is how they drift apart.

`role_key` is a constant on the model, not a free string. Two values so far —
`DefaultAssignment::REQUISITION_APPROVER` and `DefaultAssignment::QUOTATION_BUYER` — and the
resolver is written generically so the next module adds a constant and a label, nothing else.

Each role also declares, through `DefaultAssignment::abilityFor()`, the ability somebody must
hold before it may name them: `requisitions.approve` for the approver, `quotations.create`
for the buyer. Note the direction — the ability decides who is **eligible to be named**;
being named never grants anything. `BuyerDirectory::holdersOf()` answers both questions with
one method so a picker and the endpoint behind it cannot drift apart.

**`DefaultAssignment::resolve(string $roleKey, ?JobSite $jobSite, ?Project $project): ?User`**
walks job site → project → global and returns the first row whose user is still active.
A deactivated user is skipped, not returned — otherwise disabling somebody silently routes
every new requisition into a dead inbox. Cached like `NotificationSetting` does it.

### 2. `purchase_requisitions` — two columns

```
assigned_buyer_id  FK users nullOnDelete   after reviewed_at
assigned_at        timestamp nullable
index(assigned_buyer_id, status)
```

Reassignment writes a `purchase_requisition_status_histories` row with the old and new name
in `reason` — the table already exists and already carries who and when, so this needs no
new history table.

**How an assignment row is told apart from a status move** (phase 2): `old_status` and
`new_status` are written **equal**. `new_status` is a NOT NULL enum, so the row cannot simply
omit them, and equal values are a state a real status change never produces. The history
renders such a row as *Assignment changed* with the two names underneath, rather than the
nonsense "Approved to Approved".

**`assigned_at` is stamped only when the assignment is an instruction.** The raise form's
select is a *suggestion* — it writes `assigned_buyer_id` and leaves `assigned_at` null — and
the stamp is set at approval, or on a reassignment of anything past draft. Phase 5 counts
stalled days from that stamp, so a requisition sitting in draft for a fortnight must not
start the clock.

**One shared eligible-buyer list.** `App\Services\BuyerDirectory` answers "who may raise a
round here" for the defaults panel, the raise form, the approve dialog and the reassign
control alike, so a picker and the endpoint behind it can never disagree about who is
eligible. It starts from the memberships on the scope — never every user in the company,
which on a confined person's screen would be a staff directory — and puts each candidate
back through the resolver, because a membership proves somebody is *here*, not that they may
buy.

### 3. `quotations` — two columns

```
assigned_to           FK users nullOnDelete   after created_by
due_notified_at       timestamp nullable      -- 'approaching' sent
overdue_notified_at   timestamp nullable      -- 'past due' sent
```

Both stamps are cleared when `responses_due_at` moves, so a round whose deadline is pushed
can warn again later. This mirrors `tasks.overdue_notified_at` precisely.

**Inheritance happens even without the assign grant** (phase 4). A round raised from a
requisition takes that requisition's buyer as its owner *whoever raises it* — carrying a
hand-off forward is not a decision the raiser is making, so gating it on
`quotations.assign` would have quietly dropped ownership every time somebody without that
grant raised the round they had themselves been told to run. What the grant gates is
*changing* the owner. A standalone round falls to the resolved default, then to the person
keying it in.

**The owner is never also a collaborator.** Promoting a collaborator to owner detaches their
pivot row in the same act. Without that, `workers()` would count them twice and every mail
built from it would reach them twice.

**Moving `responses_due_at` re-arms both warnings**, in a model `saving` hook rather than at
the call sites — there are several of those and a stamp left behind by any one of them would
disarm the reminder for good. Two traps found while building it, both now guarded:

- `isDirty('responses_due_at')` alone is not enough. The column is cast to `date`, so a
  value set from a Carbon carrying a time reads as changed on the very next save. The check
  compares the two dates, which is what "the deadline moved" actually means.
- On an **insert** every attribute reads as dirty and there is no previous deadline, so the
  hook has to bail on a record that does not yet exist — otherwise it wipes stamps set in
  the same breath.

### 4. `quotation_assignees` (new pivot)

Same shape as `task_assignees`, same rule in the same words:

```
id
quotation_id  FK cascadeOnDelete
user_id       FK cascadeOnDelete
assigned_by   FK users nullOnDelete
assigned_at   timestamp nullable
timestamps

unique(quotation_id, user_id)
index(user_id)
```

### 5. `notification_log` (new table)

`task_notifications` is task-shaped (`task_id` FK). Rather than reshape a table that is
live and mailing people today, new modules get a polymorphic log of the same design:

```
id
notifiable_type / notifiable_id   nullable morph
user_id  FK cascadeOnDelete
type     string(40)
email    string
sent_at  timestamp nullable
error    text nullable
meta     json nullable
timestamps

index(notifiable_type, notifiable_id, type)
index(['user_id','type'])
index(['type','created_at'])
```

**Deliberate duplication.** `ProcurementNotifier` will repeat roughly sixty lines of
`TaskNotifier`'s send-and-dedupe mechanics. Extracting a shared dispatcher would mean
editing the live task mail path to save sixty lines — a bad trade on a production system.
Logged in `docs/review-and-improvements.md` as a candidate for later extraction, when a
third module wants the same thing.

---

## Where the buyer is chosen

| Moment | Behaviour |
|---|---|
| Raising the requisition | Optional select, pre-filled from the resolved default. A suggestion; the requester may leave it blank. |
| **Approving** | The approve dialog carries the select, pre-filled from the requisition's value or the default. **This is the intended moment.** |
| After approval | Reassignable by anyone holding `requisitions.assign` on that project. History row, and the new person is notified. |
| Raising the round | `quotations.assigned_to` is seeded from the requisition's buyer, and is editable. A standalone round (no requisition) falls back to the resolved default, then to the person raising it. |

**The picker only offers people who can act** — users holding `quotations.create` on that
project, resolved through `PermissionResolver`. Assigning work to somebody who will hit a
403 is a dead letter, and the list is where that gets prevented.

---

## The five e-mails

Each is a `notification_settings` row, so each is switchable per install and mutable per
person through `users.notification_preferences`. `NotificationSetting::enabled()` treats an
unknown key as on, so these send from the moment they deploy.

| Key | Fires when | Goes to | Repeats |
|---|---|---|---|
| `requisition_submitted` | Submitted for approval — either from "Save and submit" or the button on a draft | The **named approver** for that location, or everybody holding `requisitions.approve` there when nobody is named | Once per submission; returning it to draft and sending it again asks again |
| `requisition_awaiting_approval` | Submitted **N days ago** and still undecided | The same people the submission notice went to | Every N days, capped |
| `requisition_decided` | Approved or rejected | Whoever asked for it — the named requester, else whoever keyed it in | Once per decision |
| `requisition_cancelled` | Cancelled at any stage | Whoever asked for it **and** whoever was quoting it | Once |
| `quotation_cancelled` | A round is cancelled | The owner and collaborators | Once |
| `requisition_assigned` | Approved **and** assigned — either order. Never on a draft assignment. | The buyer | Once per assignment |
| `requisition_stalled` | Approved, assigned, **no round raised** after N days | The buyer, cc the approver | Every N days, capped |
| `quotation_assigned` | Round raised or reassigned; collaborator added | Owner, or just the person added | Once per assignment |
| `quotation_due_soon` | `responses_due_at` within X days, round still open | Owner + collaborators | Once, re-armed if the date moves |
| `quotation_overdue` | `responses_due_at` passed, round still open | Owner + collaborators | Once, re-armed if the date moves |

### `requisition_submitted` — added after the first walk-through

The plan as written started at "approved but nobody owns the quoting" and never covered
"submitted but nobody knows". Pressing **Submit for Approval** mailed nobody: the manager
found out by opening the Requisitions screen and noticing the pending count. That is the same
hole this module exists to close, one step earlier in the chain.

**Who is told is a `default_assignments` row, not a permission.** `requisition_approver` is a
second `role_key` in the same table, set on the same panel, resolving job site → project →
install. What it decides is **who is asked first** — `requisitions.approve` remains the only
thing that grants approval, and there is a test asserting that naming somebody who lacks the
grant does not let them approve.

Three consequences of that separation, all tested:

- **Naming nobody reaches everybody** who holds `requisitions.approve` on that scope. A
  submitted requisition that reaches no one is how a site waits a week for an answer, and a
  copy too many is a much smaller problem than silence.
- **A named approver who has since lost the grant falls back to the fan-out** rather than
  swallowing the notice.
- **Somebody who could not act on it anyway is dropped from the list**: N2 says the reviewer
  must not be the requester, so an approver named as the requester is only mailed if they
  hold `requisitions.approve_own`.

Dedupe is keyed on the id of the status-history row that recorded the move to `pending`, so
one notice per submission — and a requisition pulled back to draft and sent again is a new
row, therefore a new notice.

### The other two silences, closed in the same pass

**`requisition_decided` — the answer goes back.** Approved or rejected, whoever asked found
out by going and looking. The rejection half is the worse one: the reason is a *required*
field, and the text somebody was made to write reached nobody at all. It goes to the named
requester when there is one — office staff often raise a requisition on behalf of somebody on
site, and it is the person it is *for* who is waiting — and never to whoever made the
decision. The approval version also names the buyer it was handed to, so the requester learns
who is getting prices in the same breath.

**`requisition_awaiting_approval` — a decision that never comes is chased.** The stall
reminder only started counting *after* approval, so the period when the site is actually
blocked was the one period nothing watched. Same window arithmetic as the quoting stall,
`floor(days_waiting / N)`, and the same cap — but a **shorter default wait (3 days, against
7)**: approving is a minute's work, whereas collecting three quotes genuinely takes days.

It runs inside the existing `procurement:notify-stalled` command rather than a third cron
entry: both answer "nothing is happening to this", one before the decision and one after, and
a second scheduled command is a second thing to notice has stopped running.

Two details worth keeping:

- **"When was it submitted" is read from the status history**, not a column. The move to
  `pending` is already recorded there with its time, and a requisition pulled back to draft
  and sent again should be waiting from the *second* submission — a `submitted_at` column
  would have to remember to reset itself.
- **Who gets asked lives in one place.** `whoDecidesOn()` is shared by the submission notice
  and the chase, so the two can never disagree about who is on the hook — including the N2
  rule that drops an approver who is named as the requester unless they hold `approve_own`.

### Cancelling tells the people whose work stops

Pulling a requisition or a round changed a status on a screen nobody was looking at. The
buyer went on collecting prices for something nobody wanted, and the person who asked never
found out it was off.

The requisition's notice goes to **both** of them and says something different to each: one
has work to stop, the other has a decision to make about raising a new one.

The round's notice matters for a reason outside the system entirely. **The vendors were
invited by e-mail and nothing will tell them it is cancelled** — so the mail lists who they
were and says plainly that letting them know is now a phone call. That is the one thing an
in-app status change cannot do.

Options on the settings screen:

- `requisition_awaiting_approval` — **`days` (default 3)** and `max_reminders` (default 4).
- `requisition_stalled` — **`days` (default 7)** and `max_reminders` (default 4).
- `quotation_due_soon` — **`lead_days` (default 3)**.

Never to the person who did it: being told about your own action is noise. That is
`TaskNotifier`'s rule and it holds here.

### How `requisition_assigned` stays idempotent (phase 3)

Not by a stamp column and not by "one per requisition": the dedupe key is
`meta->window`, set to **`assigned_buyer_id:assigned_at`**. That makes each *hand-off*
unique rather than each requisition, which is what the behaviour actually needs:

- pressing Approve twice, or an approval racing a reassignment, sends once;
- handing it to Maria, taking it back, and handing it to Maria again **does** mail her the
  second time — it is a fresh instruction, and a dedupe keyed on the requisition alone would
  have swallowed it silently.

Three other rules the tests pin down: nothing is sent while the requisition is `draft` or
`pending` (a suggestion is not an instruction), nothing is sent to the person who did the
assigning, and **taking a requisition back off somebody mails nobody** — they find out from
the queue rather than from a message telling them to stop.

### Per-person opt-out

The five triggers are on the **personal notification preferences** screen alongside the task
ones. That screen's `save()` rewrites the whole preferences array, so its key list has to
carry every group: a group left out of it is silently reset to "send it" the next time
anybody saves the page. It now merges `KEYS` and `PROCUREMENT_KEYS`.

### Why the stall reminder repeats, and how it stays idempotent

A one-shot stall notice is lost the first time it is archived, which defeats the point. So
it repeats every N days while the requisition is still stalled, up to `max_reminders`.

Idempotency uses the digest's `meta->window` trick rather than a stamp column: the window
key is `floor(days_since_approved / N)`, so two runs on the same day resolve to the same
window and the second sends nothing. `max_reminders` caps it — a requisition nobody is
going to quote should stop shouting and start showing up in a review, not mail forever.

**The due warnings need a window key too** (phase 5). The stamps alone are not enough: the
stamp gives per-round idempotency within a run, but the notification log also dedupes on
(person, trigger, record), so once a round had warned, every later warning about it would be
refused however often the stamp was cleared. Both warnings therefore carry
`meta->window` = `due:<date>` / `overdue:<date>`. The consequence is deliberate and tested: a
deadline pushed out and pulled back to **the same date** does not warn twice — the people on
it have already been told about that date — while a genuinely new date does.

**The stall clock runs from `assigned_at`, not from the approval.** A requisition approved
with nobody on it is not stalled, it is *unassigned*, and the queue shows that rather than
mailing about it. The window is `floor(days_waiting / N)`, so two runs on the same day
resolve to the same window and the second sends nothing.

### The e-mails are instructions, not FYIs

Subject in the imperative. Body carries the title, `needed_by`, priority, item count and
the location. The link lands on **Raise quotation round for this requisition** — not the
generic list. A person who has to navigate is a person who does it tomorrow.

### Two scheduled commands

```php
// routes/console.php, beside the task ones
Schedule::command('procurement:notify-stalled')->dailyAt('07:05')->withoutOverlapping()->sentryMonitor();
Schedule::command('procurement:notify-due')->dailyAt('07:10')->withoutOverlapping()->sentryMonitor();
```

Both idempotent through the log, both `sentryMonitor()`ed so a dead cron on a customer's
server raises an alert instead of failing silently.

---

## The queue — the part that matters more than the mail

An e-mail is one-shot; the work is multi-day. The assignment must live somewhere a person
returns to.

**"Minhas cotações" / My quotations** — a page listing, for the signed-in user:

1. **To start** — approved requisitions assigned to them with no round yet. Days waiting,
   `needed_by`, priority. Urgent and overdue first.
2. **In progress** — rounds they own or collaborate on, not yet awarded, with
   `responses_due_at` and how many vendors have priced.
3. **Unassigned** — approved requisitions with no buyer at all, visible to anyone holding
   `requisitions.assign`. This is the bucket that stops a null default becoming a silent hole.

Plus a **count badge on the nav** for group 1, and an **"Assigned to me"** filter on the
existing requisition and quotation lists. Every query goes through the existing
`visibleTo()` scopes — an assignment must never widen what somebody can see.

**Built at phase 6:**

- **`visibleTo()` had to be written**, not reused. `PurchaseRequisition` and `Quotation` had
  no such scope: both had only ever been reached through a project or job-site route whose
  middleware does the confining. A cross-project queue has no route to guard, so the list
  itself must filter. Both scopes copy `Rfi::visibleTo()` exactly, minus its
  `whereNull('project_id')` branch — unlike a task, one of these always belongs to a project.
  Adding them put both models on `BridgeRemovedTest`'s pinned `is_admin` inventory, which is
  correct and was updated deliberately.
- **The nav gained badge support**, because it had none. A menu entry may now declare
  `'badge' => [Class::class, 'method']`; `Navigation` calls it with the signed-in user and
  renders the number, a dot when the sidebar is collapsed to a rail, and **nothing at all**
  for zero — a badge reading 0 draws the eye to say there is nothing to see. The badge counts
  group 1 only: rounds in progress are work somebody is getting on with, and a badge is for
  work nobody has started, which is the thing that quietly rots.
- **The unassigned tab is guarded, not just hidden.** `setTab()` refuses it without
  `requisitions.assign`, and `mount()` refuses it arriving through the query string — hiding
  a tab is not protection when the state behind it is a public endpoint.

---

## Permissions — built in, not retro-fitted

Per `docs/permissions-for-new-modules.md`. Two new actions on existing areas, plus one new
area for the defaults.

```php
'requisitions' => [ ... 'actions' => [ ...,
    'assign' => ['name' => 'Assign who quotes it'],
]],

'quotations' => [ ... 'actions' => [ ...,
    'assign' => ['name' => 'Assign who works the round'],
]],

'assignment-defaults' => [
    'name' => 'Assignment defaults',
    'module' => 'projects',
    'levels' => ['global', 'project', 'job_site'],
    'money' => false,
    'swept' => false,          // flips at phase 7
    'actions' => [
        'view' => ['name' => 'See who work falls to by default'],
        'edit' => ['name' => 'Change who work falls to by default'],
    ],
],
```

**How the new abilities are seeded** (phases 1–2). All three start **closed at the role
level** — they are in `ADMIN_ONLY_ABILITIES`, because naming the person every approved
requisition is handed to has no counterpart in the old rules, and this file's standing rule
is that a new grant starts closed rather than being handed to every manager by a deploy.
They reach real people through the project templates instead, which is where "the manager of
*this* project decides" belongs:

| Template | Gets |
|---|---|
| **Project Manager** | `assignment-defaults.view` + `.edit`, `requisitions.assign` |
| **Procurement** | `assignment-defaults.view`, `requisitions.assign` |

Procurement holds `requisitions.assign` without holding `requisitions.approve`, which is the
point of keeping them apart: approving says the company will buy this, assigning says who
goes and gets the prices, and a procurement lead who may not approve spend still shares the
work out among their own people.

`assignment-defaults` is listed in `TestCase::AREAS_UNDER_CONSTRUCTION` until phase 7 — the
panels are guarded, but the requisition and quotation screens that consume the defaults
arrive in phases 2 and 4, so the area is not finished being spent.

Three rules that apply without exception:

- **Guard the action methods**, not the buttons. `assignBuyer()`, `assignQuotation()`,
  `addCollaborator()`, `removeCollaborator()` each call `authorizeAbility()`. The
  `wire:click` behind a hidden button is still a public endpoint.
- **A pivot row grants nothing.** Being added to a round is a work list, not a permission.
  A collaborator without the pricing grant sees the round and cannot price it, and that is
  correct. Softening a guard because "they were assigned" is precisely the hole the
  permissions sweep spent a week closing.
- **Never trust a user id from the browser.** Every assignee id is re-checked against the
  users who hold the ability on *that* project before it is written.

---

## Translation

Every string wrapped as it is written, pt_BR added in the same change — including the five
Mailable subjects and bodies, which are exactly the sort of thing that ships in English.
Glossary: *Cotação*, *Solicitação de Compra*, *Responsável*, *Local*, *Projeto*. Counted
nouns through `trans_choice` — **ordens de compra**, *itens*.

Menu labels for the new queue page go in `lang/en/navigation.php` and
`lang/pt_BR/navigation.php`, not the global JSON.

---

## Phases — one at a time, tested before the next

| # | What | Done when |
|---|---|---|
| **1** ✅ | `default_assignments` + resolver + the three settings panels (project, job site, System Settings) | **Done.** A default can be set at each level and `resolve()` walks the chain, skipping inactive users. 17 tests in `tests/Feature/Permissions/AssignmentDefaultsTest.php`. |
| **2** ✅ | `assigned_buyer_id` on requisitions: form field, approve-dialog select, detail view, list column, "Assigned to me" filter, history on reassign | **Done.** A requisition can be assigned and reassigned, guarded and scoped. 23 tests in `tests/Feature/Permissions/RequisitionAssignmentTest.php`. |
| **3** ✅ | `notification_log` + `ProcurementNotifier` + `requisition_assigned` + its settings row | **Done.** The buyer is mailed on approval-with-assignee, once, logged, and never on a draft. 18 tests in `tests/Feature/Permissions/RequisitionAssignedMailTest.php`. |
| **4** ✅ | `quotations.assigned_to` + `quotation_assignees` + `quotation_assigned` | **Done.** Ownership carries onto the round and survives reassignment. 27 tests in `tests/Feature/Permissions/QuotationAssignmentTest.php`. |
| **5** ✅ | The three scheduled reminders + their commands + the options UI | **Done.** Stall, approaching and past due all fire once per window and survive a double run. 26 tests in `tests/Feature/Permissions/ProcurementRemindersTest.php`. |
| **6** ✅ | The **My quotations** queue, nav badge, unassigned bucket | **Done.** The three groups list correctly under `visibleTo()`. 23 tests in `tests/Feature/Permissions/MyQuotationsTest.php`. |
| **7** | **Review and Improvements** — walk both themes, both locales, a phone; empty and partial states; `swept => true`; docs level with what was built | — |

---

## What this deliberately does not build

- **A notification centre.** There is no bell or in-app inbox in this app, and adding one is
  its own module-sized decision. The in-app signal here is the queue page and the nav badge.
- **Vendor-level assignment inside a round.** `quotation_vendors.priced_by` already records
  who typed each vendor's numbers. Assigning individual vendors to individual people is a
  level of bookkeeping nobody will maintain.
- **Two owners on one round.** When the *scope* divides, the model already has the right
  answer: two rounds, one owner each — the steel to the steel merchants, the concrete to
  the plants. Two owners on one round would produce one comparison map mixing steel and
  concrete quotes, which is worse procurement, not better software. Collaborators are for
  same-scope, more-hands: one collects, another negotiates.
