# Phase 2: Project/Job Site Budgets

**Status:** Complete
**Date:** 2026-01-26
**Depends on:** Phase 1 (Cost Code Templates) - Complete

---

## Overview

This phase implements the Budget module that allows users to create budgets for projects and job sites using cost code templates. Budgets track internal cost allocation and are independent copies from templates.

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Budgets per location | One budget per location | Simpler, covers most use cases |
| Total amount | Auto-calculated | Sum of budget items - ensures consistency |
| UI approach | Tab on Project/JobSite + standalone pages | Quick access from context, detailed editing on own pages |
| Template relationship | Reference only | Budget items are independent copies |
| Notes field | Yes | User requested for additional context |

---

## Future Integration Notes

> **Important:** After completing this module, the following integrations are planned:
> - **Change Orders:** Approved change orders can be allocated to budget items, increasing the revised budget amount
> - **Expenses:** Expense items will link to budget items to track actual spending
> - **Purchase Orders:** PO items will link to budget items to track committed costs
>
> These integrations will add computed fields to track:
> - `revised_amount` = `budgeted_amount` + approved change orders
> - `committed_amount` = sum of approved PO items
> - `actual_amount` = sum of expense items
> - `remaining_amount` = `revised_amount` - `actual_amount`

---

## Database Schema

### Table: `budgets`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `project_id` | foreignId | Always required (cascade on delete) |
| `job_site_id` | foreignId (nullable) | Null = project-level budget (cascade on delete) |
| `name` | string(255) | Budget name (e.g., "Main Budget", "Phase 1 Budget") |
| `notes` | text (nullable) | Additional notes/context |
| `source_template_id` | foreignId (nullable) | Reference to original template (set null on delete) |
| `created_by` | foreignId | User who created (cascade on delete) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Note:** No `total_amount` column - total is calculated as sum of budget items.

**Indexes:**
- `unique(['project_id', 'job_site_id'])` - One budget per location (null counts as unique for project-level)
- `index(['project_id'])` - Fast project queries

### Table: `budget_items`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `budget_id` | foreignId | Parent budget (cascade on delete) |
| `parent_id` | foreignId (nullable) | Self-reference for hierarchy |
| `code` | string(50) | Cost code (copied from template) |
| `name` | string(255) | Cost code name |
| `description` | text (nullable) | Description |
| `budgeted_amount` | unsignedBigInteger | Allocated amount in cents |
| `sort_order` | integer | Display order (default: 0) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique(['budget_id', 'code'])` - Code must be unique within budget
- `index(['budget_id', 'parent_id'])` - Fast hierarchy queries

---

## Models

### Budget

**Location:** `app/Models/Budget.php`

**Relationships:**
- `project()` - BelongsTo Project
- `jobSite()` - BelongsTo JobSite (nullable)
- `sourceTemplate()` - BelongsTo CostCodeTemplate (nullable)
- `creator()` - BelongsTo User
- `items()` - HasMany BudgetItem
- `parentItems()` - HasMany BudgetItem (where parent_id is null, ordered)

**Computed Attributes:**
- `total_amount` - Sum of all items' budgeted_amount (in dollars)
- `is_project_level` - Returns true if job_site_id is null
- `location_name` - Returns job site name or "Project (General)"

**Methods:**
- `isProjectLevel()` - Returns true if no job_site_id
- `applyTemplate(CostCodeTemplate $template)` - Copies cost codes from template
- `getTotalAmountAttribute()` - Calculates sum of items

### BudgetItem

**Location:** `app/Models/BudgetItem.php`

**Relationships:**
- `budget()` - BelongsTo Budget
- `parent()` - BelongsTo BudgetItem (self)
- `children()` - HasMany BudgetItem (self, ordered by sort_order)

**Accessors:**
- `budgeted_amount` - Converts cents to dollars (get/set)
- `full_code` - Parent code + this code
- `full_name` - Parent name > this name

**Methods:**
- `isParent()` - Returns true if parent_id is null
- `isChild()` - Returns true if parent_id is not null
- `hasChildren()` - Returns true if children exist

---

## Routes

```php
// Budget routes (standalone pages)
Route::get('/budgets/{budget}', BudgetShow::class)->name('budgets.show');
Route::get('/budgets/{budget}/edit', BudgetEdit::class)->name('budgets.edit');

