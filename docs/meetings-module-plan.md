# Meetings, Minutes and Tasks — Plan

**Status: planned 2026-08-19, nothing built.** Written with the owner in a planning session;
every decision in §1 was taken by the owner and must not be re-litigated.

A meeting-minutes module (**ata de reunião**) with a real task system behind it: meetings carry
an agenda of items, items are scoped to a project, a job site or nothing, and an item that
requires work becomes a **task** that outlives the meeting and keeps coming back until it is
closed.

---

## 1. Decisions already taken with the owner

| Question | Decision |
|---|---|
| Minutes vs tasks | **Separate.** The minute is a frozen record of a date; the task is living work. An agenda item is the join between them. |
| Meeting scope | **Company-level.** One meeting spans many projects; each *item* is scoped to a project, a job site, or nothing (a general open item). |
| Closing a task | **Owner marks Ready → chair (or admin/manager) confirms.** A ready task appears in the next meeting as "awaiting confirmation" and is normally closed in front of everyone. |
| Progress % | **Derived when the task has sub-tasks** (server-computed roll-up, read-only), **manual slider** otherwise, with 0/25/50/75/100 quick buttons. Every change logged. |
| Recurrence | **Meeting series** ("Weekly Site Meeting", "Directors Meeting") with default attendees and default projects. Carry-forward looks at the previous meeting *of that series*. |
| Files | **Task's own R2 files**, uploaded through the same presigned direct-to-bucket pipeline as the documents module, with a "file to repository" action for the ones that belong in the project's document repository. The published minute PDF is filed there automatically. |
| Tasks raised outside a meeting | **They never reach an agenda on their own.** A project, job-site or standalone task is invisible to the carry-forward until someone deliberately puts it on an agenda; from that moment it behaves like any other meeting item and carries forward. |
| E-mail — tasks | **Four triggers**: created / assigned to you, closed, gone past due, and one weekly e-mail per user listing all of their open tasks. |
| E-mail — meetings | Minute PDF to attendees on publish; the agenda when a meeting is scheduled. |
| Permissions | **Anyone signed in creates tasks**, adds notes/files and updates progress on tasks they are on. **Admin/manager** create meetings, build the agenda and publish. Owner alone marks Ready; chair/admin/manager closes. |
| Weekly digest scope | **Your own** open tasks (owned by or assigned to you). Admin and manager users also get a short company roll-up at the bottom: open, overdue, and the five oldest items with their owners. |
| Standalone tasks (no project) | Visible to the people on them plus admin/manager, and never on any agenda. Somebody's own work, not a company record. |
| Sign-off | **Publish and lock.** No attendee acknowledgement. Corrections are a logged revision by an admin, with a reason, shown on the document. |

---

## 2. Why the minute and the task are different records

The requirement that drives the whole design is the owner's: *"on the next meeting, every time we
add a project or job site and it has an open task from the last meeting it's supposed to show."*

If the task lived **inside** the meeting item, carrying it forward would mean copying it, and the
copy would immediately start drifting from the original — two rows, two progress values, no single
answer to "is this done?".

So:

- **`meetings`** — a frozen record of one date: who was there, what was discussed, what was decided.
  Once published it does not change.
- **`tasks`** — the living work. One owner, many assignees, a due date, progress, notes, files.
  It outlives every meeting and is usable outside meetings entirely.
- **`meeting_items`** — the join: *at this meeting, about this project, we discussed this task, and
  this is what was said.* The same task appears in June's minute and July's minute as two items,
  each with its own discussion note.

Consequences that come free:

- A task detail screen can list **every meeting where it was discussed**, in order, with what was
  said each time — the history the owner actually wants in the next meeting.
- "Open since ATA-2026-009, discussed in 3 meetings, 12 days overdue" is a query, not bookkeeping.
- Tasks raised outside a meeting (someone just needs something done) use the same system and can be
  pulled into the next agenda.

---

## 3. Data model

Follows the parity rule (`docs/project-jobsite-parity-rule.md`): `project_id` + nullable
`job_site_id`. Tasks extend it by one step — **both nullable**, meaning a general company-level open
item that belongs to no project.

### 3.1 `meeting_series`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| name | string | "Weekly Site Meeting" |
| code | string, unique | `OBRA` — used in the meeting number |
| description | text, nullable | |
| cadence | enum | `weekly`, `biweekly`, `monthly`, `quarterly`, `ad_hoc` — display + next-date suggestion only, no scheduler |
| default_location | string, nullable | |
| is_active | boolean | |
| created_by / updated_by | FK users | |
| timestamps, soft deletes | | |

`meeting_series_members` — default attendees: `series_id`, `user_id` nullable, external
`name`/`company`/`email`, `role` (`chair`, `secretary`, `participant`).
`meeting_series_scopes` — default projects/job sites: `series_id`, `project_id`, `job_site_id` nullable.

Ad-hoc meetings outside a series are allowed (`series_id` nullable); they carry forward from an
explicitly chosen previous meeting.

### 3.2 `meetings`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| series_id | FK, nullable | |
| number | string, unique | `OBRA-2026-014` — series code + year + per-series-per-year sequence. Ad-hoc: `ATA-2026-014`. |
| title | string | defaults to the series name + date |
| meeting_date | date | |
| started_at / ended_at | time, nullable | duration shown on the minute |
| location | string, nullable | |
| meeting_url | string, nullable | for remote meetings |
| chair_id | FK users | the one who confirms completions |
| secretary_id | FK users, nullable | who writes the minute; defaults to the creator |
| status | enum | `draft`, `published`, `cancelled` |
| previous_meeting_id | FK self, nullable | set automatically from the series |
| next_meeting_date | date, nullable | agreed at the end of the meeting |
| next_meeting_id | FK self, nullable | filled when the follow-up draft is created |
| summary | text, nullable | free opening/closing notes (TinyMCE, the editor already in the app) |
| published_at / published_by | | |
| cancelled_at / cancelled_by / cancel_reason | | |
| document_id | FK documents, nullable | the filed minute PDF in the project repository |
| created_by / updated_by | | |
| timestamps, soft deletes | | |

Indexes: `(series_id, meeting_date)`, `status`, `number`.

`meeting_revisions` — every edit after publish: `meeting_id`, `revised_by`, `reason`, `changes`
(json diff), `created_at`. Shown as "Revision 2 — corrected item 3.1, by X on Y" on the minute and
in the PDF.

### 3.3 `meeting_attendees`

`meeting_id`, `user_id` nullable, `name`, `company`, `email` (for external people — clients,
vendors, engineers who are not system users), `role` (`chair`, `secretary`, `participant`),
`attendance` (`present`, `absent`, `excused`), `notified_at`, `notes`.

