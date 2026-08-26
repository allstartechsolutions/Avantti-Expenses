# Agenda order — carrying items forward without losing the structure

**Raised by:** owner feedback on the meetings module, 26 Aug 2026.
**Complaint, verbatim in substance:** *the items moved to the next one are out of the order
and not on the same structure as the one before, so it gets a bit confusing — they are no
longer organized on the same order as the previous, they are organized by due date first.*

The complaint is correct, and this document records what was found, what was decided, and
what is being built. It supersedes nothing in `docs/meetings-module-plan.md`; it refines
§5.2 of it.

---

## 1. What the code did before this change

| Where | What it did |
|---|---|
| `MeetingAgendaService::carryForwardCandidates()` :59 | `orderByRaw('due_date is null, due_date asc')` — due date was the **only** ordering rule |
| `MeetingAgendaService::scopeCandidates()` :77 | same, for the add-a-location route |
| `MeetingAgenda::carryForwardByScope()` :79 | the picker **displayed** rows grouped by project › job site … |
| `MeetingAgenda::addSelectedCarry()` :187 | … but added them from the **ungrouped** due-date collection, so projects came out interleaved. The panel showed one order and produced another |
| `MeetingAgendaService::addTask()` :110 | created every carried line with `parent_id` null and `position = nextPosition($meeting, null)` — appended to the end of a flat list |

Three consequences:

1. **Last meeting's sequence was discarded.** Whatever order the chair had dragged the
   agenda into was replaced by due date.
2. **Nesting was flattened.** A line that was 2.1 last month became a top-level line
   somewhere else, detached from its parent even when the parent was carried into the same
   meeting.
3. **Final order was the click order.** Carry-forward, add-a-location and raise-new all
   appended to one flat list, so the agenda ended up in whatever sequence the chair happened
   to press things, with due date deciding within each press.

The irony worth recording: `addTask()` stores `carried_from_item_id` on the very next line
(:131), so the system already knew each item's previous position and previous parent. It was
used only for the "open since ATA-2026-009" badge, never for ordering.

There was, and is, **no section or heading concept**. "Structure" was: `position` order, one
level of parent/child, and a scope label printed as grey text on each row
(`agenda-item.blade.php:79`). The minute screen and the ata PDF both re-render that same flat
position list (`MeetingMinuteRenderer.php:50`), so whatever the agenda looked like, the
document reproduced it.

---

## 2. What was decided

Discussed with the owner 26 Aug 2026. Every point below is a decision, not a suggestion.

### 2.1 Ordering — grouped by location, last meeting's order inside

**Template meeting** = the most recent previous meeting of the series that has items
(cancelled ones excluded, as `previousMeetingIds()` already does). It defines both the group
order and the within-group order.

1. **Groups** — project, or project › job site, or *General* — are ordered by where they
   first appeared in the template meeting. A location not on the template appends after, in
   the order it was added.
2. **Inside a group**: items that were on the template keep the template's relative order,
   then items carried from *older* meetings that were skipped at the template (by their old
   position), then tasks that have never been on any agenda (by due date, as before).
3. **Written at add time, not sorted at render time.** Positions are computed and stored, so
   the agenda screen, the running minute and the ata PDF read one stored order rather than
   three copies of the same sort. The chair can still drag afterwards.
4. **Numbering stays continuous** 1…n across the whole agenda. Headers are visual only. No
   new item type, `MeetingItem::number()` untouched, and "item 7" in the PDF's action-item
   table still means the same line.

A one-off meeting with no `previous_meeting_id` has no template and falls back to the
previous behaviour inside each group. No regression there.

### 2.2 Nesting — re-nest under the carried parent

If a carried line's parent line is also being carried into this meeting, the child is
recreated under it, in its old order. If the parent's task is closed or the parent was left
unticked, the child is **promoted** to a top-level line and keeps the parent's old slot in
the group's root order, rather than scattering to the end.

### 2.3 Items that are not carried forward — end of their own group

A task pulled in by adding a project or job site, and an item raised fresh during the
meeting, are inserted at the **bottom of their location's block**, shifting the rows after
them. Grouping always holds. Previously both appended to the very end of the agenda, which
broke grouping the moment anything was raised for a project already listed above.

### 2.4 Past due first — optional, per series

Some chairs want the late work at the top. This is a property of the **meeting, not of the
person viewing** — a per-user preference would mean the chair, the secretary and the ata all
read differently.

- **Within each location block**, not as a separate section. Grouping holds exactly as in
  §2.1; the late rows lift to the top of their own block, keeping the template's order among
  themselves, and everything else follows below in the template's order.
- **Nesting holds**: a parent lifts with its children. A root row counts as late if its own
  task is overdue or any of its children is.
