# Changelog — Workers and the Directory menu (2026-09-07)

Same day as the People build (`changelog-2026-09-07-people.md`), on its heels: *People*
becomes **Workers**, every employee row is a worker from the start rather than only the ones
linked across companies, and the sidebar gains a **Directory** group holding one **Vendors**
list (suppliers and subcontractors together) and **Workers**. Reference:
**[Workers](./workers-module.md)**.

## What changed

| File | What |
|---|---|
| `database/migrations/2026_09_07_180000_rename_people_to_workers_and_backfill.php` | **New.** `people` → `workers`, `person_id` → `worker_id`; a worker created for every employee row without one; `people.*` abilities rewritten to `workers.*` on roles and overrides; `seeded_areas` rewritten |
| `app/Models/Worker.php` | **New** (replaces `Person`). `absorb()`, `deleteIfEmpty()`, `isAtSeveralCompanies()`, `isCurrent()`, `distinctTaxIds()`, normalisers |
| `app/Models/SubcontractorEmployee.php` | Worker created on `created`; `linkWith()` merges workers; `unlink()` splits one off; `isLinked()` now means "known elsewhere" |
| `app/Livewire/Worker/WorkerIndex.php`, `resources/views/livewire/worker/worker-index.blade.php` | **New** (replace People). Every worker; status / companies / tax id filters; suggested links |
| `app/Livewire/Worker/WorkerShow.php`, `resources/views/livewire/worker/worker-show.blade.php` | **New** (replace PersonShow). Unlink splits; a lone row points to the company page |
| `app/Livewire/Vendor/VendorIndex.php`, `resources/views/livewire/vendor/vendor-index.blade.php` | **New.** The Directory's Vendors list: type cards, search, documents filter, view/edit/delete |
| `app/Livewire/Subcontractor/SubcontractorShow.php` + partials | person → worker; *Worker* column shows "Also at …" or "Only here" for every row; `?tab=` opens a tab |
| `resources/views/livewire/contract/contract-show.blade.php` | "See every contract this worker was on" |
| `config/permissions.php` | Area `people` → **`workers`**; new sidebar group **`directory`** (order 35) with `vendors` and `workers`; Suppliers entry removed from Catalog, Subcontractors from Projects. Catalogue stays 34 / 174 |
| `config/modules.php` | `workers.*` owned by `projects`, beside `vendors.*` |
| `database/seeders/PermissionSeeder.php` | `workers.link` manager-only |
| `routes/web.php` | `vendors.index`, `workers.index`, `workers.show` |
| `lang/en.json`, `lang/pt_BR.json` | ~70 strings added, 39 People-only strings removed; **Workers → Profissionais**, **Vendors → Empresas** |
| `tests/Feature/Permissions/WorkerTest.php` (was PeopleTest), `VendorDirectoryTest.php` | 19 + 3 tests |
| `tests/Feature/Permissions/{SecurityState,LegacyBehaviour,Navigation}Test.php` | Bookkeeping |
| `docs/workers-module.md` (was people-module.md), `docs/review-and-improvements.md`, `docs/sidebar-navigation.md` | Docs, backlog DR1–DR2 |

## What moved

- **Every employee row now has a worker**, so the Workers list shows everybody and every
  worker has a contracts page. Before, only rows linked across companies had a record.
- **Suppliers and Subcontractors left their old menu groups** for *Directory → Vendors*.
  Old routes and abilities are unchanged; bookmarks still work.
- **pt_BR labels**: *Workers* was *Trabalhadores* (used by the daily report PDF headcount
  column) and is now *Profissionais* everywhere; *Vendors* was *Fornecedores* (the same word as
  *Suppliers*) and is now *Empresas* — this also changes the heading of the vendor-documents
  block on the notification settings page.
