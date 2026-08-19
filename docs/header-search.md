# Header Search

The global search box in the desktop top header. It finds **projects** and **job sites**
and jumps straight to their overview pages.

- Component: `app/Livewire/Shared/HeaderSearch.php`
- View: `resources/views/livewire/shared/header-search.blade.php`
- Mounted from: `resources/views/components/layouts/inc/header.blade.php`

---

## Behaviour

**Nothing is queried until someone searches.** `HeaderSearch::term()` returns `null` while
the trimmed term is shorter than `MIN_LENGTH` (2), and every computed property short-circuits
on it. A normal page load issues **zero** queries for the search box; the first query only
runs on the debounced (300 ms) Livewire round trip once the second character is typed.

Verified with the query log:

| State | Queries |
|---|---|
| Page load, empty box | 0 |
| 1 character typed | 0 |
| 2+ characters typed | 3 (projects, their clients, job sites — plus their projects when matched) |

## What it matches

Each group returns at most `PER_GROUP` (5) rows, ordered by name.

| Group | Matched against |
|---|---|
| Projects | `project_name`, `street`, `city`, `contact_person`, client `company_name` |
| Job sites | `job_site_name`, `street`, `city`, `contact_person`, parent project `project_name` |

`%` and `_` in the term are escaped before they reach the `LIKE`, so a user typing a wildcard
searches for that literal character instead of matching everything.

Relations are eager-loaded (`client:id,company_name`, `project:id,project_name`) and the
selects are narrowed to the columns the dropdown renders, so a search is a fixed small number
of queries regardless of how many rows come back.

## The dropdown

- Grouped by entity, with a count next to each group heading.
- Each row: status dot, name, parent (client for a project, project for a job site), address,
  and the status label.
- **Keyboard**: `↑`/`↓` browse, `↵` opens the highlighted row, `Esc` closes. The highlight is
  pure Alpine over the rendered anchors, so browsing costs no server round trips.
- **Loading**: a spinner replaces the clear button while the debounced request is in flight.
- **Empty state**: names the term that failed and says what the search actually covers, with
  a link to browse all projects.
- **Below minimum length**: tells the user how many characters are needed.
- `x-cloak` keeps the panel hidden until Alpine takes over, so there is no flash on load.
- Right-aligned and capped at `calc(100vw - 3rem)` so it cannot push the page sideways.

## Terminology

Group labels use `__('Projects')` and `__('Job Sites')`, so they follow whatever each install
calls those levels. In the current English file that renders as **Job Sites** and **Lots**;
in pt_BR as **Projetos** and **Locais**. Strings that name the entities have their own
per-locale wording in `lang/en.json` and `lang/pt_BR.json` under `_header_search`.

## Known gaps

- **Desktop only.** `inc/header.blade.php` is `hidden lg:block`; the mobile header has no
  search. Adding it means a full-width overlay panel rather than a 384 px dropdown.
- **Not permission-scoped.** Every signed-in user searches every project and job site. See
  `docs/permissions-notes.md` → N9, which rides on the N4 decision about per-project
  confinement.
- **"See all matches"** goes to the projects index filtered by the term. There is no combined
  results page covering job sites as well.

---

## Tuning knobs

| What | Where |
|---|---|
| Minimum characters before any query | `HeaderSearch::MIN_LENGTH` |
| Rows per group | `HeaderSearch::PER_GROUP` |
| Debounce | `wire:model.live.debounce.300ms` in the view |
| Fields matched | the `where(...)` closure in `projects()` / `jobSites()` |

## A note on indexes

`projects.project_name` carries an index (`projects_project_name_index`, added in the projects
migration); `job_sites.job_site_name` does not. Neither matters much here — the search uses
`LIKE '%term%'`, and a leading wildcard cannot use a B-tree index, so both groups are a table
scan either way. The scan is bounded by `LIMIT 5` and the narrow column list, which is what
keeps it cheap at the scale these installs run at.

If an install ever grows to where that scan is felt, the fix is a FULLTEXT index and
`MATCH ... AGAINST` (or a trigram index) rather than more B-tree indexes on the same columns.
