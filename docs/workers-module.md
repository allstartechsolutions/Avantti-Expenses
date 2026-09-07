# Workers — one person across several subcontractors

Employees of subcontractors move from one company to the next, and sometimes present a
different tax id at each. The company wants both things at once: **one record per company**,
exactly as the worker was given there, and **one thread through all of them**, so that "every
contract this worker was on" is a question the system can answer — for everybody, not only
for the ones who moved.

Built 2026-09-07 as *People*, renamed and reshaped the same day into *Workers*
(`docs/changelog-2026-09-07-workers-directory.md`). Terminology: **Worker** on a US install,
**Profissional** on a Brazilian one.

---

## 1. The model

```
workers                         subcontractor_employees
───────                         ───────────────────────
id                              id
name        ← display only      subcontractor_id
notes                           worker_id     → workers.id   (always set once saved)
created_by                      name, title, phone, email   (as given to THIS company)
                                tax_id                      (as given to THIS company)
                                started_at, ended_at
                                notes
                                linked_by, linked_at, link_reason
```

- **Every employee row is a worker from the moment it is created** (`SubcontractorEmployee`
  `created` hook). The migration backfilled one for every existing row. A worker known at one
  company is the normal case; one known at three is the same record with three rows.
- **The employee row is untouched in meaning.** It is still "this human, at this company":
  its own name spelling, its own contact details, its own tax id. Contracts keep pointing at
  it (`contracts.subcontractor_employee_id`), so nothing on a contract moves.
- **The worker carries only what is true regardless of the company**: a display name (taken
  from the row that created it, editable), and notes. **It has no tax id of its own** — the
  tax id is a per-company fact that may differ, and that difference is something to show, not
  to resolve.
- **Linking is merging.** `linkWith()` folds the other row's worker into this row's worker
  (`Worker::absorb()`), and stamps both rows with `linked_by`, `linked_at` and the free-text
  `link_reason`. The link is a human judgement, so it is recorded as one.
- **Unlinking is splitting.** `unlink()` gives the row a fresh worker of its own and clears
  its audit; a row that is alone on its worker has nothing to unlink and says so.
- **A worker whose last row is deleted is deleted with it** (`Worker::deleteIfEmpty()`).

### Linking rules — `SubcontractorEmployee::linkWith()`

| Situation | Result |
|---|---|
| Two rows at different companies, each on its own worker | The other worker is absorbed into this row's; both rows stamped |
| The other row's worker already has several rows | All of them come across; the two workers become one |
| Both rows already on one worker | Nothing changes (the dialog refuses it first) |
| Both rows at the **same** company | Refused. Two rows at one company are two workers, or one entered twice — never linked |

### Lookalikes — `SubcontractorEmployee::lookalikes()`

Given a name, phone, e-mail and tax id, returns rows at *other* companies that share any of
them, each with the reasons it matched (`tax_id`, `phone`, `email`, `name`), strongest first.
Comparison is on normalised keys (`Worker::normalizeTaxId()` strips punctuation and case, so
`123.456.789-09` and `12345678909` are one; phone compares digits; e-mail is trimmed and
lower-cased; name is accent-stripped alphanumerics). Rows already on the same worker are left
out. Runs in PHP over one query — the tables are small (backlog PE3).

---

## 2. Screens

### Directory → Vendors (`/vendors`)

Suppliers and subcontractors are one table (`docs/vendor-unification.md`); this is the one
list. Type cards (all / suppliers / subcontractors / both) double as filters; search across
company, contact, e-mail, phone and city; the documents-health filter applies to
subcontractors. Each row links to the richer page it has (subcontractor page when it is one,
supplier page otherwise), with edit and — under `vendors.delete` — delete for a company with a
single classification and no linked records. Anything else is sent to its own page, where each
side's rules are spelled out. The old Suppliers and Subcontractors lists keep their routes.

### Directory → Workers (`/workers`)

- **Stat cards**: workers, at several companies, with more than one tax id, suggested links.
- **Workers tab**: everybody, searchable by any name, company, phone, e-mail or tax id, with
  filters for status (current / former), companies (several / one) and tax ids (differ).
  Companies as badges (current / former), the tax id or a *N different tax ids* badge,
  contracts count, when and by whom the last link was made.
- **Suggested links tab**: groups of rows at different companies sharing a **tax id, phone or
  e-mail** that are not already one worker, with **Link all as one worker**. A shared name
  alone is deliberately *not* offered here — on a list it is noise; the vendor page offers it
  at the moment the row is typed, where the person adding it can judge.

### Worker page (`/workers/{worker}`)

The "every contract this worker was on" report:

