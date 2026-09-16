# Equipment

Vehicles, machinery and tools the company owns, leases or rents: what it has, where each
piece is, when it is next due for service, what was found the last time, and what it has
cost. Not a catalog item — the catalog is what is bought; this is what is kept. Deploy
summary: **[changelog](./changelog-2026-09-16-equipment.md)**. Built as the second half of
the plan that began with [company expenses](./company-expenses.md).

## What the module is

| Screen | Route | What it shows |
|---|---|---|
| **Equipment** (Company group) | `/equipment` | The register: cards (in service, active, in maintenance, overdue, due in 30 days), filters (type, status, where, search), a row per piece with its next maintenance |
| **Add / Edit** | `/equipment/create`, `/equipment/{id}/edit` | Identity, ownership and purchase, meter, status, photo, notes |
| **Equipment page** | `/equipment/{id}` | Tabs: Overview, Maintenance, Readings, Findings, Assignments, Costs, Attachments |
| **Maintenance** (Company group) | `/equipment/maintenance` | Every open service across the fleet grouped by month; overdue / due soon / scheduled / in progress / completed / cancelled |
| **Project and job-site tab** | `/projects/{id}/equipment`, `/job-sites/{id}/equipment` | What is on that project or site now, what has been there, and a picker to send something there |
| **Dashboard card** | `/` | Maintenance Due: overdue or due, within 7 days, within 8–30 days (three disjoint counts), the first five rows |

## Schema

| Table | What one row is |
|---|---|
| `equipment` | The piece: name, asset tag, type (`vehicle` / `machinery` / `tool` / `other`), make, model, year, serial, plate, VIN; ownership (`owned` / `leased` / `rented`), supplier, purchase date and cost (cents), warranty; meter type (`none` / `km` / `mi` / `hours`) and current reading (denormalised from the readings); status (`active` / `in_maintenance` / `retired` / `sold`); current assignment (project, job site, responsible person, since); photo, notes |
| `equipment_meter_readings` | One odometer or hour-meter reading, `manual` or written by a completed `maintenance` |
| `equipment_assignments` | One stay at a project, site or with a person; the open stay has no `ended_at` |
| `equipment_maintenance_plans` | A recurring service: every N days and/or every N meter units, a "due soon" margin, the last completion point |
| `equipment_maintenances` | One service, planned or one-off: type, date and/or due reading, `scheduled` → `in_progress` → `completed` / `cancelled`, who did it, and the four reminder stamps |
| `equipment_findings` | Something encountered during a maintenance: severity, photo, open until a later maintenance or a note resolves it |
| `equipment_histories` | The audit trail, the shape of `expense_change_histories` |
| `expenses.equipment_id`, `expenses.equipment_maintenance_id` | The tag on any expense — a project's or the company's |

Vendor foreign keys are named `supplier_id` and both tables are in `Vendor::SUPPLIER_FK_TABLES`,
so a vendor merge repoints them.

## The rules (`App\Services\MaintenanceScheduler`)

1. **A plan always has exactly one open occurrence** — the next service. It is generated from
   the point the last one was **completed** (its date and its meter reading), not from the
   point it was planned for: a service done late does not make the next one early. A
   meter-only plan on equipment with no reading yet waits for the first reading. Editing a
   plan re-aims that same row (its id is what expenses, readings and history point at) and
   resets its reminder stamps only when the due point actually moved.
2. **Due when the date arrives OR the meter is reached, whichever first.** Due-ness is
   computed by `EquipmentMaintenance::isDue()` / `urgency()`, never stored. "Due soon" is
   within 30 days, or within the plan's meter margin (a tenth of the interval by default).
   Overdue is past the date; a meter reached has no "past".
3. **Completing** requires the date and, on metered equipment, the reading — which is
   logged as a reading of source `maintenance` — plus who did it (own team or a vendor) and
   notes; the open findings ticked are resolved in that maintenance; the plan gets its next
   occurrence; the equipment goes back to `active` when nothing else is in progress.
4. **Cancelling** a plan's occurrence is a skip: the next one is generated from the point
   this one was due. A one-off just closes. Deactivating a plan cancels its pending service;
   reactivating re-plans from the last completion.
5. **A reading never goes backwards.** Back-dating is allowed between its neighbours; the
   newest reading by date becomes the equipment's current one. Logging a reading tells you
   which maintenances it has just made due.
6. **Findings** are recorded on a maintenance that has started or finished, stay open until
   resolved, and never block completion — they are the reason for the next corrective.
7. **Assignment** to a job site implies its project; sending somewhere closes the open stay
   the day the new one starts; sending back leaves it nowhere. Deleting a project or job
   site ends every open stay there (`EquipmentAssignment::closeFor()`).
