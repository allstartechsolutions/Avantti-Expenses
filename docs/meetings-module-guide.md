# Meetings, Minutes and Tasks — How to Use It

A guide for the people who will run the meetings, not for whoever maintains the code.
Written 2026-08-20, revised 2026-08-26 for the agenda ordering work
(`docs/meetings-agenda-order-plan.md`). Screenshots are from a real install with demo data — the
agenda screenshots predate the location headings described in §5d.

> **A note on wording.** In the English interface this install calls a *project* a **"Job Site"**
> (that renaming lives in `lang/en.json`). So where this guide says *project*, the screen says
> *Job Site*. In pt_BR the screens say **Projeto** and **Local**. One place still shows the word
> twice for two different things — the *Add a Location* panel — and that is a known wording issue,
> not two identical fields.

---

## 1. The one thing to understand first

The module has four words that sound similar. They are four **stages of the same thing**, not four
different features:

| Word | What it is | When it exists |
|---|---|---|
| **Series** | A meeting that repeats — "Weekly Site Meeting". Set up once. | Forever |
| **Meeting** | One date. Created inside a series. | From when you create it |
| **Agenda** | The list of what that meeting will cover. | While the meeting is a **draft** |
| **Minute (ata)** | The record of what was said and decided at it. | After you press **Publish** |

**The agenda and the minute are the same meeting at two different moments.** You do not create a
minute. You build an agenda, run the meeting on top of it, and publishing turns it into the minute.

And underneath all four:

| Word | What it is |
|---|---|
| **Task** | A piece of work with one owner and a date. It **outlives the meeting** and keeps coming back until it is closed. |

That last line is the whole point of the module. An action item raised in June is not copied into
July's minute — it is the *same task*, appearing again, with its progress and its history intact.

---

## 2. The flow, end to end

```
 Series  ──▶  Meeting  ──▶  Agenda  ──▶  Run it  ──▶  Publish  ──▶  Minute
 (once)      (each week)   (before)     (during)      (at the end)   (the record)
                                │                                        │
                                └───── tasks are raised and worked ──────┘
                                              │
                                    still open? ──▶ proposed on the next agenda
```

---

## 3. One-time setup: create the series

**Meetings → Meeting Series → New Series.** Manager or admin only.

![Meeting series](images/meetings/02-meeting-series.png)

You are telling the system four things:

1. **The code** (`OBRA`) — it becomes part of every minute number this series ever issues:
   `OBRA-2026-001`, `OBRA-2026-002`.
2. **The usual attendees** — copied onto every new meeting so nobody retypes the register each
   week. People outside the company are added by name, company and e-mail; they never need a login.
3. **The projects it covers** — a new meeting starts pointing at these, which is how their open
   items appear on the agenda without anybody asking.
4. **Agenda Order** — how agendas built from this series are arranged. Every agenda groups its
   items by project and job site; this setting decides what happens *inside* each group when items
   are carried forward:
   - **Last meeting's order** (the default) — items come back in the order you put them in last
     time, so this week's agenda reads like last week's.
   - **Past due first** — the late work rises to the top of its own group, and everything else
     follows in last meeting's order.

   It is a default, not a lock: either order can be applied to a single agenda from the builder.

> **Why a series matters.** Carry-forward is read *within a series*. Without one, the open items of
> your site meeting would mix with those of the directors' meeting. A one-off meeting is allowed,
> but it carries nothing forward on its own.

A series that has already held meetings **cannot be deleted**, only deactivated — its minutes are
part of the record.

---

## 4. Before the meeting: create it

**Meetings → New Meeting.**

![Meeting form](images/meetings/03-meeting-form.png)

Choose the series and the form fills itself: title, location, the whole attendance register, and —
once the series has met before — the next date from its cadence. Set the chair (the person who will
confirm finished work) and, if you want, a secretary.

The number shown at the top is the number this meeting *will* get; it is reserved the moment you
save, so two people creating a meeting at once cannot take the same one.

The right-hand panel tells you what this meeting will start from: the previous meeting in the
series and **how many open action items will carry forward**.

---

## 5. Before the meeting: build the agenda

**On the meeting row, press Agenda.** This is where the module earns its keep.

![Agenda builder](images/meetings/04-agenda-builder.png)

There are exactly **three ways** something gets onto an agenda.

