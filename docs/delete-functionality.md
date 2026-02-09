# Delete Functionality: Projects & Job Sites

## Overview

Delete functionality for Projects and Job Sites with confirmation modals that warn about irreversible data loss. Modals display counts of all related data that will be permanently deleted. All associated files are cleaned up from storage before the database records are cascade-deleted.

---

## Key Design Decisions

### Why Not `wire:confirm`?

A simple `wire:confirm` browser dialog was explicitly avoided. Instead, a proper `<x-ui.modal>` is used to:
- Show a warning icon and bold "cannot be undone" message
- List all related data that will be permanently deleted (with counts)
- Provide Cancel and Delete buttons within the modal

### Why Manual File Cleanup?

Database cascades are defined at the **DB level** (`cascadeOnDelete()` in migrations), not Eloquent level. This means Eloquent model `boot()` events (like the file cleanup in `Expense` and `PurchaseOrder` models) **will not fire** when parent records are deleted via cascade. Files must be collected and deleted **before** the parent record is deleted.

### DB Transactions

All delete operations are wrapped in `DB::transaction()` to ensure consistency. If any step fails, the entire operation is rolled back.

---

## Files That Need Cleanup Before Deletion

| Model | File Column | Context |
|-------|------------|---------|
| `Expense` | `receipt_path` | Has `boot()` cleanup, but won't fire on cascade |
| `ChangeOrder` | `file_path` | No boot cleanup |
| `PurchaseOrder` | `receipt_path` | Has `boot()` cleanup, but won't fire on cascade |
| `DailyReportImage` | `file_path` | Polymorphic (`morphMany`), linked to `DailyReport` and `DailyReportManpower` |

---

## Implementation Details

### Project Delete

#### Where Delete Buttons Appear

| Page | Component | Route | Button Location |
|------|-----------|-------|-----------------|
| Projects Index | `ProjectIndex` | `projects.index` | Actions column in table (alongside View/Edit) |
| Project Overview | `ProjectOverview` | `projects.overview` | Header via `<x-slot:actions>` in `<x-project-layout>` |

#### Related Data Shown in Modal

The confirmation modal counts and displays:
- Job Sites
- Expenses
- Change Orders
- Daily Reports
- Purchase Orders
- Budgets

#### File Cleanup Method (`cleanupProjectFiles`)

```php
protected function cleanupProjectFiles($projectId)
{
    // 1. Expense receipt files
    Expense::where('project_id', $projectId)
        ->whereNotNull('receipt_path')
        ->pluck('receipt_path')
        ->each(fn($path) => Storage::delete($path));

    // 2. Change order files
    ChangeOrder::where('project_id', $projectId)
        ->whereNotNull('file_path')
        ->pluck('file_path')
        ->each(fn($path) => Storage::delete($path));

    // 3. Purchase order receipt files
    PurchaseOrder::where('project_id', $projectId)
        ->whereNotNull('receipt_path')
        ->pluck('receipt_path')
        ->each(fn($path) => Storage::delete($path));

    // 4. Daily report images (polymorphic)
    //    - DailyReport images
    //    - DailyReportManpower images
}
```

#### After Deletion

Redirects to `route('projects.index')` with a success flash message.

---

### Job Site Delete

#### Where Delete Buttons Appear

| Page | Component | Route | Button Location |
|------|-----------|-------|-----------------|
| Project Job Sites | `ProjectJobSites` | `projects.jobsites` | Actions column in table (alongside View/Edit) |
| Job Site Overview | `JobSiteOverview` | `jobsites.overview` | Header via `<x-slot:actions>` in `<x-jobsite-layout>` |

#### Related Data Shown in Modal

The confirmation modal counts and displays:
- Expenses
- Change Orders
- Daily Reports
- Budgets

> **Note:** Purchase Orders are NOT listed for Job Site delete because POs use `nullOnDelete` for `job_site_id` (they remain at the project level when a job site is deleted).

#### File Cleanup Method (`cleanupJobSiteFiles`)

```php
protected function cleanupJobSiteFiles($jobSiteId)
{
    // 1. Expense receipt files
    Expense::where('job_site_id', $jobSiteId)
        ->whereNotNull('receipt_path')
        ->pluck('receipt_path')
        ->each(fn($path) => Storage::delete($path));

    // 2. Change order files
    ChangeOrder::where('job_site_id', $jobSiteId)
        ->whereNotNull('file_path')
        ->pluck('file_path')
        ->each(fn($path) => Storage::delete($path));

    // 3. Daily report images (polymorphic)
    //    - DailyReport images
    //    - DailyReportManpower images
}
```

#### After Deletion

- From `ProjectJobSites`: stays on the same page (list refreshes)
- From `JobSiteOverview`: redirects to `route('projects.jobsites', $projectId)` with a success flash message

---

## Files Modified

### PHP Components

| File | Changes |
|------|---------|
| `app/Livewire/Project/ProjectIndex.php` | Added `confirmDeleteProject()`, `deleteProject()`, `cancelDelete()`, `cleanupProjectFiles()` methods and modal state properties |
| `app/Livewire/Project/ProjectOverview.php` | Added `confirmDeleteProject()`, `deleteProject()`, `cancelDeleteProject()`, `cleanupProjectFiles()` methods and modal state properties |
| `app/Livewire/Project/ProjectJobSites.php` | Added `confirmDeleteJobSite()`, `deleteJobSite()`, `cancelDeleteJobSite()`, `cleanupJobSiteFiles()` methods and modal state properties |
| `app/Livewire/JobSite/JobSiteOverview.php` | Added `confirmDeleteJobSite()`, `deleteJobSite()`, `cancelDeleteJobSite()`, `cleanupJobSiteFiles()` methods and modal state properties |