Seeded from the series members when the meeting is created, then edited on the day.

### 3.4 `meeting_items`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| meeting_id | FK, cascade | |
| parent_id | FK self, nullable | sub-items — numbering 1, 1.1, 1.2 |
| position | int | ordering within the parent |
| project_id | FK, nullable | |
| job_site_id | FK, nullable | |
| type | enum | `information`, `decision`, `action` |
| title | string | |
| discussion | text, nullable | what was said **at this meeting** |
| decision | text, nullable | what was decided (shown in bold on the ata) |
| task_id | FK tasks, nullable | set when the item raises or revisits a task |
| carried_from_item_id | FK self, nullable | the item in the previous meeting this one continues |
| status_at_meeting | json, nullable | snapshot of the task's status/progress/due date as of publish, so the minute stays truthful when the task moves on |
| discussed | boolean, default true | an item put on the agenda but not reached is `false` and rolls over |
| created_by | | |
| timestamps | | |

The number (1, 2, 2.1) is **computed from `position` + hierarchy**, never stored — reordering must
not need a rewrite of every row.

`status_at_meeting` matters: a published minute that says "60 %" must keep saying 60 % next month
when the task is at 90 %.

### 3.5 `tasks`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| number | int, unique | display code `#142`, global (minutes reference tasks across projects) |
| title | string | |
| description | text, nullable | |
| project_id | FK, nullable | |
| job_site_id | FK, nullable | |
| parent_task_id | FK self, nullable | sub-tasks, max 2 levels (a sub-task cannot have children) |
| owner_id | FK users | the only one who may mark Ready |
| priority | enum | `low`, `normal`, `high`, `urgent` |
| status | enum | `open`, `in_progress`, `blocked`, `ready`, `completed`, `cancelled` |
| progress | tinyint 0–100 | manual, or computed when sub-tasks exist |
| start_date | date, nullable | |
| due_date | date, nullable | |
| blocked_reason | text, nullable | required when status = `blocked` |
| origin_meeting_id / origin_item_id | FK, nullable | where it was raised |
| ready_at / ready_by | | |
| completed_at / completed_by | | |
| cancelled_at / cancelled_by / cancel_reason | | |
| created_by / updated_by | | |
| timestamps, soft deletes | | |

Indexes: `(project_id, status)`, `(job_site_id, status)`, `owner_id`, `due_date`, `status`.

`origin_meeting_id` records where a task was *raised*; whether it is **meeting-tracked** is a
different question and is derived from `meeting_items` (see §4). A task created on a project page has
neither, and stays off every agenda until someone adds it.

`task_assignees` — `task_id`, `user_id`, `assigned_by`, `assigned_at`. The owner is always an
implicit assignee; the table holds the others.

`task_notes` — `task_id`, `user_id`, `body` (TinyMCE), `meeting_id` nullable (a note recorded during
a meeting is stamped with it and shows a meeting badge in the timeline), `progress_snapshot`
nullable, timestamps, soft deletes. Editable by the author within a window, then locked; edits
logged.

`task_notifications` — the mail log that keeps the digests idempotent; see §8.

`task_activities` — the audit trail, same convention as `*_status_histories` elsewhere:
`task_id`, `user_id`, `action` (`created`, `status_changed`, `progress_changed`, `due_date_changed`,
`owner_changed`, `assignee_added`, `assignee_removed`, `note_added`, `file_added`, `discussed`),
`old_value`, `new_value`, `meeting_id` nullable, `created_at`.

### 3.6 `file_uploads` — the R2 sibling of `attachments`

The existing `attachments` table is the local-disk, 10 MB, PDF/JPG/PNG attachment used by expenses,
POs, requisitions and quotations. Tasks need R2, so a new polymorphic table:

| Column | Type | Notes |
|---|---|---|
| id, uuid | | |
| attachable_type / attachable_id | morph | `Task`, `TaskNote`, `Meeting` |
| disk | string | `config('documents.disk')` at upload time |
| object_key | string | server-generated, never from user input: `tasks/{task_uuid}/{file_uuid}/{sanitized-name}` |
| original_name, size_bytes, mime_type | | |
| upload_status | enum | `pending`, `available`, `failed` — same lifecycle as `document_versions` |
| multipart_upload_id | string, nullable | |
| document_id | FK documents, nullable | set when the file has been filed into the project repository |
| uploaded_by | | |
| timestamps, soft deletes | | |

**This is deliberately generic** — every future module that needs R2 files uses it, and the
deferred absorption of `attachments` (see `docs/file-repository-plan.md` §1) has a target to land in.

### 3.7 Migration count

13 tables + 1 module_access row. All additive; no changes to existing tables except the optional
`documents.project_id` nullability (§6, open decision) — which is **not** required for v1.

---

## 4. Rules the server enforces

**Status machine**

```
open ──► in_progress ──► ready ──► completed
  │           │  ▲          │
  │           ▼  │          └──(chair reopens)──► in_progress
  │        blocked
  └──────────────────────────────────────────────► cancelled
```

- `ready` may be set **only by `owner_id`**. Nobody else, not even an admin — that is the owner's
  statement about their own work. An admin who needs to force it does so by changing the owner first,
  which is logged.
- `completed` may be set **only from `ready`**, and only by the meeting chair, an admin or a manager.
  Reopening moves it back to `in_progress` with a required reason.
- `blocked` requires `blocked_reason`.
- A task with open sub-tasks **cannot** be marked ready — the button explains which ones are open.
- `cancelled` requires a reason and is only available to admin/manager or the creator.

**Progress**

- With sub-tasks: `progress` = mean of the children's progress; `completed` children count 100,
  `cancelled` children are excluded. Recomputed on every child change, stored on the parent so lists
  stay one query, never editable by hand (the field is read-only in the UI *and* server-side).
- Without sub-tasks: manual 0–100, settable by owner and assignees. Setting 100 does **not** close
  the task — the owner still presses Ready. Setting > 0 on an `open` task moves it to `in_progress`
  automatically.
- `completed` forces 100; `cancelled` freezes the last value.

**Open**

"Open" = `status in (open, in_progress, blocked, ready)`. This single definition drives the
carry-forward, the counters, the digests and the badges — one scope, `Task::scopeOpen()`, the way
`Contract::scopeCommitted()` is the single definition of "counts as money".

**Overdue**

`due_date < today && status not in (completed, cancelled)`. Ready-but-unconfirmed tasks still count
as overdue — otherwise a task parks in `ready` forever and looks healthy.

**What reaches an agenda**

Two populations of task live in the same table:

