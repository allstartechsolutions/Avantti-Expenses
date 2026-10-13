# Job site (lot) list: status filter (13 Oct 2026)

`/projects/{id}/jobsites` could only be searched by text. It now has a row of status chips
above the list — **All · Created · In Progress · Completed · On Hold** — each with the number
of the project's job sites in that status, so the counts read as a summary even before one
is clicked. (An English install calls job sites *lots*; the strings follow `en.json`.)

## Behaviour

- One chip at a time; it combines with the search. The header reads *"3 of 12"* while
  anything is filtered, *"(12)"* otherwise.
- The counts are the project's whole list (one grouped query) and do not change with the search.
- An unrecognised `statusFilter` from the browser filters nothing (`JobSiteStatus::tryFrom`).
- A filter that matches nothing shows *"No lots match your filters."* with **Clear Filters**,
  instead of the *"Get started by creating…"* message meant for a project with no job sites.
- `JobSiteStatus::label()` now goes through `__()`. The status badge and the form's status
  select were printing English on a pt_BR install; the keys already existed.

## Files

| File | What |
|---|---|
| `app/Livewire/Project/ProjectJobSites.php` | `$statusFilter`, `clearFilters()`, status counts |
| `resources/views/livewire/project/project-job-sites.blade.php` | Chips, filtered header count, no-match empty state |
| `app/Enums/JobSiteStatus.php` | Translated labels |
| `lang/en.json`, `lang/pt_BR.json` | 4 strings |
| `tests/Feature/Project/ProjectJobSitesStatusFilterTest.php` | **New.** 4 tests |

No migration, no permission change: the list sits behind the project tab's existing guard,
and the filter only narrows what that query already returned.

Parity: the job site level has no list of job sites, so there is no counterpart to update.
