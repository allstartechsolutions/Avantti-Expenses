# Project Manager Field

## Overview
Added a **Project Manager** field to Projects, allowing users to assign an active system user as the project manager. This is an optional field — projects can exist without a project manager assigned.

## Database Changes

### Migration
**File:** `database/migrations/2026_02_16_132152_add_project_manager_id_to_projects_table.php`

- Added `project_manager_id` column (nullable foreign key referencing `users` table)
- Uses `nullOnDelete` — if the assigned user is deleted, the field is set to null instead of cascading

```php
$table->foreignId('project_manager_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
```

## Model Changes

### Project Model
**File:** `app/Models/Project.php`

- Added `project_manager_id` to `$fillable`
- Added `projectManager()` BelongsTo relationship

```php
public function projectManager(): BelongsTo
{
    return $this->belongsTo(User::class, 'project_manager_id');
}
```

## Component Changes

### ProjectCreate
**File:** `app/Livewire/Project/ProjectCreate.php`

- Added `$project_manager_id` property
- Added validation rule: `'project_manager_id' => 'nullable|exists:users,id'`
- Added validation attribute: `'project_manager_id' => __('project manager')`
- Passes active users (`User::where('status', UserStatus::ACTIVE)`) to the view
- Includes `project_manager_id` in the `Project::create()` call
- **Redirect changed:** After creating a project, redirects to `projects.overview` instead of `projects.index`

### ProjectEdit
**File:** `app/Livewire/Project/ProjectEdit.php`

- Same changes as ProjectCreate
- Populates `$project_manager_id` from existing project in `mount()`
- Includes `project_manager_id` in the `$this->project->update()` call
- **Redirect changed:** After editing a project, redirects to `projects.overview` instead of `projects.index`

### ProjectOverview
**File:** `app/Livewire/Project/ProjectOverview.php`

- Eager loads `projectManager` relationship in `mount()`

## View Changes

### Create & Edit Forms
**Files:**
- `resources/views/livewire/project/project-create.blade.php`
- `resources/views/livewire/project/project-edit.blade.php`

- Changed the Initial Amount + Status row from a 2-column to a 3-column grid
- Field order: **Initial Amount** → **Project Manager** → **Status**
- Project Manager is a `<select>` dropdown populated with active users
- Default option: "Select a project manager"

### Overview Page
**File:** `resources/views/livewire/project/project-overview.blade.php`

- Added "Project Manager" display after "Created By" in the Project Information card
- Shows user name if assigned, or "Not assigned" if null

## Translations

### English (`lang/en.json`)
```json
"Project Manager": "Project Manager",
"Select a project manager": "Select a project manager",
"Not assigned": "Not assigned",
"project manager": "project manager"
```

### Portuguese (`lang/pt_BR.json`)
```json
"Project Manager": "Gerente do Projeto",
"Select a project manager": "Selecione um gerente de projeto",
"Not assigned": "Não atribuído",
"project manager": "gerente do projeto"
```

## Post-Create/Edit Redirect Change

Both `ProjectCreate` and `ProjectEdit` now redirect to the **Project Overview** page after saving, instead of the projects index. This provides a better UX — the user immediately sees the project they just created/edited.

## Notes

- Only **active** users are shown in the dropdown (filtered by `UserStatus::ACTIVE`)
- The field is **optional** — validation is `nullable`
- Uses `nullOnDelete` on the FK — if the user is deleted, the project manager is simply unset rather than causing errors
