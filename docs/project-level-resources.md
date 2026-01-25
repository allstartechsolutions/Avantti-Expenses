# Project-Level Resources Implementation

## Overview
This document tracks the implementation of making resources (Expenses, Change Orders, Daily Reports) available at both the Project level and Job Site level.

## Approach: Dual Foreign Keys
- Every resource **always** has a `project_id` (required)
- Resources **optionally** have a `job_site_id` (nullable)
- When `job_site_id` is null, the resource belongs directly to the project ("Project-level" or "General")
- When `job_site_id` is set, the resource belongs to that specific job site

## Benefits
- Simple queries: `Expense::where('project_id', $id)` gets all expenses
- Database-level foreign key constraints
- Backward compatible with existing data
- Clear pattern for future features

---

## Implementation Progress

### 1. Expenses ✅ Completed

#### Step 1.1: Database Migration ✅
- [x] Add `project_id` column to expenses table
- [x] Make `job_site_id` nullable
- [x] Backfill `project_id` from existing job_site relationships

**File:** `database/migrations/2026_01_24_170956_add_project_id_to_expenses_table.php`

#### Step 1.2: Update Expense Model ✅
- [x] Add `project()` relationship
- [x] Add `isProjectLevel()` helper method
- [x] Add `project_id` to fillable

**File:** `app/Models/Expense.php`

#### Step 1.3: Update Project Model ✅
- [x] Add `expenses()` relationship (all expenses)
- [x] Add `projectLevelExpenses()` relationship (only project-level)

**File:** `app/Models/Project.php`

#### Step 1.4: Update ProjectShow Component ✅
- [x] Add Expenses tab with full CRUD functionality
- [x] Load expenses for the project with relationships
- [x] Add expense form with location selector (Project General or Job Site)
- [x] Add filtering by location (All, Project, or specific Job Site)
- [x] Add search functionality
- [x] Support catalog items and custom items
- [x] Support receipt uploads

**File:** `app/Livewire/Project/ProjectShow.php`

#### Step 1.5: Update ProjectShow View ✅
- [x] Add Expenses tab button to navigation
- [x] Add Expenses tab content with table showing Location column
- [x] Add expense modal for create/edit/view
- [x] Location selector in form

**File:** `resources/views/livewire/project/project-show.blade.php`

#### Step 1.6: Update JobSiteShow Component ✅
- [x] Include `project_id` when creating expenses

**File:** `app/Livewire/JobSite/JobSiteShow.php`

---

### 2. Change Orders (Pending)
- [ ] Database migration (add `project_id`, make `job_site_id` nullable)
- [ ] Model updates
- [ ] UI updates (add Change Orders tab to ProjectShow)

### 3. Daily Reports (Pending)
- [ ] Database migration (add `project_id`, make `job_site_id` nullable)
- [ ] Model updates
- [ ] UI updates (add Daily Reports tab to ProjectShow)

---

## Session Log

### Session 1 - 2026-01-24

**Goal:** Implement project-level expenses

**Changes Made:**

1. **Created Migration** (`2026_01_24_170956_add_project_id_to_expenses_table.php`)
   - Added `project_id` foreign key to expenses table
   - Backfilled `project_id` from existing job_site relationships
   - Made `job_site_id` nullable for project-level expenses

2. **Updated Expense Model** (`app/Models/Expense.php`)
   - Added `project_id` to `$fillable`
   - Added `project()` BelongsTo relationship
   - Added `isProjectLevel()` helper method

3. **Updated Project Model** (`app/Models/Project.php`)
   - Added `expenses()` HasMany relationship
   - Added `projectLevelExpenses()` HasMany relationship (filtered)

4. **Updated ProjectShow Component** (`app/Livewire/Project/ProjectShow.php`)
   - Added `WithFileUploads` trait
   - Added expense-related properties (search, filter, modal state, form fields)
   - Added expense CRUD methods (create, edit, view, delete)
   - Added catalog item integration
   - Updated render() to pass expenses data to view

5. **Updated ProjectShow View** (`resources/views/livewire/project/project-show.blade.php`)
   - Added Expenses tab button to navigation
   - Added complete Expenses tab content:
     - Search bar and location filter
     - Summary card showing total expenses
     - Table with Location column (showing job site name or "Project (General)")
     - View/Edit/Delete actions
     - Empty state
   - Added expense modal for create/edit/view with:
     - Location selector (Project General or specific Job Site)
     - Catalog item search or custom item toggle
     - All expense form fields
     - Receipt upload

6. **Updated JobSiteShow Component** (`app/Livewire/JobSite/JobSiteShow.php`)
   - Added `project_id` when saving expenses

**UI Features:**
- Location filter dropdown: "All Locations", "Project (General)", or specific job sites
- Location column in table shows job site name or "Project (General)" badge
- When adding expense, can choose "Project (General)" or assign to a job site
- Search works across item names and notes

---

## Next Steps

To implement Change Orders and Daily Reports, follow the same pattern:

1. Create migration to add `project_id` and make `job_site_id` nullable
2. Update model with `project()` relationship and `isProjectLevel()` method
3. Update Project model with new relationships
4. Add tab and CRUD functionality to ProjectShow component
5. Update JobSiteShow to include `project_id` when creating records