- Chosen by a setting on the **meeting series**, which sets the default for every agenda
  built from it, plus buttons on the builder to switch a draft either way.
- "Overdue" is `Task::isOverdue()` — open, dated, and due before today. Rows with no date and
  non-task lines (information, decision) keep their place below the lifted block.

### 2.5 Group and row movement

- Row up/down arrows **stop at the edge of a location block**. Previously they swapped with
  whatever root sibling was adjacent, which is meaningless today (there are no boundaries)
  but after this change would wedge a row into another project's run of positions and split
  the agenda into four headers where there were two. A row cannot change location by being
  moved — its location comes from its task.
- **Whole location blocks** get their own up/down control on the header.
- **Drag is scoped per block.** One Alpine `x-data` per group rather than one for the list.
- A **tidy action** re-sorts a draft into the canonical order of §2.1/§2.4. This is the
  escape hatch for the agendas already built wrong, and for a chair who has dragged
  themselves into a mess.

### 2.6 Headers reach the minute and the ata

Location headers render on the agenda builder, the running minute and the ata PDF. The
document people receive is where the complaint originated, so grouping that stops at the
builder would not have answered it. The PDF's action-item table stays flat — it is a task
list, not the agenda.

---

## 3. What the previous meeting must never do

The owner's second point, and the constraint the whole change is written against:

> changes on the new meeting agenda should not be added to the previous meeting

### 3.1 What already protects it — verified, not assumed

- **Separate rows.** `addTask()` creates a *new* `MeetingItem`. It never reuses last month's
  row; `carried_from_item_id` is a read-only pointer.
- **Its own text.** `title`, `discussion` and `decision` are columns on the item, and all
  three views render `$item->title` — never `$item->task->title`
  (`minute-item.blade.php:43`, `pdf/meeting-minute.blade.php:114`). Renaming a task this
  month does not rewrite last month's ata.
- **Frozen figures.** `MeetingService::publish()` :74 writes `status_at_meeting` onto every
  item, and the minute and PDF read the snapshot first, falling back to the live task only
  when there is none (`:107`, `:137-139`, `:165-183`).
- **Its own location.** `project_id` / `job_site_id` are stored per item, so re-scoping a
  task leaves the old line under the old project.
- **Locked.** `assertBuildable()` aborts any agenda mutation on a published meeting.

### 3.2 What this change adds, and its guards

`positionForScope()`, `regroup()` and the overdue sort perform **bulk position updates** —
the first bulk writes this module has on that column. A missing clause would silently
renumber another meeting's agenda.

- Every position write is filtered on `meeting_id` **and** `parent_id`, never on `position`
  alone. The existing `reorder()` :227 is already this shape and keeps it.
- `regroup()` and the sort actions sit behind `assertBuildable()` like every other mutation.
- `templateMeeting()` and `orderKeys()` are strictly read-only.
- **A test asserts it directly:** build two meetings in one series, carry items into the
  second, reorder / regroup / sort it, then assert every row of the first is byte-identical —
  positions, parents, titles, snapshot.

---

## 4. The snapshot question — closed at Level 1

`snapshotTask()`'s own comment says it records "how the task stood when the minute was
**published**". A minute written up two weeks late therefore records figures from publication
day and stamps them on a meeting dated the 12th. An earlier meeting still in draft has no
snapshot at all, so its screen follows the live tasks — and on publish it keeps whatever they
had become.

Two honest answers existed. **Level 1 was chosen**: keep the snapshot where it is and stop
the app pretending otherwise.

1. A **banner on the agenda builder** when an earlier meeting of the series is still a draft,
   linking to it: *"ATA-2026-009 (12 Aug) is still a draft. Publish it before building this
   agenda."*
2. A **warning at publish** when a later meeting of the series already has items — the point
   at which a late snapshot becomes a permanent record.
3. The ata **states the as-at date of its figures**: *"Figures as at publication, 26 Aug
   2026."*

No schema change, no reconstruction, no write to a past meeting.

**Level 2, considered and not chosen for now:** reconstruct each task's owner / due date /
progress / status as at the meeting date by walking `TaskActivity` backwards. The log has the
actions needed (`status_changed`, `progress_changed`, `due_date_changed`, `owner_changed`)
with `old_value` / `new_value` and timestamps. It was declined because if any path mutates a
task without writing an activity row, reconstruction returns a confidently wrong number,
which is worse than today's honest fallback — and confirming that would have meant auditing
every write path in `TaskService` before the ordering work could start. Revisit only with
that audit in hand.

---

## 5. Build order

