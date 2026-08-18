# Changelog — 2026-08-18 (income distribution + job site income page)

## Income distribution across job sites

Project-level income can now be split across the project's job sites. Income booked
directly on a job site is untouched — it already belongs to one place.

- **New table** `income_distributions` (`income_id`, `job_site_id`, `amount` cents), unique
  per (income, job site), cascade on delete. Migration
  `2026_08_18_110000_create_income_distributions_table.php`. **Additive only.**
- **New model** `App\Models\IncomeDistribution`.
- **`App\Models\Income`** gained `distributions()`, `distributedTotal()`,
  `undistributedAmount()`, `isDistributed()`, `syncDistributions()`, and an `updating`
  guard that blocks lowering the amount below what is already distributed and blocks
  pinning a distributed income to one job site.
- **The income form is now a full-page modal that carries the split** — no separate
  distribute step. Left column: amount, status, dates, title, description, attachments.
  Right column: **"Where does this money go?"** — *One location* (project general or a single
  job site) or *Split across locations*, which opens a grid with Income Amount / Distributed
  / Remaining totals over a progress bar, a location search, **Select all / Split evenly /
  Clear all**, and a row per job site with **Amount and % side by side** (typing either
  rewrites the other) plus **Take remainder**. Saving is one transaction; switching modes
  clears whichever destination no longer applies.
- **Clicking an income opens a full-page detail modal showing every field the record
  holds** — headline amount with days overdue, a Record panel (status, dates, project,
  location, added by, attachment count, created at, last updated), the description or an
  explicit "none added", the complete distribution table with per-site amounts and percents
  plus Distributed / Undistributed footer rows and links to each job site, the attachments
  component, and footer actions (Mark as received / Delete / Edit / Close).
- **Job site page** — the same full-page form and detail view. A **Project share** row opens
  the detail modal read-only: a banner pointing at the project page, this location's share as
  the headline, the full distribution with this location highlighted, and no editing actions.
- **`x-ui.modal`** gained a `maxWidth="full"` size (fills the viewport, square corners, no
  gutter). Additive — every existing modal is unchanged.
- **`Income::syncDistributions([])`** now clears a split on any income, not only a
  project-level one: clearing is how an income stops being split, and only *writing* shares
  needs the record to be project-level.
- **`CLAUDE.md` gained a Design Standard section** (full-page modals for real work, detail
  views that show everything, visible totals, bulk actions, designed empty states, both
  themes and locales, consistency across project and job-site levels).
- **`CompanyFinancialService`** — a job-site scope now also matches income distributed to
  that job site and counts its share; project and client scopes still count each income
  once, whole.

## Job site income page (income module phase 2)

- **New** `App\Livewire\JobSite\JobSiteIncome` + `job-site-income.blade.php`, route
  `job-sites/{jobSite}/income` (`jobsites.income`), nav tab between Expenses and Change
  Orders.
- Same features as the project page, scoped to the job site, plus a read-only
  **Project share** row for this job site's part of a project-level income, counted in the
  summary cards and linking back to the project page.

## Localization

57 new keys in `lang/en.json` and `lang/pt_BR.json` under an `_income_distribution` block.
All BR strings use the client's term **Entrada** (never "Receita"); two pre-existing strings
that said "receita" were corrected to match.
Full-view compile + lint check run over all 173 views: clean.

## Deploy

`php artisan migrate` and `php artisan view:clear`.