// Create budget (context-aware)
Route::get('/projects/{project}/budgets/create', BudgetCreate::class)->name('projects.budgets.create');
Route::get('/job-sites/{jobSite}/budgets/create', BudgetCreate::class)->name('job-sites.budgets.create');
```

---

## Livewire Components

### 1. BudgetCreate

**Location:** `app/Livewire/Budget/BudgetCreate.php`
**View:** `resources/views/livewire/budget/budget-create.blade.php`

**Features:**
- Works for both project and job site contexts
- Form fields: name, notes
- Template selector (optional - can start blank)
- If template selected, copies cost codes on save (with $0.00 amounts)
- Redirects to BudgetShow after creation to add amounts

### 2. BudgetShow

**Location:** `app/Livewire/Budget/BudgetShow.php`
**View:** `resources/views/livewire/budget/budget-show.blade.php`

**Features:**
- Display budget details (name, notes, location)
- Hierarchical display of budget items (parent + children)
- Show total (sum of all items)
- Summary card: Total Budget (auto-calculated)
- Add cost code (parent or child)
- Edit amount inline or via sidebar
- Delete cost code (only if no children)
- Link back to project/job site
- Edit button → BudgetEdit page

### 3. BudgetEdit

**Location:** `app/Livewire/Budget/BudgetEdit.php`
**View:** `resources/views/livewire/budget/budget-edit.blade.php`

**Features:**
- Edit budget name, notes
- Full cost code management (same as BudgetShow)
- Import from template (merge or replace)
- Delete budget (with confirmation)
- Back to BudgetShow

---

## Project/Job Site Integration

### ProjectShow Updates

**File:** `app/Livewire/Project/ProjectShow.php`

Add new tab "Budget" that shows:
- If budget exists for project-level:
  - Budget summary card (name, total calculated from items)
  - View Details button → BudgetShow
- List of job site budgets (if any)
  - Job site name, budget total, link to BudgetShow
- Create Budget button (if no project-level budget exists)

### JobSiteShow Updates

**File:** `app/Livewire/JobSite/JobSiteShow.php`

Add new tab "Budget" that shows:
- If budget exists:
  - Budget summary card
  - View Details button → BudgetShow
  - Edit button → BudgetEdit
- Create Budget button (if no budget exists)

---

## UI Specifications

### Budget Tab on Project Show

```
┌─────────────────────────────────────────────────────────────────────┐
│ Budget                                                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Project Budget                              [View Details] [Edit]│ │
│ │ Total: $145,000.00 (auto-calculated from 12 cost codes)         │ │
│ │ Template: Commercial Construction                                │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ Job Site Budgets                                                    │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Site A          │ $50,000.00  │ 8 cost codes  │ [View]          │ │
│ │ Site B          │ $75,000.00  │ 10 cost codes │ [View]          │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ [+ Create Project Budget] (if none exists)                          │
└─────────────────────────────────────────────────────────────────────┘
```

### Budget Show Page

```
┌─────────────────────────────────────────────────────────────────────┐
│ ← Back to Project    Main Budget                              [Edit]│
├─────────────────────────────────────────────────────────────────────┤
│ Location: Project (General)                                         │
│ Template: Commercial Construction                                   │
│ Notes: Initial budget for Phase 1 construction                      │
├─────────────────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────────────────────────┐    │
│ │                      Total Budget                            │    │
│ │                      $145,000.00                             │    │
│ │                   (12 cost codes)                            │    │
│ └──────────────────────────────────────────────────────────────┘    │
├─────────────────────────────────────────────────────────────────────┤
│ [+ Add Cost Code]                                                   │
│                                                                     │
│ Code        │ Name                    │ Budgeted Amount │ Actions   │
│─────────────┼─────────────────────────┼─────────────────┼───────────│
│ ▼ 01        │ General Requirements    │    $25,000.00   │ [+] [✎]   │
│   01.1      │ Summary of Work         │    $10,000.00   │     [✎]   │
│   01.2      │ Price and Payment       │     $5,000.00   │     [✎]   │
│   01.3      │ Administrative          │    $10,000.00   │     [✎]   │
│ ▼ 02        │ Site Construction       │    $50,000.00   │ [+] [✎]   │
│   02.1      │ Site Preparation        │    $20,000.00   │     [✎]   │
│   02.2      │ Demolition              │    $30,000.00   │     [✎]   │
│ ▶ 03        │ Concrete                │    $70,000.00   │ [+] [✎]   │
│─────────────┼─────────────────────────┼─────────────────┼───────────│
│             │                   Total │   $145,000.00   │           │
└─────────────────────────────────────────────────────────────────────┘
```

### Budget Create Page

```
┌─────────────────────────────────────────────────────────────────────┐
│ ← Back to Project    Create Budget                                  │
├─────────────────────────────────────────────────────────────────────┤
│ Location: Project (General)                                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ Name *              [Main Budget                          ]         │
│                                                                     │
│ Notes               [                                     ]         │
│                     [                                     ]         │
│                                                                     │
│ ─────────────────────────────────────────────────────────           │
│ Cost Code Template (Optional)                                       │
│                                                                     │
│ ○ Start with blank budget (add cost codes manually)                 │
│ ● Use template: [Commercial Construction        ▼]                  │
│                                                                     │
│   Cost codes from template will be copied with $0.00 amounts.       │
│   You can set amounts after creation.                               │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                    [Cancel]  [Create Budget]        │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Files to Create

