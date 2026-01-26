# Budget & Cost Code System - Technical Specification

## Overview

This document outlines the implementation of a comprehensive Budget and Cost Code system for tracking project finances. The system will be built in phases, with each phase being a standalone deliverable.

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      COST CODE TEMPLATES                            │
│  (Global templates that can be applied to projects/job sites)       │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PROJECT / JOB SITE                               │
│  ┌─────────────────┐    ┌─────────────────┐                        │
│  │  Project Budget │    │ Job Site Budget │                        │
│  │  (Total + Code) │    │ (Total + Code)  │                        │
│  └────────┬────────┘    └────────┬────────┘                        │
│           │                      │                                  │
│           ▼                      ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐       │
│  │              APPLIED COST CODES                          │       │
│  │  (Copied from template, customizable per project)        │       │
│  └─────────────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    ▼             ▼             ▼
              ┌──────────┐ ┌──────────┐ ┌──────────────┐
              │ Expenses │ │   POs    │ │Change Orders │
              │ (Items)  │ │ (Items)  │ │   (Items)    │
              └──────────┘ └──────────┘ └──────────────┘
```

---

## Implementation Phases

| Phase | Feature | Dependencies |
|-------|---------|--------------|
| 1 | Cost Code Templates | None |
| 2 | Project/Job Site Cost Codes | Phase 1 |
| 3 | Budget System | Phase 2 |
| 4 | Expense Refactor (Items + Cost Codes) | Phase 3 |
| 5 | Purchase Orders | Phase 3, 4 |
| 6 | Change Order Budget Integration | Phase 3 |
| 7 | Budget Reports & Dashboard | All phases |

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|------------|
| Maximum hierarchy depth | 2 levels | Keep it simple: Parent → Child only |
| Code format | Flexible | No strict validation, user decides format |
| Import/Export | CSV required | Essential for template management |
| Template scope | Global | Templates are reusable across all projects |
| Budget tracking | Per cost code | Budgeted, Revised, Committed, Actual, Remaining |

---

# PHASE 1: Cost Code Templates

## Goal
Create a system for managing reusable cost code templates that can be applied to projects and job sites.

## Database Schema

### Table: `cost_code_templates`

Master templates (e.g., "Commercial Construction", "Residential", "Service Work")

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string(255) | Template name (unique) |
| `description` | text (nullable) | Template description |
| `is_active` | boolean | Active status (default: true) |
| `is_default` | boolean | Default template for new projects (default: false) |
| `created_by` | foreignId | User who created |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `name` (unique), `is_active`, `is_default`

### Table: `cost_code_template_items`

Individual cost codes within a template (hierarchical)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `template_id` | foreignId | References cost_code_templates |
| `parent_id` | foreignId (nullable) | Self-reference for hierarchy |
| `code` | string(50) | Cost code number (e.g., "03000") |
| `name` | string(255) | Cost code name (e.g., "Concrete") |
| `description` | text (nullable) | Description |
| `level` | tinyint | Hierarchy level (1 or 2 only, max 2 levels) |
| `display_order` | integer | Sort order within parent |
| `is_active` | boolean | Active status (default: true) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `template_id`, `parent_id`, `code` (unique per template)
**Constraint:** Unique `code` within same `template_id`

## Models

### CostCodeTemplate

```php
// app/Models/CostCodeTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCodeTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // Relationships
    public function createdBy(): BelongsTo;
    public function items(): HasMany; // All items
    public function rootItems(): HasMany; // Only top-level (parent_id = null)
    
    // Scopes
    public function scopeActive($query);
    
    // Methods
    public static function getDefault(): ?self;
    public function setAsDefault(): void;
    public function duplicate(string $newName): self;
}
```

### CostCodeTemplateItem

```php
// app/Models/CostCodeTemplateItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCodeTemplateItem extends Model
{
    protected $fillable = [
        'template_id',
        'parent_id',
        'code',
        'name',
        'description',
        'level',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function template(): BelongsTo;
    public function parent(): BelongsTo;
    public function children(): HasMany;
    
    // Scopes
    public function scopeActive($query);
    public function scopeOrdered($query);
    public function scopeRootLevel($query);
    
    // Accessors
    public function getFullCodeAttribute(): string; // "03000 - Concrete"
    public function getIndentedNameAttribute(): string; // "── Concrete Forms"
    
    // Methods
    public function hasChildren(): bool;
    public function canHaveChildren(): bool; // Returns true only if level == 1
    
    // Boot: validate max 2 levels on creating
}
```

## Routes

```php
// Cost Code Template routes
Route::get('cost-codes/templates', CostCodeTemplateIndex::class)
    ->name('costcodes.templates.index');
Route::get('cost-codes/templates/create', CostCodeTemplateCreate::class)
    ->name('costcodes.templates.create');
Route::get('cost-codes/templates/{template}', CostCodeTemplateShow::class)
    ->name('costcodes.templates.show');
Route::get('cost-codes/templates/{template}/edit', CostCodeTemplateEdit::class)
    ->name('costcodes.templates.edit');
```

## Livewire Components

### 1. CostCodeTemplateIndex

**File:** `app/Livewire/CostCode/CostCodeTemplateIndex.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-index.blade.php`

**Features:**
- List all templates with search
- Show item count per template
- Set default template
- Duplicate template
- Delete template (only if not in use)
- Create new template button

### 2. CostCodeTemplateCreate

**File:** `app/Livewire/CostCode/CostCodeTemplateCreate.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-create.blade.php`

**Features:**
- Template name and description
- Active/Default toggles
- Option to start from blank or copy from existing template

### 3. CostCodeTemplateShow / CostCodeTemplateEdit

**File:** `app/Livewire/CostCode/CostCodeTemplateShow.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-show.blade.php`

**Features:**
- Tree view of cost codes (expandable/collapsible)
- Add root-level cost code
- Add child cost code (under any parent)
- Edit cost code inline or in modal
- Delete cost code (only if no children)
- Drag-and-drop reordering (optional, can be phase 1.5)
- Import from CSV (optional)
- Export to CSV (optional)

## UI Specifications

### Template List View

```
┌─────────────────────────────────────────────────────────────────┐
│ Cost Code Templates                            [+ New Template] │
├─────────────────────────────────────────────────────────────────┤
│ Search: [________________]                                      │
├─────────────────────────────────────────────────────────────────┤
│ Name                    │ Codes │ Status  │ Default │ Actions  │
├─────────────────────────┼───────┼─────────┼─────────┼──────────┤
│ Commercial Construction │  45   │ Active  │   ★     │ ⋮        │
│ Residential             │  32   │ Active  │         │ ⋮        │
│ Service & Maintenance   │  18   │ Active  │         │ ⋮        │
└─────────────────────────┴───────┴─────────┴─────────┴──────────┘
```

### Template Detail View (Tree)

Note: Maximum 2 levels. [+] Add Child button only shows for Level 1 items.

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back    Commercial Construction       [Export][Import][Edit]  │
├─────────────────────────────────────────────────────────────────┤
│ [+ Add Cost Code]                                               │
│                                                                 │
│ ▼ 01000 - General Requirements                          [+][✎] │  ← Level 1 (has [+])
│   ├── 01100 - Summary of Work                              [✎] │  ← Level 2 (no [+])
│   ├── 01200 - Price and Payment                            [✎] │
│   └── 01300 - Administrative Requirements                  [✎] │
│                                                                 │
│ ▼ 02000 - Site Construction                             [+][✎] │
│   ├── 02100 - Site Preparation                             [✎] │
│   ├── 02200 - Demolition                                   [✎] │
│   └── 02300 - Earthwork                                    [✎] │
│                                                                 │
│ ▶ 03000 - Concrete                                      [+][✎] │
│ ▶ 04000 - Masonry                                       [+][✎] │
│ ▶ 05000 - Metals                                        [+][✎] │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

Legend: ▼ = Expanded, ▶ = Collapsed, [+] = Add Child (Level 1 only), [✎] = Edit
```

### Add/Edit Cost Code Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Add Cost Code                                              [X]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Parent: 02000 - Site Construction (or "Root Level")             │
│                                                                 │
│ Code *          [02100        ]                                 │
│ Name *          [Site Preparation                    ]          │
│ Description     [                                    ]          │
│                 [                                    ]          │
│                                                                 │
│ ☑ Active                                                        │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                    [Cancel]  [Save Cost Code]   │
└─────────────────────────────────────────────────────────────────┘
```

## Validation Rules

### Template
```php
[
    'name' => 'required|string|max:255|unique:cost_code_templates,name',
    'description' => 'nullable|string|max:1000',
    'is_active' => 'boolean',
    'is_default' => 'boolean',
]
```

### Template Item
```php
[
    'template_id' => 'required|exists:cost_code_templates,id',
    'parent_id' => 'nullable|exists:cost_code_template_items,id',
    'code' => 'required|string|max:50', // + unique within template
    'name' => 'required|string|max:255',
    'description' => 'nullable|string|max:1000',
    'is_active' => 'boolean',
]
```

## Default Seeder

Create a seeder with common construction cost codes (CSI MasterFormat inspired):

```php
// database/seeders/CostCodeTemplateSeeder.php

// Template: "General Construction"
// 01000 - General Requirements
//   01100 - Summary of Work
//   01200 - Price and Payment Procedures
//   01300 - Administrative Requirements
//   01400 - Quality Requirements
//   01500 - Temporary Facilities
// 02000 - Existing Conditions
//   02100 - Site Preparation
//   02200 - Demolition
// 03000 - Concrete
//   03100 - Concrete Forms
//   03200 - Concrete Reinforcement
//   03300 - Cast-in-Place Concrete
// ... etc
```

## Sidebar Navigation

Add to sidebar under a new "Settings" or "Configuration" section:

```
📋 Cost Codes
   └── Templates
```

Or as a main menu item if preferred.

---

## Phase 1 Implementation Checklist

### Step 1.1: Migrations
- [ ] Create `cost_code_templates` migration
- [ ] Create `cost_code_template_items` migration
- [ ] Run migrations

### Step 1.2: Models
- [ ] Create `CostCodeTemplate` model with relationships and methods
- [ ] Create `CostCodeTemplateItem` model with relationships and methods

### Step 1.3: Index Page
- [ ] Create `CostCodeTemplateIndex` Livewire component
- [ ] Create view with template list, search, actions
- [ ] Add route
- [ ] Add to sidebar navigation

### Step 1.4: Create Page
- [ ] Create `CostCodeTemplateCreate` Livewire component
- [ ] Create view with form
- [ ] Add route

### Step 1.5: Show/Edit Page (Template Details)
- [ ] Create `CostCodeTemplateShow` Livewire component
- [ ] Create view with tree structure
- [ ] Implement add/edit/delete for cost code items
- [ ] Add route

### Step 1.6: Seeder
- [ ] Create `CostCodeTemplateSeeder` with default template
- [ ] Run seeder

### Step 1.7: Import/Export CSV
- [ ] Add CSV export (download all cost codes from template)
- [ ] Add sample CSV template download
- [ ] Add CSV import modal with file upload
- [ ] Add import preview before execution
- [ ] Add merge/replace import modes
- [ ] Validate max 2 levels during import

### Step 1.8: Testing
- [ ] Test create template
- [ ] Test add cost codes (root and nested, max 2 levels)
- [ ] Test edit cost codes
- [ ] Test delete cost codes (with children protection)
- [ ] Test duplicate template
- [ ] Test set as default
- [ ] Test CSV export
- [ ] Test CSV import (merge mode)
- [ ] Test CSV import (replace mode)
- [ ] Test import validation (max 2 levels, required fields)

---

## Files to Create (Phase 1)

```
database/migrations/
├── YYYY_MM_DD_HHMMSS_create_cost_code_templates_table.php
└── YYYY_MM_DD_HHMMSS_create_cost_code_template_items_table.php

app/Models/
├── CostCodeTemplate.php
└── CostCodeTemplateItem.php

app/Livewire/CostCode/
├── CostCodeTemplateIndex.php
├── CostCodeTemplateCreate.php
└── CostCodeTemplateShow.php

resources/views/livewire/cost-code/
├── cost-code-template-index.blade.php
├── cost-code-template-create.blade.php
└── cost-code-template-show.blade.php

database/seeders/
└── CostCodeTemplateSeeder.php

docs/sessions/
└── YYYY-MM-DD-cost-code-templates.md
```

---

## Notes for Claude Code

When implementing Phase 1:

1. **Follow existing patterns** - Look at `CatalogCategory` for similar hierarchical structure
2. **Use existing UI components** - `x-ui.button`, `x-ui.modal`, etc.
3. **One page at a time** - Start with Index, test, then Create, test, then Show
4. **No fresh migrations** - Always incremental
5. **Check tree rendering** - Alpine.js for expand/collapse functionality

---

# PHASE 2: Project/Job Site Cost Codes

*To be detailed after Phase 1 is complete*

## Preview

- Apply template to Project or Job Site
- Copy cost codes from template (not linked, independent copy)
- Add/remove/edit cost codes per project
- Each applied cost code gets a `budgeted_amount` field

---

# PHASE 3: Budget System

*To be detailed after Phase 2 is complete*

## Preview

- Project Budget: Total amount + breakdown by cost code
- Job Site Budget: Optional, same structure
- Track: Budgeted, Revised, Committed, Actual, Remaining
- Budget vs Actual reports

---

# PHASE 4: Expense Refactor

*To be detailed after Phase 3 is complete*

## Preview

- Expense becomes header (supplier, date, payment info)
- Expense Items are the line items (catalog item, cost code, amount)
- Each item links to a cost code
- Totals roll up to cost code actuals

---

# PHASE 5: Purchase Orders

*To be detailed after Phase 4 is complete*

## Preview

- PO with header and line items
- Each line has cost code
- PO status: Draft, Sent, Approved, Received, Cancelled
- Approved PO amounts = Committed costs
- Convert PO to Expense when received

---

# PHASE 6: Change Order Integration

*To be detailed after Phase 5 is complete*

## Preview

- Change Order adds to project total
- Option to allocate to specific cost codes
- Increases Revised Budget for those codes

---

# PHASE 7: Reports & Dashboard

*To be detailed after Phase 6 is complete*

## Preview

- Budget Summary by Cost Code
- Budget vs Actual variance
- Committed costs report
- Cost code spending trends
- Export to Excel/PDF
