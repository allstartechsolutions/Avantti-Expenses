# People — one person across several subcontractors

Employees of subcontractors move from one company to the next, and sometimes present a
different tax id at each. The company wants both things at once: **one record per company**,
exactly as the person was given there, and **one thread through all of them**, so that "every
contract this person was on" is a question the system can answer.

This module is that thread. Built 2026-09-07 on `feature/employee-people-link`.

---

## 1. The model

```
people                          subcontractor_employees
──────                          ───────────────────────
id                              id
name        ← display only      subcontractor_id
notes                           person_id     → people.id   (nullable)
created_by                      name, title, phone, email   (as given to THIS company)
                                tax_id                      (as given to THIS company)
                                started_at, ended_at
                                notes
                                linked_by, linked_at, link_reason
```

- **The employee row is untouched in meaning.** It is still "this human, at this company":
  its own name spelling, its own contact details, its own tax id. Contracts keep pointing at
  it (`contracts.subcontractor_employee_id`), so nothing on a contract moves.
- **The person carries only what is true regardless of the company**: a display name (taken
  from the first row linked, editable), and notes. **It has no tax id of its own** — the tax
  id is a per-company fact that may differ, and that difference is something to show, not to
  resolve.
- **The link is a human judgement**, so it is recorded as one: `linked_by`, `linked_at` and a
  free-text `link_reason` on every row that joined a person.
- **A person never exists on its own.** It is created by the first link between two rows at
  different companies, and it dissolves (`Person::dissolveIfLonely()`) as soon as fewer than
  two rows are left on it — after an unlink, or after one of the rows is deleted. Nothing is
  backfilled by the migration.

### Linking rules — `SubcontractorEmployee::linkWith()`

| Situation | Result |
|---|---|
| Neither row has a person | A person is created from the first row's name; both rows join it |
| One row has a person | The other joins it |
| Both have different people | The two people become one (`Person::absorb()`); the other record is deleted |
| Both rows at the **same** company | Refused. Two rows at one company are two people, or one entered twice — never linked |

`unlink()` takes one row off; the person dissolves if that leaves it with one row.

### Lookalikes — `SubcontractorEmployee::lookalikes()`

Given a name, phone, e-mail and tax id, returns rows at *other* companies that share any of
them, each with the reasons it matched (`tax_id`, `phone`, `email`, `name`), strongest first.
Comparison is on normalised keys (`Person::normalizeTaxId()` strips punctuation and case, so
`123.456.789-09` and `12345678909` are one; phone compares digits; e-mail is trimmed and
lower-cased; name is accent-stripped alphanumerics). Rows already on the same person are left
out.

The tables are small — a handful of contacts per subcontractor — so this runs in PHP over one
query rather than adding normalised columns. Revisit if an install ever has thousands of
employee rows (backlog, §6).

---

## 2. Screens

### Subcontractor page → Employees tab

- **Add / Edit** one inline form (the tab already used one; it now edits as well). New fields:
  **Tax ID** (with a hint that this is the number given to *this* company), **Started**,
  **Ended** (`<x-ui.date-input>`), both optional; "Leave empty while they are still here."
- **As the details are typed**, the form looks for lookalikes at other companies and shows
  them in an amber panel with what matched. Picking one turns the submit into **Add and
  Link** / **Save and Link** and reveals the *reason* field. The link is made on save, inside
  the same transaction as the row.
- **The table** shows every stored field: name (with a *Former* badge past the end date),
  title, phone, e-mail, tax id, period, **Also at** (the other companies, linking to the
  person page, with a *Tax ids differ* badge when they do), contracts count, notes, and the
  actions: Edit, **Link**, **Unlink** (when linked), Delete.
- **Link** opens a full-page dialog: the row on the left, lookalikes on the right, a search
  box over every other company's employees (name, phone, e-mail, tax id, company), and the
  reason. Click a row to choose it; the footer says what is chosen.
- The Overview tab's summary card gains "Also known at other companies: N".

### People (`/people`) — sidebar, Projects group, after Subcontractors

- **Stat cards**: people linked, people with more than one tax id, suggested links.
- **People tab**: everyone linked, searchable by any name, company, phone, e-mail or tax id;
  companies as badges (current / former), the tax id or a *N different tax ids* badge,
  contracts count, when and by whom the last link was made.
