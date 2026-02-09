# JobSite: Tabs to Pages Migration

## Overview

Refactoring the JobSite detail view from a single monolithic component with tabs (`JobSiteShow`) to separate page components with their own routes — matching the pattern already used for Projects.

## Why

- `JobSiteShow.php` was ~800 lines handling 6 tabs in a single component
- Projects were already migrated to this pages pattern successfully
- Separate pages are easier to maintain, test, and have better performance (less state per component)
- Browser history/back button works naturally with real pages

## Architecture

### Shared Layout Components

| Component | File | Purpose |
|-----------|------|---------|
| `<x-jobsite-layout>` | `resources/views/components/jobsite-layout.blade.php` | Breadcrumbs, header, success message, nav slot, content slot |
| `<x-jobsite-nav>` | `resources/views/components/jobsite-nav.blade.php` | Horizontal tab-style nav with `<a>` links (not `wire:click`) |

Props for `<x-jobsite-layout>`: `$jobSite`, `$active`, `$title`
Props for `<x-jobsite-nav>`: `$jobSite`, `$active`

### Page Components

| # | Component | Route | Route Name | Status |
|---|-----------|-------|------------|--------|
| 1 | `JobSiteOverview` | `GET /job-sites/{jobSite}` | `jobsites.overview` | Done |
| 2 | `JobSiteExpenses` | `GET /job-sites/{jobSite}/expenses` | `jobsites.expenses` | Pending |
| 3 | `JobSiteChangeOrders` | `GET /job-sites/{jobSite}/change-orders` | `jobsites.change-orders` | Pending |
| 4 | `JobSiteDailyReports` | `GET /job-sites/{jobSite}/daily-reports` | `jobsites.daily-reports` | Pending |
| 5 | `JobSitePurchaseOrders` | `GET /job-sites/{jobSite}/purchase-orders` | `jobsites.purchase-orders` | Pending |
| 6 | `JobSiteBudget` | `GET /job-sites/{jobSite}/budget` | `jobsites.budget` | Pending |

### Legacy Support

- `JobSiteShow` remains at `GET /job-sites/{jobSite}/show` (`jobsites.show`)
- Routes 2-6 temporarily point to `JobSiteShow` until each page component is built
- `JobSiteShow` detects the URL segment in `mount()` to set the correct active tab and nav highlight
- `JobSiteShow` blade now uses `<x-jobsite-layout>` for consistent navigation

---

## Session Log

### Session 1 - 2026-02-04

**Goal:** Set up shared layout components, create JobSiteOverview page, and update JobSiteShow to use the new layout

**New Files Created:**

1. **`resources/views/components/jobsite-layout.blade.php`**
   - Shared layout with breadcrumbs (Projects > Project Name > Job Sites > Job Site Name > Section)
   - Page header with title and "Back to Project" button
   - Optional `actions` slot in header for injecting buttons (e.g., Delete button)
   - Success message flash
   - `<x-jobsite-nav>` integration
   - Content slot

2. **`resources/views/components/jobsite-nav.blade.php`**
   - Horizontal nav matching project-nav pattern
   - 6 menu items: Overview, Expenses, Change Orders, Purchase Orders, Daily Reports, Budget
   - Uses `<a href>` links to actual routes (not `wire:click`)
   - Active tab highlighting with brand colors

3. **`app/Livewire/JobSite/JobSiteOverview.php`**
   - Lightweight component receiving `JobSite` via route model binding
   - Loads `project`, `createdBy` relationships
   - Queries change orders and expenses for summary totals
   - Delete functionality with confirmation modal, file cleanup, and DB transaction (see `docs/delete-functionality.md`)
   - Layout: `components.layouts.app`

4. **`resources/views/livewire/job-site/job-site-overview.blade.php`**
   - Summary cards: Total Contract Value, Total Expenses, Profit/Loss
   - Job Site Information card (name, project, amount, status, dates)
   - Contact Information card
   - Address Information card (with BR/US country support)
   - Sidebar: Expenses summary with link to expenses page, Change Orders summary with link

**Files Modified:**

5. **`routes/web.php`**
   - Added import for `JobSiteOverview`
   - Added `jobsites.overview` route at `GET /job-sites/{jobSite}`
   - Added placeholder routes for `jobsites.expenses`, `jobsites.change-orders`, `jobsites.purchase-orders`, `jobsites.daily-reports`, `jobsites.budget` (temporarily pointing to `JobSiteShow`)
   - Moved legacy `jobsites.show` route to `GET /job-sites/{jobSite}/show`

6. **`app/Livewire/JobSite/JobSiteShow.php`**
   - Added `$activeNavTab` property for nav highlighting
   - Updated `mount()` to detect URL segment and set correct `$activeTab` + `$activeNavTab`
   - Maps URL segments (e.g., `change-orders`) to internal tab names (e.g., `changeorders`)

7. **`resources/views/livewire/job-site/job-site-show.blade.php`**
   - Replaced old header + breadcrumbs + tab buttons with `<x-jobsite-layout>`
   - Removed overview tab content (now in its own page)
   - Replaced closing `</div>` with `</x-jobsite-layout>`
   - Remaining tabs (expenses, change orders, daily reports, purchase orders, budget) still use `@if($activeTab === ...)` conditionals

8. **Updated `jobsites.show` references to `jobsites.overview`** in:
   - `resources/views/livewire/project/project-job-sites.blade.php`
   - `resources/views/livewire/project/project-budget.blade.php`
   - `resources/views/livewire/project/project-show.blade.php` (2 references)
   - `resources/views/livewire/budget/budget-show.blade.php`
   - `resources/views/livewire/daily-report/daily-report-form.blade.php`
   - `app/Livewire/Budget/BudgetShow.php`
   - `app/Livewire/Budget/BudgetEdit.php`
   - `app/Livewire/DailyReport/DailyReportForm.php`

**References left for future steps** (still pointing to `jobsites.show` with tab parameters):
   - `resources/views/livewire/expense/expense-create.blade.php` → will update to `jobsites.expenses`
   - `app/Livewire/Expense/ExpenseCreate.php` → will update to `jobsites.expenses`
   - `resources/views/livewire/purchase-order/purchase-order-create.blade.php` → will update to `jobsites.purchase-orders`
   - `resources/views/livewire/purchase-order/purchase-order-show.blade.php` → will update to `jobsites.purchase-orders`
   - `resources/views/livewire/budget/budget-create.blade.php` → will update to `jobsites.budget`

---

## Next Steps

Build each page component one at a time, test, then move to the next:

1. **JobSiteExpenses** — Extract expense logic (search, create/edit/view modals, payment handling) from `JobSiteShow`
2. **JobSiteChangeOrders** — Extract change order logic (search, create/edit/view modals)
3. **JobSiteDailyReports** — Extract daily reports list (links to create/edit form)
4. **JobSitePurchaseOrders** — Extract purchase orders list + stats
5. **JobSiteBudget** — Extract budget view
6. **Cleanup** — Remove `JobSiteShow` once all pages are migrated, update remaining route references
