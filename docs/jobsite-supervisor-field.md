# Job Site Supervisor Field with Change History

## Overview
Added a **Supervisor** field to Job Sites, allowing users to assign an active system user as the supervisor. This is an optional field. Every supervisor change is tracked in a history log with who changed it, when, from whom to whom, and an optional note/reason. This allows tracking accountability — when problems arise, you can look up who was supervising at that time.

## Database Changes

### Migration 1: Add supervisor_id to job_sites
**File:** `database/migrations/2026_02_16_150000_add_supervisor_id_to_job_sites_table.php`

- Added `supervisor_id` column (nullable foreign key referencing `users` table)
- Uses `nullOnDelete` — if the assigned user is deleted, the field is set to null

```php
$table->foreignId('supervisor_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
```

### Migration 2: Create job_site_supervisor_histories table
**File:** `database/migrations/2026_02_16_150001_create_job_site_supervisor_histories_table.php`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Primary key |
| `job_site_id` | foreignId | FK to `job_sites`, cascadeOnDelete |
| `old_supervisor_id` | foreignId | Nullable FK to `users`, nullOnDelete |
| `new_supervisor_id` | foreignId | Nullable FK to `users`, nullOnDelete |
| `changed_by` | foreignId | FK to `users`, constrained |
| `note` | text | Nullable — optional reason for the change |
| `timestamps` | | created_at, updated_at |

## Model Changes

### JobSiteSupervisorHistory (new)
**File:** `app/Models/JobSiteSupervisorHistory.php`

- Fillable: `job_site_id`, `old_supervisor_id`, `new_supervisor_id`, `changed_by`, `note`
- Relationships: `jobSite()`, `oldSupervisor()`, `newSupervisor()`, `changedBy()`

### JobSite (updated)
**File:** `app/Models/JobSite.php`

- Added `supervisor_id` to `$fillable`
- Added `supervisor()` BelongsTo relationship
- Added `supervisorHistories()` HasMany relationship (ordered by `created_at` desc)
- Added `recordSupervisorChange()` method:

```php
public function recordSupervisorChange(User $changedBy, ?int $oldSupervisorId, ?int $newSupervisorId, ?string $note = null): void
```

## Component Changes

### ProjectJobSites
**File:** `app/Livewire/Project/ProjectJobSites.php`

- Added `$supervisor_id` and `$supervisor_change_note` properties
- Added validation rule: `'supervisor_id' => 'nullable|exists:users,id'`
- Passes active users (`User::where('status', UserStatus::ACTIVE)`) to the view
- Eager loads `supervisor` relationship in the job sites query
- On **create**: includes `supervisor_id`, records initial assignment if supervisor is set
- On **edit**: detects if supervisor changed, records history with optional note
- **Redirect changed:** After creating or editing a job site, redirects to `jobsites.overview` instead of staying on the list

### JobSiteOverview
**File:** `app/Livewire/JobSite/JobSiteOverview.php`

- Eager loads `supervisor`, `supervisorHistories.changedBy`, `supervisorHistories.oldSupervisor`, `supervisorHistories.newSupervisor`

## View Changes

### Job Sites Form & Table
**File:** `resources/views/livewire/project/project-job-sites.blade.php`

- Added **Supervisor** dropdown in a 2-column grid row with **Status**
- Dropdown populated with active users, default option "Select a supervisor"
- Added **Change Note** textarea (only visible when editing and supervisor has changed, using Alpine.js `x-effect`)
- Added **Supervisor** column in the job sites table

### Job Site Overview
**File:** `resources/views/livewire/job-site/job-site-overview.blade.php`

- Added **Supervisor** in the Job Site Information card (after Created By), shows name or "Not assigned"
- Added **Supervisor History** card in the sidebar with timeline-style entries:
  - Date and time
  - Old supervisor → New supervisor (or "Initial assignment" / "Removed")
  - Changed by user name
  - Note (if provided), shown in a subtle background box

## Translations

### English (`lang/en.json`)
```json
"Supervisor": "Supervisor",
"Select a supervisor": "Select a supervisor",
"Supervisor History": "Supervisor History",
"Change Note": "Change Note",
"Optional reason for this change...": "Optional reason for this change...",
"Initial assignment": "Initial assignment",
"Removed": "Removed",
"supervisor": "supervisor"
```

### Portuguese (`lang/pt_BR.json`)
```json
"Supervisor": "Supervisor",
"Select a supervisor": "Selecione um supervisor",
"Supervisor History": "Histórico de Supervisores",
"Change Note": "Nota da Alteração",
"Optional reason for this change...": "Motivo opcional para esta alteração...",
"Initial assignment": "Atribuição inicial",
"Removed": "Removido",
"supervisor": "supervisor"
```

## History Tracking Logic

| Action | old_supervisor_id | new_supervisor_id | Display |
|--------|-------------------|-------------------|---------|
| Initial assignment | null | user_id | "Initial assignment: John" |
| Change supervisor | user_id_A | user_id_B | "John → Jane" |
| Remove supervisor | user_id | null | "John → Removed" |

## Notes

- Only **active** users are shown in the dropdown (filtered by `UserStatus::ACTIVE`)
- The field is **optional** — validation is `nullable`
- Uses `nullOnDelete` on the FK — if the user is deleted, the supervisor is simply unset
- History records use `nullOnDelete` for old/new supervisor — deleted users show as null but the history entry is preserved
- History is ordered newest first in the overview sidebar
- The `changed_by` FK is constrained (not nullOnDelete) — the user who made the change must exist
