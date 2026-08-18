# Code review — income distribution + job site income page (2026-08-18)

Reviewed: commit `a2c8639` (the feature) plus the working tree at the time. Five defects
found and fixed in `c9bf382`; four items were judged and deliberately left alone.

The automated review agent failed twice on transient API 529s, so this was a manual pass:
reading the whole diff, then exercising each suspect path with `Livewire::test` inside a
rolled-back transaction.

---

## Fixed

### 1. Unhandled `DomainException` → HTTP 500
`ProjectIncome::saveIncome()` lost its try/catch when the split moved into the form.
`distributionRows` is a **public Livewire property**, so a client can put a `job_site_id`
from another project into it. `syncDistributions()` correctly refused — and the exception
went straight to a 500.

Now caught and reported as a form error, with the transaction rolled back. Verified: no
income record and no orphan distribution row are written.

### 2. Client-injected grid rows crashed the detail view
Binding to `distributionRows.99.amount` (an index the server never built) created a row with
no `job_site_id` / `job_site_name`. The blade then died on `Undefined array key
"job_site_name"`, and `distributionTotal()` would have counted the junk row.

Livewire writes the property *before* the `updated` hook runs, so a guard inside the hook is
not enough on its own. Fixed with an `isBuiltRow()` check used by **every** path that walks
the rows — the update hook (which now unsets the bad index), `assignRemainder`,
`splitEvenly`, `toggleAllSites`, `clearAllShares`, `collectShares`, `distributionTotal`,
`selectedSiteCount`, `updatedIncomeAmount`, and `visibleDistributionRows` (so nothing
malformed can reach the view at all).

### 3. Abandoned form state leaked into the next record
Escape and backdrop clicks close the modal **without telling the server**, so staged uploads
and stale validation errors survived. A file picked in a cancelled *Add* could be attached to
the next record opened for *Edit*.

`openEditModal()` now calls `resetForm()` first, on both the project and job site pages.
Verified: 1 staged upload before, 0 after reopening.

### 4. Wrong error when the amount was cleared
Clearing the amount field while shares were still filled reported "the distribution cannot
exceed the income amount" instead of "the amount field is required" — the split pre-check ran
before the field rules. It now only runs when the amount is a positive number.

### 5. Model guard misread a partially-selected model
The `updating` guard read raw cents out of `getAttributes()`, which is **absent** on
`Income::select('id','job_site_id')->find(...)`. The amount read as `0`, so a legitimate
update threw. Now falls back to `getRawOriginal('amount')`. Re-verified that the guard still
blocks a lowered amount and a job-site assignment on a slim model, and still allows a raise.

---

## Judged and left alone

- **`income_distributions.amount` is a signed bigint** (mirrors `incomes.amount`). Negatives
  are unreachable through the app — `syncDistributions()` drops anything ≤ 0 — but the column
  would accept one. Changing it needs a second migration against an already-deployed table.
- **Attachments are written after the transaction commits**, so a storage failure leaves a
  record without its files. Pre-existing pattern across the whole app; not introduced here.
- **No per-record authorization.** The pages rely on route middleware, with delete
  admin-only. The split adds no exposure: every query is scoped through
  `$this->project->income()` / `$this->jobSite->income()`, and the model rejects job sites
  from other projects.
- **Concurrent edits are last-write-wins.** Two people editing the same income both pass the
  guard. Consistent with the rest of the app.
- **Pre-existing:** `lang/en.json` has a duplicate `"by"` key (same value twice, harmless).
  Present in `5be0c44` too, so not from this work.

---

## Non-functional checks

- **Query counts are flat**, no N+1: project income page 6, job site income page 4,
  regardless of how many incomes are distributed.
- **All 173 views** compile and lint clean after the changes.
- **Eight scenario suites** green: model guards, report scoping, both pages end to end,
  create-with-split, mode switching, tamper paths, pt_BR rendering, partial-model guards.
