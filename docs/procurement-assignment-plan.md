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
context_id     unsignedBigInteger nullable      -- null when context_type = 'global'
role_key       string(40)                       -- 'quotation_buyer' to start
user_id        FK users nullOnDelete
set_by         FK users nullOnDelete
timestamps

unique(context_type, context_id, role_key)
index(role_key)
```

Not a morph: the `global` tier has no row to point at, and a nullable morph id is a worse
lie than a nullable integer with an enum beside it.

`role_key` is a constant on the model, not a free string. One value at first —
`DefaultAssignment::QUOTATION_BUYER` — and the resolver is written generically so the next
module adds a constant and a label, nothing else.

**`DefaultAssignment::resolve(string $roleKey, ?JobSite $jobSite, ?Project $project): ?User`**
walks job site → project → global and returns the first row whose user is still active.
A deactivated user is skipped, not returned — otherwise disabling somebody silently routes
every new requisition into a dead inbox. Cached like `NotificationSetting` does it.

### 2. `purchase_requisitions` — two columns

```
assigned_buyer_id  FK users nullOnDelete   after reviewed_at
assigned_at        timestamp nullable
```

Reassignment writes a `purchase_requisition_status_histories` row with the old and new name
in `reason` — the table already exists and already carries who and when, so this needs no
new history table.

### 3. `quotations` — two columns

```
assigned_to           FK users nullOnDelete   after created_by
due_notified_at       timestamp nullable      -- 'approaching' sent
overdue_notified_at   timestamp nullable      -- 'past due' sent
```

Both stamps are cleared when `responses_due_at` moves, so a round whose deadline is pushed
can warn again later. This mirrors `tasks.overdue_notified_at` precisely.

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
| `requisition_assigned` | Approved **and** assigned — either order. Never on a draft assignment. | The buyer | Once per assignment |
| `requisition_stalled` | Approved, assigned, **no round raised** after N days | The buyer, cc the approver | Every N days, capped |
| `quotation_assigned` | Round raised or reassigned; collaborator added | Owner, or just the person added | Once per assignment |
| `quotation_due_soon` | `responses_due_at` within X days, round still open | Owner + collaborators | Once, re-armed if the date moves |
| `quotation_overdue` | `responses_due_at` passed, round still open | Owner + collaborators | Once, re-armed if the date moves |

Options on the settings screen:

- `requisition_stalled` — **`days` (default 7)** and `max_reminders` (default 4).
- `quotation_due_soon` — **`lead_days` (default 3)**.

Never to the person who did it: being told about your own action is noise. That is
`TaskNotifier`'s rule and it holds here.

### Why the stall reminder repeats, and how it stays idempotent

A one-shot stall notice is lost the first time it is archived, which defeats the point. So
it repeats every N days while the requisition is still stalled, up to `max_reminders`.

Idempotency uses the digest's `meta->window` trick rather than a stamp column: the window
key is `floor(days_since_approved / N)`, so two runs on the same day resolve to the same
window and the second sends nothing. `max_reminders` caps it — a requisition nobody is
going to quote should stop shouting and start showing up in a review, not mail forever.

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
    'swept' => false,          // until the tests pass
    'actions' => ['view', 'edit'],
],
```

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
| **1** | `default_assignments` + resolver + the three settings panels (project, job site, System Settings) | A default can be set at each level and `resolve()` walks the chain, skipping inactive users |
| **2** | `assigned_buyer_id` on requisitions: form field, approve-dialog select, detail view, list column, "Assigned to me" filter, history on reassign | A requisition can be assigned and reassigned, guarded and scoped |
| **3** | `notification_log` + `ProcurementNotifier` + `requisition_assigned` + its settings row | The buyer is mailed on approval-with-assignee, once, logged, and never on a draft |
| **4** | `quotations.assigned_to` + `quotation_assignees` + `quotation_assigned` | Ownership carries onto the round and survives reassignment |
| **5** | The three scheduled reminders + their commands + the options UI | Stall, approaching and past due all fire once per window and survive a double run |
| **6** | The **My quotations** queue, nav badge, unassigned bucket | The three groups list correctly under `visibleTo()` |
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