### Blade Views

| File | Changes |
|------|---------|
| `resources/views/livewire/project/project-index.blade.php` | Added Delete button in actions column, added delete confirmation modal |
| `resources/views/livewire/project/project-overview.blade.php` | Added Delete button via `<x-slot:actions>`, added delete confirmation modal |
| `resources/views/livewire/project/project-job-sites.blade.php` | Added Delete button per job site row, added delete confirmation modal |
| `resources/views/livewire/job-site/job-site-overview.blade.php` | Added Delete button via `<x-slot:actions>`, added delete confirmation modal |

### Shared Layouts

| File | Changes |
|------|---------|
| `resources/views/components/project-layout.blade.php` | Added optional `actions` slot in the header for injecting buttons |
| `resources/views/components/jobsite-layout.blade.php` | Added optional `actions` slot in the header for injecting buttons |

---

## Component Properties & Methods

### Project Delete (used in `ProjectIndex` and `ProjectOverview`)

```php
// Properties
public $showDeleteModal = false;          // ProjectIndex
public $showDeleteProjectModal = false;   // ProjectOverview
public $deletingProjectId = null;         // ProjectIndex only
public $deleteProjectData = [];

// Methods
public function confirmDeleteProject($projectId)  // Loads counts, opens modal
public function deleteProject()                     // Cleans files, deletes, redirects
public function cancelDelete()                      // ProjectIndex
public function cancelDeleteProject()               // ProjectOverview
protected function cleanupProjectFiles($projectId)  // File cleanup
```

### Job Site Delete (used in `ProjectJobSites` and `JobSiteOverview`)

```php
// Properties
public $showDeleteJobSiteModal = false;
public $deletingJobSiteId = null;         // ProjectJobSites only
public $deleteJobSiteData = [];

// Methods
public function confirmDeleteJobSite($jobSiteId)    // Loads counts, opens modal
public function deleteJobSite()                      // Cleans files, deletes
public function cancelDeleteJobSite()                // Closes modal, resets state
protected function cleanupJobSiteFiles($jobSiteId)   // File cleanup
```

---

## Modal Pattern

The modals follow a consistent pattern across all pages:

```blade
@if($showDeleteModal)
    <x-ui.modal name="delete-modal-name" :show="true" maxWidth="lg">
        <div class="p-6">
            {{-- Warning icon (red circle with exclamation) --}}
            {{-- Title: "Delete Project" or "Delete Job Site" --}}
            {{-- Message: "Are you sure? This action cannot be undone." --}}

            {{-- Related data list (conditionally shown) --}}
            @if($hasRelated)
                <div class="bg-red-50 ...">
                    <p>The following data will be permanently deleted:</p>
                    <ul>
                        {{-- X icon + count for each data type --}}
                    </ul>
                </div>
            @endif

            {{-- Cancel and Delete buttons --}}
        </div>
    </x-ui.modal>
@endif
```

**Modal lifecycle:**
1. `confirmDelete*()` loads data counts, sets `$showDeleteModal = true`, dispatches `open-modal`
2. `cancelDelete*()` sets `$showDeleteModal = false`, dispatches `close-modal`
3. `delete*()` performs cleanup + deletion, resets state

---

## Actions Slot in Shared Layouts

Both `project-layout.blade.php` and `jobsite-layout.blade.php` now support an optional `actions` slot for injecting buttons into the page header:

```blade
{{-- In the layout component --}}
<div class="flex items-center space-x-3">
    <x-ui.button variant="secondary" href="..." icon="arrow-left">Back</x-ui.button>
    <x-ui.button variant="primary" href="..." icon="edit">Edit</x-ui.button>
    @if(isset($actions))
        {{ $actions }}
    @endif
</div>
```

```blade
{{-- Usage in a page component --}}
<x-project-layout :project="$project" active="overview" title="Project Details">
    <x-slot:actions>
        <x-ui.button variant="danger" wire:click="confirmDeleteProject" icon="trash">
            Delete
        </x-ui.button>
    </x-slot:actions>

    {{-- Page content --}}
</x-project-layout>
```

This pattern can be reused for any future buttons that need to appear in the header area of project or job site pages.

---

## Important Notes

### Polymorphic Image Cleanup

`DailyReportImage` uses a polymorphic `morphMany` relationship. Images can be linked to:
- `DailyReport` (via `imageable_type = DailyReport::class`)
- `DailyReportManpower` (via `imageable_type = DailyReportManpower::class`)

Both types must be cleaned up. The cleanup logic:
1. Get all `DailyReport` IDs for the project/job site
2. Delete images where `imageable_id` is in those IDs and `imageable_type` is `DailyReport`
3. Get all `DailyReportManpower` IDs for those daily reports
4. Delete images where `imageable_id` is in those manpower IDs and `imageable_type` is `DailyReportManpower`

### Legacy Pages

The legacy `ProjectShow` and `JobSiteShow` (tab-based) components also have delete functionality for backward compatibility. These will be removed once the tab-to-page migration is fully complete.

### Adding New File-Bearing Models

If a new model is added in the future that stores files (e.g., Subcontractor Documents), remember to:
1. Add cleanup logic to both `cleanupProjectFiles()` and `cleanupJobSiteFiles()` (if applicable)
2. Follow the same pattern: query file paths, iterate and delete from Storage, then let cascade handle DB records