- **Meeting-tracked** — raised at a meeting, or pulled onto an agenda later. These carry forward
  automatically until closed.
- **Direct** — created on a project page, a job site page, or as a standalone item with no scope at
  all. Somebody's own work. These are **never** proposed by the carry-forward.

The distinction is **derived, not stored**: a task is meeting-tracked when it has at least one
`meeting_items` row (`Task::scopeMeetingTracked()` → `whereHas('meetingItems')`). No flag to keep in
sync, and the transition happens for free — the moment a direct task is added to an agenda it gains
a meeting item and is meeting-tracked from then on. There is no way back, deliberately: once a task
has been discussed in a published minute, dropping it silently out of the follow-up would break the
record.

Why not simply show everything: the ata is the record of what management discussed and committed to,
not a dump of everyone's to-do list. A weekly site meeting that auto-loads forty small tasks becomes
a document nobody reads, and the twelve items that actually matter get lost in it.

---

## 5. Screens

Every screen follows the design standard in `CLAUDE.md`: full-page modals for real work, detail views
that show everything, computed numbers on screen, both themes, both locales, no horizontal scroll.

### 5.1 Meetings index — `/meetings`

Table: number, series, date, title, chair, attendees (present/invited), items, **open actions**,
status pill. Filters: series, status, date range, project, "has overdue actions". Sidebar module
**Meetings**.

Empty state explains what a meeting series is and offers to create the first one.

### 5.2 Meeting create → the agenda builder

The heart of the module. Full page, three regions:

**Header** — series (pre-fills attendees and scopes), date, time, location, chair, secretary.

**Attendance** — the series members pre-listed with present/absent/excused toggles, plus "add
attendee" for guests (internal user picker or free-text external name/company/e-mail).

**Agenda** — assembled from three sources:

1. **Carry-forward (automatic).** On opening a new meeting in a series, every **open task**
   discussed in the previous meeting of that series is proposed as an item, grouped by project /
   job site, each row showing: owner avatar + name, due date (red when overdue, with the day count),
   the progress bar, current status, "open since ATA-2026-009 · 3 meetings", and the last note.
   Each row has a checkbox — unchecked rows are simply not on this agenda and remain open.
2. **Add project / job site.** A picker; the moment a project or job site is added, **all of its
   open meeting-tracked tasks** are pulled in the same way — including ones raised in a different
   series. This is the owner's stated requirement, and it is why tasks are scoped to project/job site
   rather than to the meeting.

   Underneath, collapsed, a line reads *"14 other open tasks on this project are not on the
   agenda"*. Opening it lists them — owner, due date, progress, overdue flag — each with an **Add to
   agenda** button. Nothing is pulled in unless someone presses it. The chair can see that the work
   exists without the minute filling up with it, and a direct task that turns out to need management
   attention is one click from becoming a tracked item.
3. **New item.** Raised fresh: type (information / decision / action), scope (project, job site, or
   *General* for a company-level open item), title, and — for an action — the new task's owner,
   assignees and due date, created inline.

Sub-items are added under any item (drag to nest, max 2 levels, matching the task hierarchy).
Drag to reorder; numbering recomputes live.

Bulk shortcuts, per the design standard: *select all overdue*, *carry everything from last meeting*,
*add all job sites of this project*, *push all due dates by one week*.

### 5.3 Meeting detail / running the meeting — `/meetings/{meeting}`

While `draft` this is the working screen; after publish it is the minute.

Per item, inline and without leaving the page:
- the discussion box (what is being said now),
- the decision box,
- for an action item: progress slider, due date, "add note", owner-only **Mark ready**, chair-only
  **Confirm completion**, **Block** with reason, **Reassign**,
- "not discussed" toggle, which rolls the item to the next meeting untouched.

Right rail: live counters — items discussed / total, actions raised today, actions closed today,
overdue count, attendance.

Footer: **next meeting date** → *Publish*. Publishing:
1. validates that every action item has an owner and a due date,
2. snapshots each item's `status_at_meeting`,
3. locks the meeting,
4. renders the PDF and files it into the project repository (or the company folder for a
   multi-project meeting),
5. e-mails attendees,
6. optionally creates the **next meeting as a draft** with carry-forward already applied.

Editing after publish is admin-only, requires a reason, and writes a `meeting_revisions` row shown
on the document.

### 5.4 Task detail — full-page modal, reachable from everywhere

Everything the record knows, in one screen:

- header: `#142`, title, status pill, priority, scope (project → job site breadcrumb), progress bar
  with the number,
- owner card and assignee chips (add/remove, logged),
- dates: created, start, due (with "in 4 days" / "12 days overdue"), ready, completed,
- description,
- **sub-tasks** with their own progress bars and the roll-up arithmetic shown,
- **notes timeline** — author, timestamp, meeting badge when recorded in a meeting, attachments
  inline (image thumbnails, PDF preview link),
- **files** on the task itself, drag-and-drop to R2, each with size, type, uploader, download
  (presigned, 5 min) and *File to project repository*,
- **discussed in** — every meeting that touched it, with that meeting's discussion text: the history
  the next meeting needs,
- **activity log** — every status, progress, due-date, owner and assignee change with who and when,
- audit facts: created by / created at / last updated by / at.

### 5.5 My Tasks — `/tasks/mine`

Three tabs: **I own** (with the Ready button), **Assigned to me**, **I raised**. Grouped by
overdue / due this week / later / awaiting confirmation. Counter badge in the sidebar.

### 5.6 All Tasks — `/tasks`

Company-wide list with filters: project, job site, owner, assignee, status, priority, due range,
overdue, source meeting, general (no project). Views: list, by owner, by project. CSV export.

### 5.7 Project and Job Site pages (parity rule)

`projects/{project}/tasks` and `job-sites/{jobSite}/tasks` — the same list scoped, plus the meetings
that discussed this project. The project page rolls up its job sites' tasks; the job site page shows
only its own.

Both overview pages gain an **Open action items** card: count, overdue count, next due date, and the
oldest open item — clickable through.

### 5.8 Dashboard

A widget: my overdue tasks, my tasks due this week, tasks awaiting my confirmation (chairs), and the
next scheduled meeting.

### 5.9 The minute PDF (ata)

dompdf, same as the other PDFs. Header with company logo, meeting number, date, time, location.
Attendance table (present / absent / excused, internal and external). Agenda in numbered order with
discussion and decisions. **Action items table**: number, description, owner, due date, status,
progress as at the meeting. Open items from previous meetings shown with their age. Next meeting
date. Revision note when applicable.

---

## 6. Permissions