### a. Carry-forward — automatic

The right-hand panel lists everything **still open from earlier meetings of this series**, already
ticked, **in the order it will land on the agenda**. Each row shows the owner, the due date (red,
with the days late), the progress, and **"open since OBRA-2026-001 · 3 meetings"** — how long it
has been dragging. The last note written on it is quoted underneath.

- **All / Only overdue / None** are shortcuts. "Only overdue" is the usual short agenda.
- Press **Carry N items forward** and they join the agenda.
- **Anything left unticked stays open.** It is not closed and not lost — it is simply not on
  *this* agenda, and it will be proposed again next time.

**What comes back, comes back as it was.** Items return grouped by project and job site, and inside
each group in the order you had them last time — not sorted by date. If you spent time dragging
last week's agenda into a sensible order, that order is what you get this week.

**A main item and its sub-items travel together.** If you raised *"Safety on site"* and hung four
action items under it, the whole group comes across whole: the main line still at the top, the four
still beneath it. That holds even when the main line is not work in its own right — a plain
*Information* heading with no owner and no date — and even when its own task has been completed
while the work under it has not. In the panel such a heading has no tick of its own and is labelled
**comes with the items below**.

Only the **still-open** sub-items come with it. Finished work stays in the minute that recorded it.

### b. Add a location — brings its open items with it

Pick a project (and optionally one job site) and press **Add to the Agenda**. Every open task at
that location **that meetings already track** comes with it.

Underneath, per location, you will see a line like *"4 other open tasks here are not on the
agenda"*. Those are tasks somebody raised on a project page that **no meeting has ever discussed**.
They stay off the minute unless you deliberately press **Add** on one — and from that moment they
carry forward like everything else.

> **This is the rule that surprises people.** A task raised on a project page is somebody's own
> work. It does not appear in your minutes unless you put it there. The drawer exists so you can
> *see* that it exists without it filling up the record.

One thing to watch when you add one: a task raised on a project page **does not have to have a due
date**, but an action item on a minute does. A task added from the drawer without one is marked
**no date** on the agenda. Open it and give it a date — until you do, the line cannot be saved at
all, and the minute cannot be published.

### c. Raise an item — something new

**Raise an Item** adds a line: *Information*, *Decision* or *Action Item*.

An **Action Item** creates a task then and there, and demands an **owner and a due date**. From
that moment it is tracked and will carry forward on its own.

Lines can be reordered with the arrows, given sub-items with **+**, and taken off with **×**.
Taking a line off the agenda **never closes its task**.

### d. Starting over

**Clear the agenda** on the Order line removes every line at once. Like taking off a single line, it
**closes nothing** — every task stays open and is proposed again immediately, so rebuilding a badly
built agenda is one press rather than twenty.

One consequence worth knowing: a task whose *only* appearance on any agenda was the one you cleared
stops being something meetings track. It stays open on its project, but it will no longer be
proposed on its own — you would add it again from the *"other open tasks here are not on the
agenda"* drawer.

### e. How the agenda is arranged

The agenda is cut into **location blocks** — one heading per project, and a separate heading for
each job site. The heading carries the number of lines under it and a pair of arrows that move the
**whole location** up or down.

- **The row arrows stop at the edge of a block.** Pressing *up* on the first line of a project does
  nothing, and the button greys out to say so. A line cannot change project by being moved — its
  location comes from its task — so to move a project earlier you move its heading, not its rows.
- **Dragging works inside a block.** Drag a line to reorder it among the others of its own location.
- **Order** above the list applies an arrangement to *this* agenda: **Last meeting's order** or
  **Past due first**, whichever you want this week regardless of what the series is set to.
- **Group by location** appears only when a location has ended up split across the agenda. It
  brings each location's lines back together and leaves your order inside each one alone.

New lines land at the end of **their own location's block**, not at the bottom of the page — so
raising something for a project listed near the top puts it with that project.

---

## 6. During the meeting: run it

**Press Run the Meeting** (or open the meeting from the list).

![Running the meeting](images/meetings/05-run-the-meeting.png)

The agenda is an **accordion**: each line is collapsed to a header showing who owns it, when it is
due, the percentage, and a green tick once something has been written. **Open one line at a time**
as you take it, or use *Expand all*.

Inside an open item you can:

