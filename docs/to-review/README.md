# To Review

Things found while building something else, written down properly instead of being fixed on
the spot — and instead of being lost.

This is not a bug list and not a backlog of ideas. Everything in here has the same shape:

- it was **found and verified**, with the evidence recorded;
- it was **not acted on**, and the note says why;
- it needs **a decision from the owner**, or a piece of information nobody had at the time
  (usually something only production can answer).

The difference between this folder and `docs/review-and-improvements.md`: that file is the
per-module review backlog, worked during a module's review phase, and its rows are mostly
"do this when you get to it". A file in here is a **question waiting on an answer**, and the
answer changes what gets built. Neither is an archive; both are meant to be emptied.

## How to use one of these

Every file is written to be picked up cold, months later, by somebody who was not in the room.
Each one carries:

1. **What is wrong**, demonstrated — not asserted.
2. **How to check whether it still matters**, usually a query or two to run against
   production, because most of these turn on facts only the live install has.
3. **The options**, with what each one costs and what it breaks.
4. **A recommendation**, so the decision starts from a position rather than a blank page.

When one is settled: act on it, move the outcome into the module's own doc or
`review-and-improvements.md`, and delete the file. A note that has been decided and left here
is worse than no note, because the next person has to work out whether it is still true.

## Naming

`YYYY-MM-DD-short-subject.md` — the date it was found, so the oldest unanswered question is
obvious at a glance.

## Open

| Found | Subject | Waiting on |
|---|---|---|
| 2026-08-26 | [Task assignment ignores confinement](./2026-08-26-task-assignment-confinement-gap.md) | Whether anything in production is actually confined — two SQL queries |