- Header: name, notes, "Known at N companies · currently at …", **Edit** (name and notes).
- Stat cards: companies, **tax ids presented** (amber when more than one), contracts, contract
  value and paid (roll-ups, hidden where money is hidden).
- **Companies**: one row per employee record — company (link), name as given, title, tax id
  (amber when they differ), phone, e-mail, period, who linked it / when / why, notes, Unlink
  (or, for a worker with one row, a link to the company page to link from there).
- **Contracts**: number (link), project, location, "through *company* as *name*", status,
  dates, amount and paid, with a totals footer. Filtered with `Contract::visibleTo()`, so a
  confined member sees only contracts on their own projects and the page says so.

### Subcontractor page → Employees tab

- One inline form adds and edits: name, title, phone, e-mail, **tax id** (with a hint that this
  is the number given to *this* company), **started**, **ended**, notes.
- **As the details are typed**, lookalikes at other companies appear in an amber panel with
  what matched. Picking one turns the submit into *Add and Link* / *Save and Link* and
  reveals the reason field. The link is made on save, in the same transaction as the row.
- **The table** shows every stored field plus a **Worker** column: "Also at A, B" or "Only
  here", linking to the worker page, with a *Tax ids differ* badge when they do. Actions:
  Edit, **Link**, **Unlink** (when known elsewhere), Delete.
- **Link** opens a full-page dialog: the row on the left, lookalikes on the right, a search
  over every other company's employees, and the reason.
- `?tab=employees` opens the tab directly — the worker page links there.

### Contract page

Under *Contact*, "See every contract this worker was on" links to the worker page
(`workers.view`).

---

## 3. Permissions

Area **`workers`** (`config/permissions.php`), global, `money => true`, swept:

| Ability | Guards |
|---|---|
| `workers.view` | `/workers`, `/workers/{worker}` (route middleware + `mount()`), the worker links on the vendor and contract pages |
| `workers.link` *(sensitive)* | `startLink`, `chooseLinkTarget`, `linkEmployee`, `unlinkEmployee`, `chooseEmployeeLink` on the vendor page; `linkGroup` on Workers; `startEdit`, `saveWorker`, `unlinkEmployee` on the worker page. Without it the add-employee form never offers lookalikes, and an `employee_link_to` the browser sends anyway is discarded on save |

`workers.link` is in `MANAGER_ONLY_ABILITIES`. The rename migration rewrote the stored
`people.*` abilities on every role and override, and the `seeded_areas` record, so nobody
lost access and the seeder does not offer the area twice.

The Vendors list answers to the existing `vendors.*` abilities. Both areas belong to the
`projects` module, so the Directory group follows that module's switch (backlog DR1).

**Two things moved on the way** — say what moved:

- **`saveEmployee` requires `vendors.edit`** (since the People build). Before, the
  add-employee form had no guard of its own.
- **Suppliers left the Catalog group and Subcontractors left the Projects group** in the
  sidebar; both now sit behind *Directory → Vendors*. Their routes and abilities are unchanged.

---

## 4. Tests

`tests/Feature/Permissions/WorkerTest.php` — reproduced, revocable, scoped, separate, plus the
mechanics: worker-on-create, merge on link, join a third, fold two workers, split on unlink,
delete keeps the other row's worker, lookalikes in the form, the discarded link without the
grant, suggested groups, the list's search and filters.
`tests/Feature/Permissions/VendorDirectoryTest.php` — the Vendors list: every role, revocable,
type filters, and the delete rules.

Bookkeeping: `SecurityStateTest` and `LegacyBehaviourTest` swept lists, `NavigationTest`
sidebar (Directory group between Projects and Catalog for all three roles).

---

## 5. Decisions taken

- **Worker / Profissional.** "Worker" is the plain US construction term; "Profissional" is
  what a Brazilian site calls a tradesperson who moves between companies, and it avoids
  "colaborador", which reads as the company's own staff. The daily report PDF's headcount
  column shares the *Workers* key and now reads *Profissionais* too.
- **Vendors is *Empresas* in pt_BR**, because *Fornecedores* is already *Suppliers*, and a
  group called Fornecedores containing a filter called Fornecedores would be nonsense.
- **Tax id is stored as typed** and compared normalised. No mask, no format validation: the
  product runs in two countries and the field records what was *presented*, including a wrong
  number.
- **Worker name comes from the row that created it** and is editable on the worker page. The
  list shows "Also as …" when the company records spell it differently.
- **A shared name is a hint, not a suggestion**: offered in the form as it is typed, never on
  the Workers page.
- **The unified vendor *detail* page is step two**, not built: a subcontractor still opens
  the subcontractor page, a pure supplier the supplier page.

## 6. Review backlog

See `docs/review-and-improvements.md`, rows **PE1–PE4** and **DR1–DR2**.