| Action | Who |
|---|---|
| View meetings and minutes | any signed-in user |
| Create / edit a draft meeting, build the agenda | admin, manager |
| Publish a minute | chair, admin, manager |
| Edit after publish (revision) | admin only, reason required |
| Cancel a meeting | admin, manager |
| Create a task, add notes and files | any signed-in user |
| Edit title/description/due date/priority | owner, creator, admin, manager |
| Change progress | owner, assignees, admin, manager |
| Change owner / assignees | admin, manager, current owner |
| **Mark ready** | **owner only** |
| Confirm completion / reopen | chair of the meeting, admin, manager |
| Cancel a task | creator, admin, manager |
| Delete a task | admin only, and only when it has no notes or files |

Guards live in the model (`Task::canMarkReady(User)`, `canConfirm(User)`) and are called by both the
Blade (to hide the button) and the Livewire action (to refuse it), which is the pattern the rest of
the app uses. Gaps found while building go to `docs/permissions-notes.md`.

---

## 7. Files on R2

The documents module already owns the hard part: `DocumentStorageService` (multipart, presigning,
`HeadObject` verification, stale-upload pruning) and `DocumentUploadController`. Reusing it needs one
refactor, done in phase 0:

- Extract the browser-side multipart uploader (currently inline in the documents Blade) into a
  **shared Alpine component** plus a small `<x-ui.file-uploader>` Blade wrapper, taking an upload
  target and emitting progress events.
- Generalise the init/parts/complete/abort endpoints to accept an **upload target** — today a
  document, from now on also a task, a task note or a meeting — each target class answering
  "who may upload here?", "what key prefix?", "what size/type limits?".
- `DocumentStorageService` keeps working on `document_versions`; the parts it needs to share
  (presign, complete, verify, temporary URL) move behind a small interface that `file_uploads` rows
  satisfy too.

Everything else follows the documents module: allowlist of types, server-generated keys, presigned
5-minute download URLs, local-disk fallback with a plain message when R2 is not configured, and the
scheduled abort of stale multipart uploads (R2 bills incomplete uploads).

Task files are capped lower than repository documents — 100 MB by default (`tasks.max_upload_bytes`)
— because a meeting attachment is a photo, a spreadsheet or a marked-up PDF, not a drawing set. The
"file to repository" action is how a big file gets there.

---

## 8. Notifications

Four task triggers, decided by the owner. Everything else the module knows stays in-app (badges,
My Tasks, the dashboard widget) — an inbox nobody reads is worse than no e-mail at all.

| # | Trigger | Recipients | Content |
|---|---|---|---|
| 1 | **Task created / you were assigned** | the owner and each assignee, **never the person who did it** | task code and title, scope (project → job site, or *General*), owner, due date, priority, description, deep link. Adding an assignee to an existing task fires the same mail for that person only. |
| 2 | **Task closed** | owner, assignees, creator, and the chair of the origin meeting — minus the actor | how it closed (completed or cancelled, with the reason), who closed it, when, final progress, link to the record |
| 3 | **Task went past due** | owner + assignees | fired **once**, the morning after the due date passes — not a daily nag. Lists days overdue, current progress, and the last note. If the due date is later moved forward, the stamp clears and the task can go overdue again. |
| 4 | **Weekly open-tasks digest** | every user with at least one open task | one mail, Monday 07:00: counts at the top, then **overdue** (with day counts), **due this week**, **later**, and **awaiting your confirmation** for chairs. Users with nothing open get nothing. |

Meeting mails are separate and belong to the meetings phases: the minute PDF to attendees on
publish, and the agenda when a meeting is scheduled.

**Settings.** System Settings gains a Notifications panel: a toggle per trigger, plus the digest's
day and hour. Each user can opt out individually on their profile page. A trigger disabled system-
wide beats an individual opt-in.

**Delivery.** The app sends mail synchronously today (`Mail::to()->send()` in Estimates, Invoices
and the quotation RFQ) and `QUEUE_CONNECTION=database` with nothing queued — so a worker cannot be
assumed on every install. Therefore:

- Interactive triggers (1 and 2) go through a `TaskNotifier` service and are dispatched
  `afterResponse()` — the user never waits for SMTP, and no queue worker is required.
- Triggers 3 and 4 are scheduled commands, `tasks:notify-overdue` (daily 07:00) and
  `tasks:send-weekly-digest` (Mondays 07:00), registered in `routes/console.php` beside the existing
  `documents:*` schedule, both `->withoutOverlapping()`. They send inline; a command has no request
  to block.
- **Deploy note:** these need the Laravel scheduler cron, which the documents module already
  requires. Where it is not running, no digest goes out and nothing else breaks.

**Idempotency.** A `task_notifications` table (`task_id` nullable, `user_id`, `type`, `sent_at`,
`meta` json) records every mail sent. Each trigger checks it before sending, so a second run of a
command on the same day mails nobody twice, and "why didn't I get an e-mail?" is answerable from the
database instead of guessed at.

---

## 9. Module registration and i18n

`config/modules.php` gains, **before** `projects` (the module check stops at the first matching
prefix, exactly as `documents` and `quotations` had to):

```php
'meetings' => [
    'name' => 'Meetings',
    'description' => 'Meeting minutes, agendas and the task system behind them.',
    'route_prefixes' => [
        'projects.tasks', 'jobsites.tasks',
        'meetings.*', 'tasks.*', 'meeting-series.*',
    ],
],
```

pt_BR terms — decided now so the whole module is consistent:

| English | pt_BR |
|---|---|
| Meeting | Reunião |
| Minutes / minute | Ata |
| Meeting series | Série de reuniões |
| Agenda | Pauta |
| Agenda item | Item da pauta |
| Action item | Pendência |
| Task | Tarefa |
| Owner | Responsável |
| Assignees | Envolvidos |
| Due date | Prazo |
| Progress | Andamento |
| Ready (awaiting confirmation) | Aguardando confirmação |
| Completed | Concluída |
| Blocked | Impedida |
| Chair | Coordenador |
| Secretary | Secretário |
| Attendees | Participantes |
| Carried forward | Pendente de reunião anterior |

Every string through `__()` with the pt_BR added in the same change — not a later pass.

---

## 10. Build order

One page at a time, tested before the next (CLAUDE.md rule 7). Tasks come **before** meetings: the
meeting screens are worthless without a task system, and the task system is useful on its own.