8. **Cost is derived.** A maintenance's cost is the sum of the expenses tagged to it; the
   total cost of ownership is the purchase cost plus every tagged expense the reader may
   see (`Expense::visibleTo()` — a confined reader's projects, and the company's rows only
   with `company-expenses.view`).

## Permissions

Area `equipment`, module **`equipment`** (its own switch on System Settings → Modules),
`money => true`, `levels => ['global', 'project', 'job_site']`.

| Ability | What it guards | Seeded to |
|---|---|---|
| `equipment.view` | the register, the maintenance list, the equipment page, the project / site tab, the dashboard card, the Costs tab (amounts still follow `can_see_money`) | everyone |
| `equipment.create` | adding to the register | everyone |
| `equipment.edit` | the edit screen, including status | everyone |
| `equipment.assign` | sending equipment to a project, site or person, and back — only to a project the actor may open (`Project::visibleTo()`) | everyone |
| `equipment.maintain` | readings, plans, scheduling, starting, completing, cancelling, findings | everyone |
| `equipment.delete` *(sensitive)* | deleting — a bare entry from the register's row, anything with history from its page, refused while expenses are tagged | admins |

Two decisions worth knowing:

- **A company record with project levels.** Equipment belongs to no project — being sent to
  one never makes it the record's scope (`Equipment::permissionScope()` returns null) — but
  the project and job-site Equipment tabs are project screens, and the catalogue rule is
  that a tab's grant must be one a membership can hold. So the area is grantable at every
  level, which for somebody **confined** is narrower than the role: they reach the register
  only if a membership of theirs says so. The seeded Project Manager, Site Supervisor and
  Site Team templates carry `view` / `assign` / `maintain` accordingly. The tab itself also
  asks `project.view` on the scope, so a member of another project never sees it.
- **Delete is refused, not cascaded, with money on the record.** `expenses.equipment_id` is
  `restrictOnDelete`; retire or mark sold instead.

File directory `equipment/` in `FileController::authorizeFile()` resolves the photo, the
attachments and a finding's photo to the equipment and asks `equipment.view`.

## Reminders

`equipment:notify-maintenance-due`, daily at 07:20, `EquipmentMaintenanceNotifier` — the
vendor-document pattern: four stages stamped on the maintenance (`notified_30_at`,
`notified_7_at`, `notified_due_at`, `notified_overdue_at`), "on or before" so a missed
morning is caught the next, one grouped mail per person per morning
(`EquipmentMaintenanceDueMail`), the notification log refusing a second copy the same day,
stamps written only when a delivery succeeded or nobody was to be told — never when every
delivery failed, and never on a second run the same day (everybody already had the digest,
so tomorrow's says it). A maintenance with both a date and a meter reaches the 7-day stage
by either: seven days out, or inside the plan's meter margin. A meter-only maintenance
reaches it through the margin and the due stage the day a reading reaches the due reading;
it has no overdue stage. Completing, cancelling, retiring or selling ends the sequence.

Recipients: the people picked on System Settings → Notifications (Equipment E-mails), else
everyone who may maintain equipment; the company switch is
`NotificationSetting::EQUIPMENT_MAINTENANCE_DUE`, and a person can opt out on their profile.

## Expenses and costs

Every expense form — the shared create/edit form, the two legacy modals, the company expense
form — carries an optional **Equipment** picker and, once a piece is chosen, **For which
maintenance**. The picker appears only where the Equipment module is on and the person holds
`equipment.view`; otherwise nothing is offered and nothing new is accepted, though a row that
already carries a tag keeps it. The equipment page's Maintenance tab offers *Add cost* on an open service,
which opens the company expense form pre-tagged (`?equipment=&maintenance=`); the Costs tab
offers *Add a company expense for this equipment*. The Costs tab shows the total cost of
ownership, the purchase cost, the tagged total, what is not tied to a maintenance, and the
tagged expenses by project or category, by month and by maintenance, each narrowed to what
the reader may see.

## Tests

- `tests/Feature/Permissions/EquipmentTest.php` — reproduced, revocable, separate (create /
  edit / delete / assign / maintain), the project and site tab under confinement, the
  whole maintenance cycle through the page, tagging and the Costs tab, delete refused.
- `tests/Feature/Equipment/MaintenanceScheduleTest.php` — the eight rules above.
- `tests/Feature/Permissions/EquipmentMaintenanceRemindersTest.php` — stages, idempotency,
  meter stages, ending the sequence, recipients, switches, the command, the mail.

## Backlog

Recorded in `docs/review-and-improvements.md` under *Equipment (2026-09-16)*.