- **Suggested links tab**: groups of rows at different companies sharing a **tax id, phone or
  e-mail** that are not already the same person, with **Link all as one person**. A shared
  name alone is deliberately *not* offered here — on a list it is noise; the vendor page
  offers it at the moment the row is typed, where the person adding it can judge.

### Person page (`/people/{person}`)

The "every contract this person was on" report:

- Header: name, notes, "Known at N companies · currently at …", **Edit** (name and notes).
- Stat cards: companies (with current count), **tax ids presented** (amber when more than
  one), contracts (with open count), contract value and paid (roll-ups, hidden where money
  is hidden).
- **Companies**: one row per employee record — company (link), name as given, title, tax id
  (amber when they differ), phone, e-mail, period, who linked it / when / why, notes, Unlink.
- **Contracts**: number (link), project, location, "through *company* as *name*", status,
  dates, amount and paid, with a totals footer. Filtered with `Contract::visibleTo()`, so a
  confined member sees only contracts on their own projects and the page says so.
- Audit: created by / at, last updated, and a line explaining the record dissolves below two
  rows.

### Contract page

Under *Contact*, a linked employee shows "Also known at other companies — see every contract
this person was on", linking to the person page (text only without `people.view`).

---

## 3. Permissions

New area **`people`** (`config/permissions.php`), global, `money => true`, swept:

| Ability | Guards |
|---|---|
| `people.view` | `/people`, `/people/{person}` (route middleware + `mount()`), the person link on the vendor and contract pages |
| `people.link` *(sensitive)* | `startLink`, `chooseLinkTarget`, `linkEmployee`, `unlinkEmployee`, `chooseEmployeeLink` on the vendor page; `linkGroup` on People; `startEdit`, `savePerson`, `unlinkEmployee` on the person page. Without it the add-employee form never offers lookalikes, and a `employee_link_to` the browser sends anyway is discarded on save |

Seeding: a new area is offered to the seeded roles by `PermissionSeeder::grantAbilitiesOfNewAreas()`
on `permissions:sync`. `people.link` is in `MANAGER_ONLY_ABILITIES` — a manager decides, an
employee sees the result. Custom roles get nothing until an administrator grants it.

**One thing moved on the existing area** — say what moved: **`saveEmployee` now requires
`vendors.edit`.** Before, the add-employee form had no guard of its own; anybody who could
open the vendor page (`vendors.view`) could add a row, while deleting one already needed
`vendors.edit`. Adding and editing now match deleting. `PeopleTest::test_adding_an_employee_now_needs_the_vendor_edit_grant`
records it.

`Contract::scopeVisibleTo()` is new (contracts had no cross-project scope before; every list
of them lived inside a project). It filters to `Project::visibleTo()` for a confined person.

---

## 4. Tests — `tests/Feature/Permissions/PeopleTest.php`

Reproduced (every seeded role opens both screens), revocable, scoped (a row of another
company cannot be linked through this page; two rows at one company are refused; a confined
member's person page shows only their projects' contracts), separate (view vs link, on the
vendor page and on the person page), plus the mechanics: create-on-link, join, fold two people
into one, dissolve on unlink and on delete, lookalikes in the form, the discarded link without
the grant, suggested groups on the People page, search.

Bookkeeping updated: `SecurityStateTest` and `LegacyBehaviourTest` swept lists,
`NavigationTest` sidebar (People after Subcontractors for all three roles).

---

## 5. Decisions taken

- **Tax id is stored as typed** and compared normalised. No mask, no validation of the
  number's format: the product runs in two countries and the field exists to record what was
  *presented*, including a wrong number.
- **Person name comes from the first row linked** and is editable on the person page. The
  list shows "Also as …" when the company records spell it differently.
- **A shared name is a hint, not a suggestion**: offered in the form as it is typed, never on
  the People page.
- **`linkGroup` links everything sent that sits at a different company** and skips a second
  row at the same company; the ids are re-read server-side.

## 6. Review backlog

See `docs/review-and-improvements.md`, rows **PE1–PE4**.