| Phase | What | Done when |
|---|---|---|
| 0 | Migrations, models, relationships, module row, the shared R2 uploader refactor | `php artisan migrate` runs clean; documents module still uploads exactly as before |
| 1 | Task core — My Tasks page + full task detail modal: create, assign, progress, status machine, notes, files, activity log | A task can be raised, worked and closed end to end without any meeting existing |
| 2 | Project and Job Site task pages + overview cards (parity rule, both levels in the same change) | Both levels identical in function |
| 3 | Meeting series admin + meetings index + meeting create | A meeting can be created with attendance |
| 4 | The agenda builder — carry-forward, add project/job site pulls open tasks, new items, nesting, reorder | The owner's core requirement demonstrably works |
| 5 | Meeting detail / running screen + publish + lock + revisions | A minute can be run and published |
| 6 | Minute PDF + filing to the repository + attendee e-mail | The ata leaves the system looking like a document a client would accept |
| 7 | Notifications — created/assigned, closed, past due, weekly open-tasks digest, `TaskNotifier` + System Settings toggles and per-user opt-out | A second run of either command on the same day mails nobody twice |
| 8 | Dashboard widget, All Tasks filters and CSV, open-items-by-owner and aging reports | |
| 9 | **Review and Improvements** — the standing final phase | Backlog in `docs/review-and-improvements.md` worked, both themes, both locales, phone, docs and pt_BR level with what was built |

---

## 11. Open decisions (assumption stated, none blocking)

1. **Task visibility for `employee` users** — assumed: an employee sees tasks they own or are
   assigned to, plus everything on projects they can already see; no private flag in v1. If the owner
   wants an `is_internal` flag like documents have, it is one boolean and a scope.
2. **`documents.project_id` nullability** — a company-level meeting (no project) has no repository to
   file its PDF into. Assumed for v1: those PDFs stay as `file_uploads` on the meeting, and only
   project/job-site meetings file into the repository. Making `project_id` nullable to get a company
   folder is an additive migration whenever the owner wants it.
3. **Sub-task depth** — assumed 2 levels (task → sub-task). Deeper trees are unreadable on a phone
   and make the progress roll-up hard to explain.
4. **Time zone** — assumed the app's single configured zone; no per-attendee zone for remote meetings.
5. **External participants** — assumed name/company/e-mail on the attendee row only. No portal, no
   login, no ability for them to update a task; they receive the PDF. Same posture as vendors in the
   quotation module.
6. **Auto-creating the next meeting** — assumed a *button* at publish ("create the next meeting"),
   not a scheduler. A cadence field suggests the date; nothing fires on its own.

---

## 12. Build log

### Phase 0 — schema, models, module registration (2026-08-19)

Done, migrated locally, **not committed**.

- **14 migrations**, all additive, `2026_08_19_180000` … `180013`: `meeting_series`,
  `meeting_series_members`, `meeting_series_scopes`, `meetings`, `meeting_attendees`,
  `meeting_revisions`, `tasks`, `meeting_items`, `task_assignees`, `task_notes`,
  `task_activities`, `task_notifications`, `file_uploads`, and the `module_access` row.
  Self-referencing and circular foreign keys (`meetings.previous_meeting_id`,
  `tasks.parent_task_id`, `tasks.origin_item_id` ↔ `meeting_items.task_id`) are added in a
  second `Schema::table()` pass, the way `documents.current_version_id` already does it.
- **13 models**: `Task` (the rules — the open scope, the meeting-tracked/direct distinction,
  the progress roll-up and every guard), `Meeting` (numbering, publish/revise guards),
  `MeetingItem` (the computed 1 / 1.1 numbering, the publication snapshot), `MeetingSeries`,
  `MeetingSeriesMember`, `MeetingSeriesScope`, `MeetingAttendee`, `MeetingRevision`,
  `TaskNote`, `TaskActivity`, `TaskNotification`, `FileUpload`.
- **Inverse relations** added: `Project::tasks()` / `projectLevelTasks()`,
  `JobSite::tasks()`, `User::ownedTasks()` / `assignedTasks()`.
- **`config/modules.php`** gained `meetings`, declared before `projects` so `projects.*` does
  not claim `projects.tasks`.
- **pt_BR and en** gained the 33-key `_meetings` section for every `__()` string the models
  introduce. Eleven words (`Open`, `In Progress`, `Completed`, `Low`, `Normal`, `High`,
  `Urgent`, `Weekly`, `Monthly`, `Information`, `Due date changed`) already existed and were
  left untouched — see M1 in `docs/review-and-improvements.md`.

Verified in a rolled-back transaction: numbering (`OBRA-2026-001` → `002`, ad-hoc
`ATA-2026-001`), the agenda numbering (1 / 1.1), the publication snapshot, the
meeting-tracked vs direct split, the open/overdue/forUser scopes, the roll-up arithmetic
(50 % + completed, cancelled child excluded → 75 %), and every guard including the one that
matters most: **the owner cannot mark ready while a sub-task is open**.

Two defects found and fixed during that pass:

- `daysOverdue()` returned **-5** for a task five days late. `Carbon::today()->diffInDays($past)`
  is signed in Carbon 3; the operands were the wrong way round.
- Models had no in-memory attribute defaults, so a freshly instantiated `Meeting` answered
  `isDraft() === false` and `canPublish() === false` until it was reloaded. `$attributes`
  defaults added to `Task`, `Meeting`, `MeetingItem`, `MeetingSeries`, `MeetingAttendee`,
  `MeetingSeriesMember` and `FileUpload`.

**Deploy:** `php artisan migrate`.

### Phase 0b — the shared upload layer (2026-08-19)

Done and verified against the live R2 bucket, **not committed**. The documents module's
upload machinery is now shared rather than copied.

- **`App\Contracts\StoredFile`** — the contract for "a row that stands for one object in
  storage": `isPending()`, `isAvailable()`, `isMultipart()`, plus the three status constants
  and the columns an implementation must carry. Implemented by `DocumentVersion` and
  `FileUpload`.
- **`DocumentStorageService` now works on `StoredFile`**, not on `DocumentVersion`.
  `completeUpload`, `storeLocalUpload`, `abortUpload`, `headObject`, `temporaryUrl`,
  `copyObject` and `deleteObject` had their parameter types widened; the bodies are unchanged
  apart from two status constants now read from the contract. `completeUpload()` also takes an
  optional per-file ceiling, so a target with a smaller cap is enforced at the only point where
  the real size is known.
- **`planUpload()` extracted from `beginUpload()`** — the presign / single vs multipart / part
  size decision, which is now the one copy anything uploads through. `beginUpload()` keeps its
  exact signature and return shape (`version_id` included) and simply delegates.
- **`FileUploadService`** — the only new decisions: which targets exist (`task`, `task_note`,
  `meeting`), who may upload to each, the server-generated object key, and the 100 MB cap from
  the new `config/tasks.php`. All storage work is delegated.
- **`FileUploadController`** at `uploads/{init,parts,complete,abort}` — the same handshake as
  the document endpoints, with the target checked before anything is created and every
  subsequent call checked against the person who started the upload.
