# Project-Level Resources Implementation

## Overview
This document tracks the implementation of making resources (Expenses, Change Orders, Daily Reports) available at both the Project level and Job Site level.

**Important Design Decision:** Some projects will not have any job sites - they operate at the project level only. This gives users flexibility to either:
- Use a single project without job sites (simpler workflow)
- Split a project into multiple job sites (for complex projects with multiple locations)

**Rule for Future Features:** Any new features that track work/costs/documents should be implemented at BOTH the project level AND job site level, following the dual foreign key pattern below.

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
- Supports both simple projects (no job sites) and complex projects (multiple job sites)

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

### 2. Change Orders ✅ Completed

#### Step 2.1: Database Migration ✅
- [x] Add `project_id` column to change_orders table
- [x] Make `job_site_id` nullable
- [x] Backfill `project_id` from existing job_site relationships

**File:** `database/migrations/2026_01_25_100000_add_project_id_to_change_orders_table.php`

#### Step 2.2: Update ChangeOrder Model ✅
- [x] Add `project_id` to `$fillable`
- [x] Add `project()` BelongsTo relationship
- [x] Add `isProjectLevel()` helper method

**File:** `app/Models/ChangeOrder.php`

#### Step 2.3: Update Project Model ✅
- [x] Add `changeOrders()` HasMany relationship (all change orders)
- [x] Add `projectLevelChangeOrders()` HasMany relationship (only project-level)

**File:** `app/Models/Project.php`

#### Step 2.4: Update ProjectShow Component ✅
- [x] Add Change Orders tab with full CRUD functionality
- [x] Load change orders for the project with relationships
- [x] Add change order form with location selector (Project General or Job Site)
- [x] Add filtering by location (All, Project, or specific Job Site)
- [x] Add search functionality
- [x] Support file uploads

**File:** `app/Livewire/Project/ProjectShow.php`

#### Step 2.5: Update ProjectShow View ✅
- [x] Add Change Orders tab button to navigation
- [x] Add Change Orders tab content with table showing Location column
- [x] Add change order modal for create/edit/view
- [x] Location selector in form

**File:** `resources/views/livewire/project/project-show.blade.php`

#### Step 2.6: Update JobSiteShow Component ✅
- [x] Include `project_id` when creating change orders

**File:** `app/Livewire/JobSite/JobSiteShow.php`

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

### Session 2 - 2026-01-25

**Goal:** Implement project-level change orders

**Changes Made:**

1. **Created Migration** (`2026_01_25_100000_add_project_id_to_change_orders_table.php`)
   - Added `project_id` foreign key to change_orders table
   - Backfilled `project_id` from existing job_site relationships
   - Made `job_site_id` nullable for project-level change orders

2. **Updated ChangeOrder Model** (`app/Models/ChangeOrder.php`)
   - Added `project_id` to `$fillable`
   - Added `project()` BelongsTo relationship
   - Added `isProjectLevel()` helper method

3. **Updated Project Model** (`app/Models/Project.php`)
   - Added `changeOrders()` HasMany relationship
   - Added `projectLevelChangeOrders()` HasMany relationship (filtered)

4. **Updated ProjectShow Component** (`app/Livewire/Project/ProjectShow.php`)
   - Added change order-related properties (search, filter, modal state, form fields)
   - Added change order CRUD methods (create, edit, view, delete)
   - Updated render() to pass change orders data to view

5. **Updated ProjectShow View** (`resources/views/livewire/project/project-show.blade.php`)
   - Added Change Orders tab button to navigation
   - Added complete Change Orders tab content:
     - Search bar and location filter
     - Summary card showing total change orders amount
     - Table with Location column (showing job site name or "Project (General)")
     - View/Edit/Delete actions
     - Empty state
   - Added change order modal for create/edit/view with:
     - Location selector (Project General or specific Job Site)
     - All change order form fields
     - File upload

6. **Updated JobSiteShow Component** (`app/Livewire/JobSite/JobSiteShow.php`)
   - Added `project_id` when saving change orders

**UI Features:**
- Location filter dropdown: "All Locations", "Project (General)", or specific job sites
- Location column in table shows job site name or "Project (General)" badge
- When adding change order, can choose "Project (General)" or assign to a job site
- Search works across titles and descriptions