| Step | What | Done when |
|---|---|---|
| 1 ✅ | Ordering engine: `templateMeeting()`, `orderKeys()`, `sortCandidates()`, `openPositions()`, `carryForward()` batch with re-nesting and promotion; `agenda_order` on the series | **Done 26 Aug 2026** — `tests/Feature/Meetings/AgendaOrderTest.php`, 12 tests |
| 2 ✅ | Group-aware positions: `move()` boundary guard + `canMove()`, `moveGroup()`, `regroup()`, `applyOrder()`; `reorder()` rewritten to touch only its own block | **Done 26 Aug 2026** — 11 further tests in the same file |
| 3 ✅ | Agenda builder view: headings with count and move controls, per-block drag, order toolbar, series setting | **Done 26 Aug 2026** — 4 further tests; pt_BR added in the same change |
| 4 ✅ | Minute screen and ata PDF headings; Level 1 snapshot banner, publish warning and as-at line; pt_BR strings in the same change | **Done 26 Aug 2026** — 5 further tests |
| 5 | Review pass: both themes, both locales, phone, long project names, empty and partial states; the cross-meeting immutability test | Per the module review standard |

### Notes from step 1

- **Three carry paths existed, not two.** `MeetingService::scheduleFollowUp()` :167 also looped
  `addTask()` one task at a time when creating the next meeting, so a follow-up scheduled from
  the minute lost its nesting the same way the builder did. It now calls `carryForward()`.
- **`openPositions()` is the only bulk position write.** Filtered on `meeting_id` and
  `whereNull('parent_id')`, never on position alone, and covered by two tests that assert the
  previous meeting comes out byte-identical.
- The ranking reads the previous meeting's positions, which no `ORDER BY` can see, so the sort
  happens in PHP on the fetched collection rather than in SQL. The candidate lists were already
  fully materialised, so this costs nothing extra.

### Notes from step 2

- **`reorder()` is now narrower and safer than it was.** It rewrites only the slots the dragged
  rows already occupy, so a row left out of the payload keeps its exact position instead of
  being appended to the end, and ids belonging to another block — or another meeting — are
  dropped rather than honoured.
- **Two tidy actions, not one.** `regroup()` brings a location's rows back together and leaves
  the chair's order inside each one alone; `applyOrder()` is the full canonical re-sort. Wiping
  out somebody's dragging is not what "my agenda is interleaved" asks for.
- **A pre-existing cross-meeting hole was closed on the way past.** `openItemForm($parentId)` in
  `RaisesAgendaItems` read the parent with an unscoped `MeetingItem::find()`, and `addItem()`
  passed `parent_id` straight through to the insert. A crafted id could hang a line of this
  agenda off a line of a **previous, even published** meeting — which would have appeared as a
  new sub-item on that meeting's minute, the exact thing §3 forbids. The lookup is now scoped to
  the meeting and `assertOwnParent()` re-checks it in the service, which also closes a third
  nesting level the module does not have.
- `MeetingShow.php:405` still reads `MeetingItem::find()` unscoped, but only to print a line
  number in a revision change-log. Left as it is; noted here rather than fixed silently.

### Notes from step 3

- **The location no longer repeats on every row.** `agenda-item.blade.php` printed
  `getScopeLabel()` in each line's meta row; with a heading directly above the block that was the
  same words twice. Removed there, kept everywhere the agenda is not grouped.
- **The tidy button only appears when it would do something.** `itemBlocks` cuts the agenda into
  *runs* of a location rather than grouping it, so an interleaved agenda shows honestly as
  interleaved and `isInterleaved` decides whether to offer "Group by location".
- **Row arrows are disabled at a block edge** rather than silently doing nothing, and their
  tooltip says where the whole-location control is.
- `agenda_order` is on the series form as **Agenda Order**, through the single `updateOrCreate`,
  so it saves on both create and edit.

### Notes from step 4

- **One place cuts the blocks.** `blocksFrom()` takes lines that are already loaded, so the
  builder, the running minute and the ata all group the same way without three copies of the rule
  or three extra queries.
- **The as-at line only appears when it says something.** A minute published on the day of its
  own meeting needs no explanation, so the line is printed only when publication and meeting date
  differ.
- **The out-of-order warning does not block.** Writing a minute up late is a fact of life; the
  publish dialog names the later meetings and says the figures will be from after this meeting's
  own date, and the chair decides.
- **`MeetingMinuteRenderer::html()` was added.** Asserting on the ata's wording means reading its
  HTML rather than the compressed bytes of a rendered PDF. It is what dompdf is handed anyway.
- The location is no longer repeated on each PDF line either, for the same reason as on screen —
  the heading directly above says it.

## 6. Behaviour changes to announce

Both are narrowings of something that works today, and are recorded here rather than shipped
quietly:

- **Row up/down arrows now stop at the edge of a location block.** Pressing up on the first
  row of Project B no longer walks it into Project A. Whole blocks move with the header
  control instead. The buttons get a disabled state at block edges so it does not read as
  broken.
- **Publishing a minute out of date order now warns.** It did not before.
