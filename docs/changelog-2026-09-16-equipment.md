# Changelog — Equipment (2026-09-16)

An asset register for vehicles, machinery and tools, with maintenance scheduled by date
and/or meter, findings, assignments to projects and sites, e-mail reminders, and expenses
tagged to each piece. Reference: **[Equipment](./equipment-module.md)**. Second half of the
plan that began with [company expenses](./changelog-2026-09-16-company-expenses.md).

## What changed

| File | What |
|---|---|
| `database/migrations/2026_09_16_1100xx_*` (8) | **New.** `equipment`, `equipment_meter_readings`, `equipment_assignments`, `equipment_maintenance_plans`, `equipment_maintenances`, `equipment_findings`, `equipment_histories`; the `equipment` row in `module_access` |
| `database/migrations/2026_09_16_120000_add_equipment_to_expenses_table.php` | **New.** `expenses.equipment_id` (restrict) and `expenses.equipment_maintenance_id` (null on delete) |
| `app/Models/Equipment*.php` (7) | **New.** Labels with their own keys, due-ness (`isDue`, `urgency`, `dueLabel`), assignment (`assignTo`, `endAssignment`, `closeFor`), cost helpers, `permissionScope()` null |
| `app/Services/MaintenanceScheduler.php` | **New.** Readings (never backwards), plans and their one open occurrence, schedule / start / complete / cancel, findings |
| `app/Services/EquipmentMaintenanceNotifier.php`, `app/Console/Commands/NotifyEquipmentMaintenanceDue.php`, `app/Mail/EquipmentMaintenanceDueMail.php` + view, `routes/console.php` | **New.** The reminders, daily at 07:20 |
| `app/Models/NotificationSetting.php`, `NotificationLogEntry.php`, `app/Livewire/SystemSettings/NotificationSettings.php` + view, `resources/views/livewire/settings/notifications.blade.php` | The `equipment_maintenance_due` trigger, its recipients card and personal opt-out |
| `app/Livewire/Equipment/{EquipmentIndex,EquipmentCreate,EquipmentEdit,EquipmentShow,MaintenanceIndex}.php`, `app/Livewire/Concerns/ManagesEquipmentForm.php`, `resources/views/livewire/equipment/**` | **New.** The screens; the equipment page's seven tabs are partials. The register deletes a bare entry from its row; the page deletes anything with history through the counted modal |
| `app/Livewire/Project/ProjectEquipment.php`, `app/Livewire/JobSite/JobSiteEquipment.php`, `app/Livewire/Concerns/ListsScopedEquipment.php`, one shared partial | **New.** The project and job-site Equipment tab |
| `app/Livewire/Concerns/PicksEquipment.php`, `resources/views/livewire/expense/partials/equipment-picker.blade.php` | **New.** The equipment tag on every expense form (`ManagesExpenseForm`, `ProjectShow`, `JobSiteShow`) |
| `app/Livewire/Expense/ExpenseCreate.php`, `app/Livewire/CompanyExpense/CompanyExpenseCreate.php` | Pre-tag from `?equipment=&maintenance=` |
| `app/Livewire/Dashboard/DashboardIndex.php` + `partials/overview.blade.php` | The Maintenance Due panel |
| `app/Livewire/Project/{ProjectIndex,ProjectOverview,ProjectJobSites,ProjectShow}.php`, `app/Livewire/JobSite/{JobSiteOverview,JobSiteShow}.php` | Deleting a project or site ends the equipment stays there (every delete path, the legacy pages included) |
| `app/Livewire/Shared/Attachments.php`, `app/Http/Controllers/FileController.php` | `equipment` attachments and the `equipment/` directory |
| `app/Models/Vendor.php` | `equipment`, `equipment_maintenances` in `SUPPLIER_FK_TABLES` |
| `config/permissions.php` | Area **`equipment`** (six actions, all levels), three entries in the **Company** group (with Company Expenses, by the owner's decision), the `equipment` project/site tab. Catalogue **36 / 186** |
| `config/modules.php` | Module key `equipment`, declared before `projects` |
| `database/seeders/PermissionSeeder.php` | `equipment.delete` admin-only; `view` / `assign` / `maintain` on the Project Manager, Site Supervisor and Site Team templates |
| `routes/web.php` | Five `equipment.*` routes and the two tab routes |
| `lang/en.json` | ~330 strings, including the namespaced enum labels. **No pt_BR** — the owner keeps a separate repository for the Brazilian version from here on |
| `tests/Feature/Permissions/EquipmentTest.php` (15), `tests/Feature/Equipment/MaintenanceScheduleTest.php` (8), `tests/Feature/Permissions/EquipmentMaintenanceRemindersTest.php` (9) | The tests, including the regressions from the review pass |
| `tests/Feature/Permissions/{Navigation,TeamTab,SecurityState,LegacyBehaviour}Test.php`, `tests/TestCase.php` | Bookkeeping |
| `docs/equipment-module.md`, `docs/delete-functionality.md`, `docs/deployment-scheduler.md`, `docs/vendor-unification.md`, `docs/sidebar-navigation.md`, `docs/review-and-improvements.md`, `docs/README.md`, `CLAUDE.md` | Docs |

## What moved

- **Every seeded role** sees Equipment and Maintenance in the Company group and may register,
  edit, assign and maintain; only administrators delete.
- **Somebody confined** reaches the register only through a membership that grants
  `equipment.view` — the three site templates now carry it.
- **Expense forms** gain an optional Equipment picker; nothing changes for an expense that
  leaves it blank.
- **Company Expenses moved** from the Projects group to the Company group in the same change.

## Deploy

`php artisan migrate` (nine migrations), then `php artisan permissions:sync` so the seeded
roles and templates receive the new area. The scheduler already runs every minute; the new
command joins it at 07:20. Switching the module off on System Settings → Modules hides every
equipment screen and tab.