### Session 3 - 2026-01-25

**Goal:** Implement project-level daily reports

**Changes Made:**

1. **Created Migration** (`2026_01_25_110000_add_project_id_to_daily_reports_table.php`)
   - Added `project_id` foreign key to daily_reports table
   - Backfilled `project_id` from existing job_site relationships
   - Made `job_site_id` nullable for project-level daily reports

2. **Updated DailyReport Model** (`app/Models/DailyReport.php`)
   - Added `project_id` to `$fillable`
   - Added `project()` BelongsTo relationship
   - Added `isProjectLevel()` helper method

3. **Updated Project Model** (`app/Models/Project.php`)
   - Added `dailyReports()` HasMany relationship
   - Added `projectLevelDailyReports()` HasMany relationship (filtered)

4. **Added Routes** (`routes/web.php`)
   - Added `projects/{project}/daily-reports/create` route
   - Added `projects/{project}/daily-reports/{dailyReport}/edit` route

5. **Updated DailyReportForm Component** (`app/Livewire/DailyReport/DailyReportForm.php`)
   - Added `$project` and `$context` properties
   - Updated `mount()` to accept both project and job site
   - Updated `save()` to include `project_id`
   - Updated `fetchWeather()` to use project coordinates if no job site
   - Added `redirectBack()` helper for context-aware redirects

6. **Updated DailyReportForm View** (`resources/views/livewire/daily-report/daily-report-form.blade.php`)
   - Updated breadcrumb to show project or job site context
   - Updated Report Information section for both contexts
   - Updated weather coordinate check message

7. **Updated ProjectShow Component** (`app/Livewire/Project/ProjectShow.php`)
   - Added Daily Reports tab with search and location filter
   - Added support for `tab` URL parameter
   - Added daily reports query with filters

8. **Updated ProjectShow View** (`resources/views/livewire/project/project-show.blade.php`)
   - Added Daily Reports tab button to navigation
   - Added complete Daily Reports tab content:
     - Search bar and location filter
     - Summary card showing total reports count
     - Table with Date, Location, Prepared By, Tasks, Status columns
     - Actions: View PDF, Download PDF, Edit
     - Empty state

**UI Features:**
- Location filter dropdown: "All Locations", "Project (General)", or specific job sites
- Location column in table shows job site name or "Project (General)" badge
- Status shows Editable, Read Only, or Locked
- Edit links route to correct form based on context (project or job site)
- PDF view and download available for all reports

---

### 3. Daily Reports ✅ Completed

#### Step 3.1: Database Migration ✅
- [x] Add `project_id` column to daily_reports table
- [x] Make `job_site_id` nullable
- [x] Backfill `project_id` from existing job_site relationships

**File:** `database/migrations/2026_01_25_110000_add_project_id_to_daily_reports_table.php`

#### Step 3.2: Update DailyReport Model ✅
- [x] Add `project_id` to `$fillable`
- [x] Add `project()` BelongsTo relationship
- [x] Add `isProjectLevel()` helper method

**File:** `app/Models/DailyReport.php`

#### Step 3.3: Update Project Model ✅
- [x] Add `dailyReports()` HasMany relationship (all daily reports)
- [x] Add `projectLevelDailyReports()` HasMany relationship (only project-level)

**File:** `app/Models/Project.php`

#### Step 3.4: Add Routes for Project-Level Daily Reports ✅
- [x] Add route `projects/{project}/daily-reports/create`
- [x] Add route `projects/{project}/daily-reports/{dailyReport}/edit`

**File:** `routes/web.php`

#### Step 3.5: Update DailyReportForm Component ✅
- [x] Support both project and job site contexts
- [x] Add `$project` and `$context` properties
- [x] Update `mount()` to accept either project or job site
- [x] Update `save()` to include `project_id`
- [x] Update weather fetching to use project coordinates if no job site
- [x] Update redirect logic for both contexts

**File:** `app/Livewire/DailyReport/DailyReportForm.php`

#### Step 3.6: Update DailyReportForm View ✅
- [x] Update breadcrumb navigation for both contexts
- [x] Update Report Information section to show location appropriately
- [x] Update weather coordinate check for both contexts