- write the **Discussion** — what was said;
- write the **Decision** — what was agreed (it prints in a box on the minute);
- move the task's **progress** with the 0/25/50/75/100 buttons;
- **Confirm Done** if you are the chair and the owner has marked it ready;
- **record a note** against that task — it is stamped with this meeting, so the task screen later
  reads "said at OBRA-2026-001";
- read the **notes already recorded** on that task, without leaving the meeting;
- press the **eye** to open the full task.

Also on this screen:

- **Notes** at the top is a rich-text box for anything about the meeting as a whole.
- **Attendance** on the right: mark each person **P / A / E** (present, absent, excused) as the
  room fills.
- **Where It Stands** counts items discussed, decisions recorded, tasks raised here and closed
  here, and warns you about what is overdue or waiting on your word.
- **Raise an Item** works here too — things come up mid-meeting.
- An item nobody reached: **Mark as not discussed**. It rolls to the next meeting untouched.

Everything saves as you type. Nothing needs a "save" press except the Notes box.

---

## 7. At the end: publish

**Publish Minute.** The system checks two things first and will refuse:

- the agenda is not empty;
- **every action item has an owner and a due date** — the dialog names the offending lines.

Publishing does four things:

1. **Locks the minute.** It stops being editable.
2. **Photographs every task** as it stands today. If a task is at 50% now and reaches 90% next
   week, this minute still says 50% — with *"since then it has moved on"* beside it.
3. Records who published it and when.
4. Lets you set the **next meeting date**.

> **Publish in order, and publish promptly.** "As it stands today" means the day you *publish*, not
> the day of the meeting. Write a minute up a fortnight late and it records the figures of the day
> you typed it — the ata then says *"Figures as at publication, 26 Aug 2026"* so nobody is misled,
> but they are still late figures. Two things help:
>
> - Building next week's agenda warns you, by name, if an earlier minute of the series has not
>   gone out yet. Until it is published its figures follow the live tasks, so work you move on
>   from the new agenda changes what the old draft shows — and what it keeps when you finally
>   publish it.
> - The publish dialog warns you if a later meeting of the series already has an agenda. It does
>   not stop you; writing a minute up late is a fact of life. It just says so out loud.

![The published minute](images/meetings/06-published-minute.png)

The published minute opens with every item expanded, because a record is read as a document. It
carries the **same location headings** the agenda was built under, and so does the ata PDF — the
document reads the way the meeting was run.

### Deleting a meeting

**A published minute can never be deleted.** It has been frozen, filed into the project repository
and e-mailed to every attendee — removing it would leave the system disagreeing with the document
people are holding. A published minute that is wrong is **corrected**; a meeting that did not happen
is **cancelled**, which keeps it in the record with its reason.

**Delete** is offered only on a meeting that never became a record — a draft, or one already
cancelled — and only to someone holding the *Delete* grant on Meetings. It is for the one case the
other two do not cover: a meeting created by mistake.

What it does:

- the meeting and its agenda lines go;
- **the tasks stay open**, including anything raised at that meeting, and appear at the next one;
- the meeting before and the meeting after are joined to each other, so the chain still reads;
- its number is never reissued.

It is not an undo. Nothing in the interface restores a deleted meeting.

### Creating the next meeting

**Create the Next Meeting** builds the follow-up as a draft, copies the register, links the two
meetings both ways, and **arrives with everything still open already on its agenda**. You are back
at step 5 with most of the work done.

### If the minute is wrong

**Correct the Record** — admin only. You must type *why* **before** anything unlocks. Your changes
are saved with the old text kept alongside, and the minute grows a **Corrections** section showing
what it used to say. Nothing changes silently.

---

## 8. Tasks, away from meetings

Tasks are not only a meetings thing.

**Meetings → My Tasks** is what one person is on the hook for, in three tabs — **I own**,
**Assigned to me**, **I raised** — grouped by overdue, awaiting confirmation, due this week, later.

![My Tasks](images/meetings/07-my-tasks.png)

Every project and job site also has a **Tasks** page, and both overview pages carry an **Open
Action Items** card.

![Project tasks](images/meetings/09-project-tasks.png)

The filter worth knowing on those pages is **"On and off the agenda"**: it answers *"what is open
here that no meeting has ever discussed?"*

### The task itself