- **The Alpine uploader is now one factory** registered under two names: `documentUploader`
  (the repository, whose payload and callback are untouched) and `fileUploader` (everything
  else, which passes `config.fields`, `completedMethod` and `completedKey`). The transport —
  presigned PUT or multipart with progress, retries, cancellation and abort — is shared.
- **`file_uploads` gained `checksum`** (migration `180014`), which the shared
  `completeUpload()` records.

Verified end to end against the real bucket: `begin()` → presigned `PUT` (HTTP 200) →
`complete()` with `HeadObject` verification → the row available at the true size with the ETag
stored → a presigned download whose bytes matched what went up → `abort()` leaving no row.
The repository side was re-checked in the same pass: identical plan keys for single and
multipart, a real multipart opened, a second part batch signed and the upload aborted, and
`/projects/{id}/documents` rendering **HTTP 200** with `documentUploader(` in the markup. The
bucket was left with no test objects and no open multipart uploads.

Three defects found and fixed during that pass:

- **`checksum` was silently discarded.** `FileUpload` had the column but not the fillable
  entry, so `update(['checksum' => …])` dropped it without error.
- **The orphan sweep would have aborted live uploads.**
  `DocumentStorageService::abortOrphanedMultipartUploads()` lists what the bucket holds and
  aborts anything the application does not recognise — but it only knew `document_versions`.
  A task's in-flight multipart older than the cutoff would have been aborted underneath it.
  It now checks both tables.
- **Nothing pruned unfinished attachment uploads**, which R2 bills.
  `FileUploadService::pruneStaleUploads()` added and called from the existing hourly
  `documents:prune-uploads` command, which now reports both sweeps.

**Deferred to phase 1:** the `<x-ui.file-uploader>` Blade wrapper. The Alpine side is shared
now; the markup is worth designing with the screen that uses it rather than guessing at it.

### Phase 1 — the task core (2026-08-19)

Done, exercised end to end, **not committed**. A task can now be raised, worked and closed
without any meeting existing — which is the point of building this half first.

**`TaskService`** — every change a task can undergo, in one place, because tasks are moved from
the task screens, from a meeting being run, and later from the scheduled jobs, and "only the
owner may say it is ready" has to mean the same thing in all three. Create, update, the status
machine (ready → confirm → reopen, block/unblock, cancel), progress, assignees and notes. Every
call writes a `task_activities` row; every transition refreshes the parent's roll-up.

**`ManagesTasks`** (Livewire concern) — the form, the detail view and the actions, shared from
the start so the project and job-site pages (phase 2) and the meeting screens (phase 5) get the
same behaviour rather than a copy of it. It carries screen state only; it never decides
anything, and it reports refusals to the user instead of throwing.

**`MyTasks`** at `/tasks/mine` — three tabs (I own / assigned to me / I raised), each with a
live count, and rows grouped into **overdue, awaiting confirmation, due this week, later, no
due date, closed** rather than sorted by a date column. Four figures at the top that ignore the
filters. Filters for search, status, priority and project, plus *show closed*. Distinct empty
states for "nothing on your plate", "not helping with anything" and "nothing matches these
filters".

**The task detail** — a full-page modal that shows everything the record knows: progress with
the roll-up arithmetic printed (`(50 + 100) ÷ 2 = 75%`), description, sub-tasks with their own
bars, the notes timeline with meeting badges and progress snapshots, files, every meeting that
discussed it, the full activity log, and the audit facts. The action bar offers only what this
person may actually do, and says who can do the rest ("Only Ana can mark this ready").

**Files** — `<x-ui.file-uploader>`, the Blade wrapper deferred from phase 0, on the task and on
**each note**: the plan promised note attachments, so the screen offers them rather than the
model merely supporting them. Both go up through the shared presigned pipeline.

**Reason prompt** — reopen, block and cancel share one dialog, because none of them may happen
without a reason and three dialogs would have drifted.

Also: the `meetings` sidebar entry (gated by the module toggle), the route, and **139 keys**
added to `pt_BR.json` and `en.json`, including the five pluralised counts.

Verified, each against the running application rather than by reading:

- The form creates, validates (empty title, due date before start date), and edits.
- Progress 40% moves an open task to In Progress; 100% does **not** close it.
- Ready → Confirm → Reopen (refused without a reason) → the whole trail in the activity log.
- A sub-task inherits its parent's scope, and the parent switches to the derived percentage.
- **Marking ready with an open sub-task is refused** — and the refusal reaches the screen.
- A non-owner sees no Ready button, sees "Only Other Person can mark this ready", and is still
  refused server-side if they call the action anyway.
- A file attached to a note lands under that note, not on the task, and still shows on the
  task's activity trail. Real bytes to R2, verified, then removed.
- The page renders **HTTP 200**; with the Meetings module switched off it returns **403**.
- All 226 Blade views compile and pass `php -l` (the sweep safety rule in
  `docs/translation-system.md`).
- pt_BR: every one of the 173 strings in the module resolves, checked by rendering the whole
  screen under `pt_BR`.

Nothing was left in the database or the bucket by any of it.

**Deploy:** nothing beyond phase 0's `php artisan migrate` — plus `npm run build` for the
uploader, already built locally.

### Phase 2 — the project and job-site task pages (2026-08-20)

Done, both levels in the same change, **not committed**.

Parity is structural here rather than remembered: both pages share one concern
(`ListsScopedTasks`) and one Blade partial (`livewire/task/partials/scoped-list.blade.php`), and
one card component (`<x-open-tasks-card>`) serves both overviews. The only difference between
the two pages is the one that is real — a project rolls up its job sites' tasks and can filter
by them; a job site shows only its own.

- **`/projects/{project}/tasks`** and **`/job-sites/{jobSite}/tasks`**, with the tab added to
  both navs and removed from both when the module is switched off.
- Filters: search, status, priority, owner, job site (project level only), **on/off the agenda**,
  and show closed. The agenda filter puts the module's central distinction on screen — a project
  manager can ask "what is open here that no meeting has ever discussed?" and get an answer.
- Four figures per location: open, overdue (with the oldest open item and its age), awaiting
  confirmation (with the next due date), and **not on any agenda**.
- The same buckets, rows, detail modal, form and reason prompt as My Tasks — one set of screens,
  reached from three places.
- **Both overview pages** gained an *Open Action Items* card: open, overdue and to-confirm
  counts, next due date, and the oldest open item with its age, linking through. It hides itself
  with the module.
- A task raised from the job-site page is fixed to that site — the scope selector is disabled
  and the server takes the context from the page, not the form.

