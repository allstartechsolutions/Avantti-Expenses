# Cost Code Templates Module

**Status:** Implemented
**Date:** 2026-01-26
**Location:** Projects → Cost Codes

---

## Overview

The Cost Code Templates module allows users to create and manage reusable cost code templates for construction projects. Each template contains hierarchical cost codes (parent/child structure with 1 level of nesting).

---

## Database Schema

### Table: `cost_code_templates`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string(255) | Template name |
| `description` | text (nullable) | Template description |
| `is_default` | boolean | Default template flag (default: false) |
| `created_by` | foreignId | User who created (cascade on delete) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Table: `cost_codes`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `template_id` | foreignId | References cost_code_templates (cascade) |
| `parent_id` | bigint (nullable) | Self-reference for hierarchy |
| `code` | string | Cost code (unique per template) |
| `name` | string | Cost code name |
| `description` | text (nullable) | Description |
| `sort_order` | integer | Sort order (default: 0) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique(['template_id', 'code'])` - Code must be unique within template
- `index(['template_id', 'parent_id'])` - For faster queries

---

## Models

### CostCodeTemplate

**Location:** `app/Models/CostCodeTemplate.php`

**Relationships:**
- `creator()` - BelongsTo User
- `costCodes()` - HasMany CostCode
- `parentCostCodes()` - HasMany CostCode (where parent_id is null, ordered by sort_order)

**Methods:**
- `duplicate(int $userId, ?string $newName)` - Creates a copy of the template with all cost codes
- `setDefault(int $templateId)` - Static method to set a template as default

### CostCode

**Location:** `app/Models/CostCode.php`

**Relationships:**
- `template()` - BelongsTo CostCodeTemplate
- `parent()` - BelongsTo CostCode (self)
- `children()` - HasMany CostCode (self, ordered by sort_order)

**Methods:**
- `isParent()` - Returns true if parent_id is null
- `isChild()` - Returns true if parent_id is not null

**Accessors:**
- `full_code` - Returns parent code + this code (e.g., "01.01.1")
- `full_name` - Returns parent name > this name (e.g., "General > Labor")

---

## Routes

| Method | URI | Name | Component |
|--------|-----|------|-----------|
| GET | `/cost-codes/templates` | cost-codes.templates.index | CostCodeTemplateIndex |
| GET | `/cost-codes/templates/create` | cost-codes.templates.create | CostCodeTemplateCreate |
| GET | `/cost-codes/templates/{template}` | cost-codes.templates.show | CostCodeTemplateShow |
| GET | `/cost-codes/templates/{template}/edit` | cost-codes.templates.edit | CostCodeTemplateEdit |

---

## Livewire Components

### CostCodeTemplateIndex

**Location:** `app/Livewire/CostCode/CostCodeTemplateIndex.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-index.blade.php`

**Features:**
- List all templates with pagination (15 per page)
- Search by name/description
- Show cost codes count per template
- Delete template (with confirmation)
- Duplicate template
- Set as default template

### CostCodeTemplateCreate

**Location:** `app/Livewire/CostCode/CostCodeTemplateCreate.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-create.blade.php`

**Features:**
- Create new template with name, description
- Set as default toggle
- Redirects to show page after creation

### CostCodeTemplateShow

**Location:** `app/Livewire/CostCode/CostCodeTemplateShow.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-show.blade.php`

**Features:**
- View template details
- Hierarchical display of cost codes (parent + children)
- Add parent cost codes
- Add child cost codes (under any parent)
- Add/edit cost codes in a dialog (`x-ui.modal`, name `cost-code-form-modal`), not a sidebar panel
- **Save & Add Another** keeps the dialog open, clears it, holds the parent and bumps the sort order
- Sort order is filled in with the next free position under the parent
- Delete cost codes (only if no children)
- Unique code validation within template
- **Import CSV** - Import cost codes from CSV file
- **Download sample CSV** - Get a template CSV file

### CostCodeTemplateEdit

**Location:** `app/Livewire/CostCode/CostCodeTemplateEdit.php`
**View:** `resources/views/livewire/cost-code/cost-code-template-edit.blade.php`

**Features:**
- Edit template name, description
- Toggle default status
- Redirects to show page after save

---

## Navigation

Cost Codes is located under **Projects** submenu in the sidebar:

```
Projects
├── All Projects
├── Clients
└── Cost Codes    ← Templates management
```

To keep the submenu open when on Cost Codes pages, the route is included in the `activeSubmenu` logic in `app.blade.php`.

---

## Hierarchy Rules

1. **Maximum depth:** 1 level (Parent → Child only, no grandchildren)
2. **Code uniqueness:** Codes must be unique within the same template
3. **Deletion protection:** Cannot delete a parent code that has children

---

## CSV Import

### CSV Format

| Column | Required | Description |
|--------|----------|-------------|
| `code` | Yes | Cost code (must be unique within template) |
| `name` | Yes | Cost code name |
| `description` | No | Optional description |
| `parent_code` | No | Parent code (leave empty for root level) |

### Sample CSV

```csv
code,name,description,parent_code
01,General Requirements,General project requirements,
01.1,Summary of Work,Project scope summary,01
01.2,Price and Payment,Payment procedures,01
02,Site Work,Site related work,
02.1,Site Preparation,Prepare the site,02
02.2,Demolition,Demolition work,02
```

### Import Modes

| Mode | Behavior |
|------|----------|
| **Merge** | Updates existing codes (matched by code), adds new ones |
| **Replace** | Deletes all existing codes before importing |

### Validation Rules

- Required columns: `code`, `name`
- Maximum 2 levels (child of a child is not allowed)
- Duplicate codes in CSV are rejected
- Parent code must exist (either in CSV or already in template)

### Encoding Support

The import handles various file encodings:

- **UTF-8** (recommended)
- **Windows-1252** (auto-converted)
- **ISO-8859-1** (auto-converted)
- **BOM** (automatically removed)
- **Corrupted Portuguese characters** (auto-fixed)

**Best Practice:** When exporting CSV files, always use **UTF-8** encoding:
- **Excel:** Save As → CSV UTF-8 (Comma delimited)
- **Google Sheets:** File → Download → CSV
- **Numbers:** File → Export To → CSV → Unicode (UTF-8)

---

## Files Structure

```
app/
├── Livewire/CostCode/
│   ├── CostCodeTemplateIndex.php
│   ├── CostCodeTemplateCreate.php
│   ├── CostCodeTemplateShow.php
│   └── CostCodeTemplateEdit.php
├── Models/
│   ├── CostCodeTemplate.php
│   └── CostCode.php

resources/views/livewire/cost-code/
├── cost-code-template-index.blade.php
├── cost-code-template-create.blade.php
├── cost-code-template-show.blade.php
└── cost-code-template-edit.blade.php

database/migrations/
├── 2026_01_26_100000_create_cost_code_templates_table.php
└── 2026_01_26_100001_create_cost_codes_table.php
```

---

## UI Components Used

- `x-ui.button` - All buttons (primary, secondary, ghost, danger variants)
- `x-ui.icon` - Icons (plus, edit, trash, upload, download, check, copy, star, etc.)
- Custom modal for CSV import

---

## Future Phases

See `docs/budget-costcode-system.md` for the full roadmap:

| Phase | Feature | Status |
|-------|---------|--------|
| 1 | Cost Code Templates | ✅ Complete |
| 2 | Apply templates to Projects/Job Sites | Pending |
| 3 | Budget System integration | Pending |
| 4 | Expense Items with cost codes | Pending |
| 5 | Purchase Orders | Pending |
| 6 | Change Order integration | Pending |
| 7 | Reports & Dashboard | Pending |