![Task detail](images/meetings/08-task-detail.png)

Everything the record knows: progress (with the arithmetic printed when it comes from sub-tasks),
description, sub-tasks, the notes timeline, files, **every meeting that discussed it**, and the
full activity log.

---

## 9. The rules that catch people out

| Rule | Why |
|---|---|
| **Only the owner can mark a task Ready.** Not a manager, not an admin. | It is a statement about their own work. An admin who must force it changes the owner first, which is logged. |
| **Ready is not Done.** The chair (or an admin/manager) confirms it. | Items get closed in front of everyone, usually at the meeting. |
| **100% does not close a task.** | Reaching 100% is progress; declaring it finished is the owner's call. |
| **A task with sub-tasks has no percentage of its own** — it is the average of its children, computed by the system. | So the number on screen is never a guess somebody typed. |
| **You cannot mark a task ready while a sub-task is open.** | |
| **Removing a line from an agenda does not close its task.** | It means "not discussing this today". |
| **Cancelling a meeting closes nothing.** | Its items stay open and appear at the next one. |
| **Tasks raised outside a meeting never reach an agenda by themselves.** | The minute is what management committed to, not everyone's to-do list. |
| **Overdue counts even when Ready.** | Otherwise a task parks in "ready" forever and the report looks healthy. |
| **A line cannot be moved into another project.** The row arrows stop at the edge of its location block. | A line's location comes from its task, not from where it sits. Move the whole location from its heading instead. |
| **Closing a main item does not dissolve the group under it.** The main line still comes across, marked as done, with the open work beneath it. | The shape you gave the agenda is part of the record. |
| **A minute's figures are those of the day it was published**, not the day of the meeting. | Publish promptly and in order; the ata says which day it is quoting when the two differ. |
| **A published minute cannot be deleted.** Correct it, or cancel the meeting. | It has already been filed and mailed. Deleting it would make the system disagree with the copy in people's inboxes. |
| **Deleting a meeting closes nothing**, exactly like cancelling. | The work exists whether or not the meeting that raised it does. |

---

## 10. Who can do what

| Action | Who |
|---|---|
| See meetings and minutes | anyone signed in |
| Create a task, add notes and files, move progress | anyone signed in |
| **Mark a task Ready** | **its owner, and nobody else** |
| Confirm completion / reopen | the chair of the meeting it came from, admin, manager |
| Create meetings, build agendas, run and publish | admin, manager (publishing also the chair) |
| Manage series | admin, manager |
| Correct a published minute | **admin only**, with a reason |

The whole module can be switched off for an install in **System Settings → Modules**; the sidebar
entry, the project tabs and the overview cards all disappear with it.

---

## 11. If something looks wrong

| What you see | What it means |
|---|---|
| An open item did not appear on the new agenda | It has never been discussed in a meeting of *this* series. Add its location, then use the "not on the agenda" drawer. |
| "Publish" is greyed out or refuses | An action item has no owner or no date — the dialog names it. |
| An agenda line will not save | It is an action item with no owner or no date — the reason is shown at the top of the form. Fill both in and it saves. |
| An agenda line shows **no date** | It came from a task raised outside meetings, where the date is optional. Give it one. |
| Editing a line does nothing | Its task is closed. Title, owner and date belong to the task, so reopen it first — the form now says so. |
| The minute shows an old percentage | That is deliberate. It shows what was true at that meeting. |
| An item cannot be marked ready | It has open sub-tasks, or you are not its owner. The screen says which. |
| A published minute cannot be edited | Use **Correct the Record** (admin). |
| Two dropdowns both say "Job Site" | Known wording issue — the first is the project, the second is the job site within it. |

---

## 12. What is not built yet

Corrected 2026-08-26. Since this guide was written the minute PDF, the e-mail to attendees, the
overdue notice and the weekly digest have all shipped — this section said otherwise and was wrong.

Built and in use:

- the **ata PDF** (view or download from the published minute) and the **e-mail to attendees** sent
  when a minute is published;
- **overdue notices** (`NotifyOverdueTasks`) and the **weekly digest** (`SendWeeklyTaskDigest`).

Still to come, from `docs/meetings-module-plan.md` phase 8:

- a **dashboard widget**, an **all-tasks list** across projects, and the meeting reports.

`MyTasks` covers one person's own list in the meantime.