**File:** `resources/views/livewire/daily-report/daily-report-form.blade.php`

#### Step 3.7: Update ProjectShow Component ✅
- [x] Add Daily Reports tab with listing functionality
- [x] Add search and location filter
- [x] Support tab URL parameter for redirects
- [x] Load daily reports for the project with relationships

**File:** `app/Livewire/Project/ProjectShow.php`

#### Step 3.8: Update ProjectShow View ✅
- [x] Add Daily Reports tab button to navigation
- [x] Add Daily Reports tab content with table
- [x] Show Location column (job site name or "Project (General)")
- [x] Show status (Editable, Read Only, Locked)
- [x] Actions: View PDF, Download PDF, Edit (with correct route based on context)

**File:** `resources/views/livewire/project/project-show.blade.php`

---

## All Features Completed

All three resources (Expenses, Change Orders, Daily Reports) are now available at both Project and Job Site levels.

## Future Features Guideline

Any new features that track work, costs, or documents should follow this pattern:
1. Create migration to add `project_id` (required) and make `job_site_id` nullable
2. Update model with `project()` relationship and `isProjectLevel()` method
3. Update Project model with relationships
4. Add UI to ProjectShow (tab with CRUD or list with links)
5. Update any existing job-site-level forms to include `project_id`

---

## Other Session Logs

### Session 4 - 2026-01-25

**Goal:** Add "Preferred Supplier" field to Catalog Items (Products and Rentals)

**Changes Made:**

1. **Created Migration** (`2026_01_25_120000_add_supplier_id_to_catalog_items_table.php`)
   - Added `supplier_id` foreign key (nullable) to catalog_items table
   - References suppliers table with ON DELETE SET NULL

2. **Created Default Supplier Seeder** (`database/seeders/DefaultSupplierSeeder.php`)
   - Creates "General Supplier" with dummy values
   - Used for items without a specific preferred supplier

3. **Updated CatalogItem Model** (`app/Models/CatalogItem.php`)
   - Added `supplier_id` to `$fillable`
   - Added `supplier()` BelongsTo relationship

4. **Updated Supplier Model** (`app/Models/Supplier.php`)
   - Added `catalogItems()` HasMany relationship

5. **Updated CatalogItemCreate Component** (`app/Livewire/Catalog/CatalogItemCreate.php`)
   - Added `$supplier_id` property
   - Added validation rule for supplier_id
   - Added supplier_id to validation attributes
   - Updated save() to include supplier_id for products and rentals
   - Updated render() to pass suppliers to view

6. **Updated CatalogItemEdit Component** (`app/Livewire/Catalog/CatalogItemEdit.php`)
   - Added `$supplier_id` property
   - Updated mount() to load supplier_id from item
   - Added validation rule for supplier_id
   - Added supplier_id to validation attributes
   - Updated save() to include supplier_id for products and rentals
   - Updated render() to pass suppliers to view

7. **Updated CatalogItemCreate View** (`resources/views/livewire/catalog/catalog-item-create.blade.php`)
   - Added searchable Preferred Supplier dropdown (shows only for products and rentals)
   - Dropdown appears after Category field in Basic Information section
   - Uses Alpine.js for client-side search filtering

8. **Updated CatalogItemEdit View** (`resources/views/livewire/catalog/catalog-item-edit.blade.php`)
   - Added searchable Preferred Supplier dropdown (shows only for products and rentals)
   - Dropdown appears after Category field in Basic Information section
   - Uses Alpine.js for client-side search filtering

**UI Features:**
- Supplier dropdown only appears for Products and Rentals (not Services)
- Dropdown is optional - can be left blank
- Shows all available suppliers from the Supplier module
- Default "General Supplier" available via seeder for items without specific supplier
- **Searchable dropdown** with Alpine.js:
  - Type-to-search: Filter suppliers as you type
  - Click to select from the filtered list
  - Clear button (X) to remove the selection
  - Escape key closes the dropdown
  - Click outside closes the dropdown
  - Smooth fade in/out animations
  - Shows "No suppliers found" when no matches
  - Initializes with current value on edit page

**Commands to run:**
```bash
php artisan migrate
php artisan db:seed --class=DefaultSupplierSeeder
```