Verified against the running application: the project page sees its own and its sites' tasks and
not a standalone one; the job-site page sees only its own; both job-site filters narrow
correctly; the agenda filter splits 3/0 as expected; a task raised from the site page lands on
that site; the detail modal opens from both; all four pages return **HTTP 200**; with the module
off the nav tab and the card disappear and the page returns **403**; 230 Blade views compile;
and both pages render in pt_BR.

One defect found and fixed in that pass: **"Next due" showed a date in the past**, because it
took the earliest due date of any open task — including overdue ones, which the count beside it
already covers. Both call sites now look forward only.

**Deploy:** unchanged.

### Phase 3 — meeting series, the meetings index, and creating a meeting (2026-08-20)

Done, **not committed**. The minutes side of the module is now visible; the agenda itself is
phase 4.

**Meeting series** (`/meeting-series`, admin and manager only) — the recurring meetings a company
holds, each with its code, cadence, usual location, **usual attendees** (system users or external
people with a name, company and e-mail) and the **projects and job sites it covers**. The form is
a full-page modal with both lists editable in place. A series that has held meetings cannot be
deleted, only deactivated: the minutes it issued are part of the record either way, and the
screen says so rather than failing.

**Meetings index** (`/meetings`) — company-level, because a meeting spans as many projects as
were on its agenda. Next meeting, drafts in preparation, published minutes and active series at
the top; filters for series, status, date range and a search over number, title and location.
Drafts offer Edit; anything published shows as locked, since a published minute is corrected
through a revision and not through the form.

**Meeting form** (`/meetings/create`, `/meetings/{meeting}/edit`) — the series does the typing:
choosing one fills the title, the location, the register and, when the series has met before, the
next date from its cadence. The register marks **present / absent / excused** per person, and
external guests can be added with the e-mail the minute will go to. The right rail shows what the
meeting will start from: the previous meeting in the series and **how many open action items will
carry forward** — the promise phase 4 has to keep.

The number is issued **inside the transaction that creates the meeting** (`SITE-2026-001`,
`SITE-2026-002`, ad-hoc `ATA-2026-…`), so two people creating a meeting at the same moment cannot
take the same one. The form shows the number it will get before saving, marked as reserved on
save.

The sidebar's single "My Tasks" link became a **Meetings group** — Minutes, My Tasks, and Meeting
Series (manager and above) — matching the rail-flyout pattern the other groups use.

Verified against the running application: the series form creates with members and scopes,
upper-cases the code, refuses a duplicate code and a blank attendee row, and refuses to delete a
series that has held meetings (with the reason on screen and the button withdrawn); choosing a
series fills five fields and the register; end-before-start and a blank attendee are refused;
the second meeting of a series gets `-002` and links back to the first; editing a draft keeps its
number; **a published meeting returns 403 from the form**; a non-manager gets 200 on the index but
**403 on create and on the series admin**; all three screens render in pt_BR; the module toggle
takes all four routes to 403 and back; 240 Blade views compile.

One defect found and fixed: **the first meeting of a series was dated a week out.** The cadence
suggests the date *after the last meeting*, and with no last meeting the suggestion was a guess
the user had to undo. It now only applies once the series has met.

**Deploy:** unchanged.

### Phase 4 — the agenda builder (2026-08-20)

Done, **not committed**. This is the screen the module was designed around, and the owner's
requirement now works end to end.

**`/meetings/{meeting}/agenda`**, three ways onto the agenda:

1. **Carry-forward**, proposed automatically and pre-ticked. Grouped by location, each row
   showing owner, due date with the days-late count in red, progress, status, **"open since
   WSX-2026-001 · 3 meetings"**, and the last note in quotes. Bulk shortcuts: all, only overdue,
   none. Whatever is left unticked stays open and is proposed again next time, and the screen
   says so.
2. **Add a location.** Choosing a project (and optionally a job site) pulls in **all of its open
   meeting-tracked tasks**. Underneath, per location, a collapsed drawer reads *"3 other open
   tasks here are not on the agenda"* — the direct ones, each with an explicit **Add to agenda**
   button. Nothing enters the minute unless somebody presses it.
3. **Raise an item** inline: information, decision or action. An **action item creates its task
   there and then** (owner and due date both required), and from that moment it carries forward
   on its own.

Sub-items nest one level under any line; the number (1, 1.1, 1.2) is computed from position, so
reordering costs one swap and no rewrite. Removing a line takes it **off the agenda only** — the
task stays open and comes back next time, and both the confirm text and the flash message say
exactly that.

**One deliberate departure from the plan:** reordering is up/down buttons rather than
drag-and-drop (recorded as M9). Buttons are keyboard-reachable, need no library, and do not
fight the scroll on a phone.

`MeetingAgendaService` holds the rules. The one worth naming: **carry-forward reads every earlier
meeting of the series, not only the last one.** An item skipped at one meeting must not vanish
because nobody mentioned it that week — which the test below proves.

Verified against the running application, in one continuous scenario across three meetings:

- Two action items raised at meeting 1 became owned, dated, meeting-tracked tasks.
- Meeting 2 proposed **exactly those two** and **not** a task raised on the project page.
- "Only overdue" narrowed the tick to one; carrying it created an item whose
  `carried_from_item_id` points at meeting 1, and the badge shows `WSX-2026-001`.
- Adding the project pulled in the tracked item and **left the never-discussed one off**, listed
  in the drawer instead; adding that one by hand put it on the agenda and made it tracked from
  then on.
- Reorder swapped two lines; a sub-item numbered 1.1; removing a line closed the numbering gap
  and left the task open.
- Meeting 3 **stopped proposing the completed task** and **still proposed the one skipped at
  meeting 2** — the behaviour the whole design rests on.
- The page renders HTTP 200 with the carry panel, the day-late count and the history line;
  it renders in pt_BR; a **published** meeting's agenda returns 403; a **non-manager** gets 403.

One defect found and fixed: **every agenda numbered its first item "2".** Positions are 0-based
because the displayed number is position + 1, but `nextPosition()` returned 1 for an empty
agenda. Sub-items showed 1.2 for the same reason.

**Deploy:** unchanged.

### Phase 5 — running the meeting, publishing, correcting (2026-08-20)

Done, **not committed**. The module now produces minutes.

**`/meetings/{meeting}`** is one screen for both states, deliberately: what the chair types during
the meeting is exactly what the record says afterwards, so nobody has to wonder whether a
separate "minute view" shows the same thing.