```
database/migrations/
├── 2026_01_26_150000_create_budgets_table.php
└── 2026_01_26_150001_create_budget_items_table.php

app/Models/
├── Budget.php
└── BudgetItem.php

app/Livewire/Budget/
├── BudgetCreate.php
├── BudgetShow.php
└── BudgetEdit.php

resources/views/livewire/budget/
├── budget-create.blade.php
├── budget-show.blade.php
└── budget-edit.blade.php
```

---

## Files to Modify

```
app/Models/Project.php          - Add budget() and budgets() relationships
app/Models/JobSite.php          - Add budget() relationship
app/Livewire/Project/ProjectShow.php    - Add Budget tab
app/Livewire/JobSite/JobSiteShow.php    - Add Budget tab
resources/views/livewire/project/project-show.blade.php   - Add Budget tab UI
resources/views/livewire/job-site/job-site-show.blade.php - Add Budget tab UI
routes/web.php                  - Add budget routes
```

---

## Implementation Order

### Step 1: Database & Models
1. Create `budgets` migration
2. Create `budget_items` migration
3. Run migrations
4. Create `Budget` model
5. Create `BudgetItem` model
6. Update `Project` model with relationships
7. Update `JobSite` model with relationships

### Step 2: Budget Create Page
1. Create `BudgetCreate` component
2. Create view
3. Add routes
4. Test creation (blank and with template)

### Step 3: Budget Show Page
1. Create `BudgetShow` component
2. Create view with hierarchical display
3. Implement add/edit/delete cost codes
4. Add route
5. Test functionality

### Step 4: Budget Edit Page
1. Create `BudgetEdit` component
2. Create view
3. Implement template import (merge/replace)
4. Add route
5. Test functionality

### Step 5: Project Integration
1. Update `ProjectShow` component (add Budget tab)
2. Update view (Budget tab UI)
3. Test flow: Project → Budget tab → Create/View

### Step 6: Job Site Integration
1. Update `JobSiteShow` component (add Budget tab)
2. Update view (Budget tab UI)
3. Test flow: Job Site → Budget tab → Create/View

---

## Validation Rules

### Budget
```php
[
    'name' => 'required|string|max:255',
    'notes' => 'nullable|string|max:2000',
    'source_template_id' => 'nullable|exists:cost_code_templates,id',
]
```

### Budget Item
```php
[
    'code' => 'required|string|max:50', // + unique within budget
    'name' => 'required|string|max:255',
    'description' => 'nullable|string|max:1000',
    'budgeted_amount' => 'required|numeric|min:0',
]
```

---

## Testing Checklist

**Ready for testing:**

- [ ] Create project-level budget (blank)
- [ ] Create project-level budget (from template)
- [ ] Create job site budget
- [ ] View budget details
- [ ] Verify total auto-calculates correctly
- [ ] Add parent cost code
- [ ] Add child cost code
- [ ] Edit cost code amount (verify total updates)
- [ ] Delete cost code (without children)
- [ ] Delete cost code protection (with children)
- [ ] Edit budget details (name, notes)
- [ ] Import template (merge mode)
- [ ] Import template (replace mode)
- [ ] Delete budget
- [ ] Verify one budget per location constraint
- [ ] Test currency formatting
- [ ] Test from Project show page (Budget tab)
- [ ] Test from Job Site show page (Budget tab)
