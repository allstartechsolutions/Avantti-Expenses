# Change orders: approval now gates the revenue side too

**1 Sep 2026.** Only an **approved** change order revises the contract value, and therefore the
profit. Draft, pending and rejected ones do not.

## Why

A change order has always carried two independent amounts: `amount`, what the client is billed,
and `items`, what the change does to each cost code's budget. Since the status column shipped
(19 Aug 2026) the cost side waited for approval and the revenue side did not — every change
order counted towards the contract value whatever its status.

That meant an offer the client had **rejected** still moved the project's contract value and its
profit figure, and so did a draft nobody had finished writing. The two halves of the same record
disagreed about whether it had happened.

## What moved

| Figure | Before | Now |
|---|---|---|
| Contract value (project and job site) | every change order | approved only |
| Profit / Loss card | every change order | approved only |
| Per-job-site breakdown rows | every change order | approved only |
| "Billed to the Client" card on the Change Orders tab | every change order | approved only |
| Cost code budgets | approved only | approved only (unchanged) |

Both PDF reports follow their screens exactly; a test asserts they cannot drift apart.

**No historical figure moved.** The `2026_08_19_180001` migration backfilled every pre-existing
row to `approved`, so only records created since 19 Aug 2026 in a non-approved state are
affected.

## What is *not* counted is still reported

Silently dropping a number somebody typed is how a report loses trust, so nothing disappears:

- **Contract Value card** — a footnote reads "`+$X` in change orders is awaiting a decision and
  is not counted here" whenever there is a draft or pending one.
- **Job site overview** — the card counts approved change orders and, below, names how many are
  awaiting a decision and for how much. The list under the cards still shows every record.
- **Contract Value Breakdown** — two groups. *Approved Change Orders*, which add up to the
  subtotal, and *Not Counted*, struck through, each row labelled with its status and a line
  explaining that approving one moves it into the total above.
- **Change Orders tab** — "Billed to the Client" shows the approved total and says how many more
  were raised but are not counted. "Awaiting a Decision" is unchanged.
- **Both PDFs** — the same two groups, so the client's copy accounts for every change order.

## Where the rule lives

One place per level, rather than the six hand-rolled sums that were there before:

- `Project::getAdjustedContractValue()` · `getApprovedChangeOrdersTotal()` · `getPendingChangeOrdersTotal()`
- `JobSite::getContractValue()` · `getAdjustedContractValue()` · `getApprovedChangeOrdersTotal()` · `getPendingChangeOrdersTotal()`

`JobSiteOverview`, both financial reports and both PDF controllers now call these instead of
summing `change_orders.amount` themselves. `ChangeOrder::getStatusLabel()` / `statusLabel()`
replace the status-label array that had been copied into two blades.

Contract change orders (`contract_change_orders`, the aditivos on a subcontractor contract) have
no status and are untouched.

## Tests

`tests/Feature/ChangeOrderContractValueTest.php` — the rule at both levels, a rejected change
order no longer moving the profit, an approved **deductive** one still reducing it (the sign has
to keep working), the two lists together still holding every record, and the PDFs agreeing with
the screens figure for figure.
