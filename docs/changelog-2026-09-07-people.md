# Changelog — people across subcontractors (2026-09-07)

> **Superseded the same day.** *People* was renamed **Workers**, reshaped so that every
> employee row is a worker, and moved into the new Directory menu — see
> `changelog-2026-09-07-workers-directory.md` and `workers-module.md`. Kept as the record
> of what the first pull request shipped.

Subcontractor employees move between companies and sometimes present a different tax id at
each. Each company's record had to stay its own, and there was no way to say "these three
rows are one person" — so no way to list every contract somebody was on.

Now: a **person** record ties employee rows at different companies together, created by the
first link and dissolved when fewer than two rows remain; the **vendor page** edits employees,
records **tax id and period**, notices lookalikes at other companies as a row is typed and
offers the link; a **People** screen lists everyone linked (with a badge when their tax ids
differ) and suggests links from shared tax ids, phones and e-mails; a **person page** shows
every company, every tax id side by side and every contract, filtered to what the reader may
see. Reference: **[People](./people-module.md)**.

## What changed

| File | What |
|---|---|
| `database/migrations/2026_09_07_100000_create_people_table_and_link_subcontractor_employees.php` | **New.** `people`; on `subcontractor_employees`: `person_id`, `tax_id`, `started_at`, `ended_at`, `linked_by/at`, `link_reason` |
| `app/Models/Person.php` | **New.** `employees`, `contracts` (through), `distinctTaxIds()`, normalisers, `dissolveIfLonely()`, `absorb()` |
| `app/Models/SubcontractorEmployee.php` | `person`, `linkedBy`, `isCurrent()`, `siblings()`, `linkWith()`, `unlink()`, `lookalikes()`, `matchReasonLabel()`; dissolves a lonely person on delete |
| `app/Models/Contract.php` | **`scopeVisibleTo()`** — contracts had no cross-project scope |
| `app/Livewire/Subcontractor/SubcontractorShow.php` | Employee form adds *and edits*; tax id, started, ended; lookalike suggestions; link dialog; unlink. **`saveEmployee` now guarded by `vendors.edit`** (was unguarded) |
| `resources/views/livewire/subcontractor/partials/employees-tab.blade.php` | **New** (moved out of the page). Form, suggestions panel, table with Also at / Tax ids differ |
| `resources/views/livewire/subcontractor/partials/link-employee-modal.blade.php`, `link-candidates-table.blade.php` | **New.** Full-page link dialog |
| `app/Livewire/People/PeopleIndex.php`, `resources/views/livewire/people/people-index.blade.php` | **New.** People list + Suggested links, `linkGroup` |
| `app/Livewire/People/PersonShow.php`, `resources/views/livewire/people/person-show.blade.php` | **New.** The person page and contracts report; edit name/notes; unlink |
| `resources/views/livewire/contract/contract-show.blade.php` | Contact gains a link to the person page |
| `config/permissions.php` | Area **`people`** (`view`, `link` sensitive), sidebar entry. Catalogue **34 / 174** |
| `database/seeders/PermissionSeeder.php` | `people.link` manager-only |
| `routes/web.php` | `people.index`, `people.show` behind `ability:people.view` |
| `lang/en.json`, `lang/pt_BR.json` | ~120 strings |
| `tests/Feature/Permissions/PeopleTest.php` | **New.** 18 tests |
| `tests/Feature/Permissions/{SecurityState,LegacyBehaviour,Navigation}Test.php` | Bookkeeping |
| `CLAUDE.md`, `docs/permissions-module.md`, `docs/review-and-improvements.md` | Counts, backlog PE1–PE4 |

## What moved

- **Adding or editing an employee now needs `vendors.edit`.** It had no guard; deleting one
  already needed `vendors.edit`. Anybody who could only *view* vendors loses the add form.
- **`people.link` is manager-only by seed**; employees see people and their contracts but do
  not decide who is who.