**While it is a draft** — discussion and decision boxes per item, saved on blur so nothing is
lost if the laptop closes; the attendance register with P/A/E per person; and the task worked in
front of everyone: progress buttons, **Confirm Done** for the chair, and a note box whose notes
are stamped with the meeting, so the task screen later reads "said at RUN-2026-001". An item that
was never reached is marked **not discussed** and rolls to the next meeting untouched. A right
rail counts items discussed, decisions recorded, tasks raised here, tasks closed here, and what
is overdue or waiting on the chair's word.

**Publishing** checks before it freezes: an empty agenda is refused, and **every action item must
have an owner and a due date** — the modal names the offending items and withholds the button
rather than failing on the press. On publish each item's `status_at_meeting` is snapshotted, and
the minute keeps saying what it said: a task later moved to 90% still shows **50%** on the
published record, with "since then it has moved on" beside it.

**Correcting** a published minute is admin-only and reason-first: the reason is captured *before*
anything unlocks, the before/after of every changed field is stored, and the document carries a
**Corrections** section showing the old text struck through above the new. Discarding a
correction puts the original text back in the database, not just on screen.

**Cancelling** keeps the number and the row, and says plainly that nothing on the agenda is
closed — those tasks stay open and will be proposed next time.

**The next meeting** is one button: it copies the register, links both ways, and arrives with
everything still open already on its agenda.

Verified in one continuous lifecycle: typed text saved; attendance corrected; progress moved from
the meeting; a note stamped with the meeting number; an item marked not discussed; **publish
refused twice** (empty agenda, then an unowned action item) with both messages on screen; publish
succeeding and snapshotting; the task moving to 90% while the minute held 50%; correction refused
without a reason, then recorded with before/after; the minute locked again afterwards; and the
follow-up meeting created with its register and carry-forward already applied.

Permissions proven by attempting the writes and reading the database back, not by reading the
code: an **employee cannot change summary, discussion or attendance on a draft or a published
meeting**; an **admin can on a draft**; and an **admin cannot on a published one** without going
through the correction flow.

One defect found and fixed: the index's **"Open" button rendered as "Em Aberto"** in pt_BR — it
was reusing the task-status key. It has its own key now.

**Deploy:** unchanged.

### Phase 5b — owner's changes to the run screen (2026-08-20)

Three changes asked for after walking the screen, **not committed**.

**1. The meeting notes are a real editor.** `<x-ui.tinymce-editor>`, the same component the
estimates, invoices and daily reports already use, so meeting notes can carry headings, bold and
lists rather than one flat paragraph.

**2. The agenda is an accordion.** Every line collapses to a header that still carries what the
chair needs to steer by — number, type, title, status, owner, due date, percentage, whether
anything has been written yet (a green tick), and the sub-item count. Opening a line reveals the
discussion and decision boxes, the task controls and its sub-items. **Expand all / Collapse all**
sit in the header. A draft opens with everything collapsed, because a meeting is taken one item
at a time; **a published minute opens with everything expanded**, because a record is read as a
document.

**3. The agenda can be added to from the meeting itself.** Things come up while a meeting runs,
so **Raise an Item** is now on the run screen, with sub-items raised from inside their parent and
lines removable in place. Carrying items forward and reordering stay on the builder, which is one
click away.

The item form was a copy in the builder; it is now the shared `RaisesAgendaItems` concern used by
both screens, so an action item raised at the meeting creates its task with the same rules as one
raised in the builder.

**A real defect found while testing this:** the notes are HTML now, and the app's existing way of
printing editor output — `strip_tags($html, '<p><br>…')` — **keeps the attributes of the tags it
allows**, so `<p onclick="alert(1)">` came through intact. That is stored XSS. Meeting notes now
go through a new `App\Support\RichText`, which parses the fragment, unwraps every tag outside a
narrow allowlist, drops **every** attribute except a scheme-checked `href`, marks external links
`noopener noreferrer`, and removes script/style/iframe/object/embed/form outright. It sanitises
both on save and on display, so anything stored before it existed is also cleaned. Verified
against inline handlers, `javascript:` hrefs, `<img onerror>`, `<svg onload>`, style and iframe
blocks — and against accented text, which the naive DOMDocument approach mangles.

The same weak pattern exists elsewhere in the app and is recorded as **M11** for the review phase.

**Deploy:** unchanged.

### Phase 5c — note boxes, inline notes, eye button (2026-08-20)

Reported by the owner while walking the run screen, **not committed**.

**A defect I introduced in 5b:** every item's "record a note" box was bound to one shared
property (`newNoteBody`), so typing against one item typed against all of them. Each item now
carries its own box, keyed by item id (`itemNote[$item->id]`), and only the box that was saved is
cleared. Pressing Enter in the box saves it. The task detail modal keeps its own composer and is
unaffected — checked, because the two now live on the same screen.

**The notes are readable from the meeting.** Each open item lists what has already been recorded
against its task — author, how long ago, the meeting badge when it was said in one, and the
progress it was at — with the four most recent shown and *"See all N notes"* for the rest. That
was the point of writing them: the next meeting reads them without opening anything.

**"Open task" is an eye button** (`<x-ui.icon-button icon="eye">`), on the item, on its sub-items,
and on a published minute, matching the icon the rest of the app uses for view.

Verified with three action items on one agenda: typing against item 1 left items 2 and 3 empty;
saving wrote one note to one task and cleared only that box; a second note on item 3 landed on the
right task; the notes render inline stamped with the meeting number; the wording "Open task" is
gone and the eye icon is present.

### Phase 5d — editing a line that is already on the agenda (2026-08-20)

**A gap the owner found, and a fair one:** the agenda builder let you add, reorder and remove
lines, but there was no way to change one that was already there. A meeting spends most of its
time doing exactly that — fixing a title, moving a date, handing work to somebody else — and the
only route was to open the task in its own modal.

Every line now carries an **edit** button, on the builder and on the run screen (both share
`RaisesAgendaItems`). The form opens inline under the line it belongs to.

What an edit writes, and where:

- **Title, owner, due date, location go to the task**, through `TaskService::update()`, so the
  change is logged on the task's history and every other screen sees it. The agenda line follows.
  Moving the owner or the date from the meeting is the normal way work gets reassigned, and the
  form says so.
- **Type and the line's own title** (when there is no task) go to the item.
- A line that carries a task **stays an action item** — the work exists whatever the minute calls
  the line — and the type selector is disabled with that reason on screen.

Guards unchanged: an action item still cannot lose its owner or its date, and a **published**
minute refuses the edit outright.

Verified: a typo'd action item was renamed, reassigned to another user, re-dated and moved to a job
site in one save — the task took all four, and `owner_changed` / `due_date_changed` appear on its
history; an information item was renamed and changed to a decision; clearing the owner was refused;
the same edit worked from the run screen; and after publishing, the attempt left the title
untouched.
