# Subcontractor Employees with Contract Contact Link

## Overview
Added an **Employees** feature to the Subcontractor module. Each subcontractor can have multiple employees (title, name, phone, email, notes), managed from a new **Employees** tab on the subcontractor detail page. A contract can optionally be linked to one of the selected subcontractor's employees as its point of contact, chosen on the contract create/edit forms and displayed on the contract detail page.

The existing single embedded contact fields on the subcontractor (`contact_name`, `contact_email`, `title`, `phone`) remain untouched — they still represent the primary company contact.

## Database Changes

### Migration 1: Create subcontractor_employees table
**File:** `database/migrations/2026_07_16_121922_create_subcontractor_employees_table.php`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Primary key |
| `subcontractor_id` | foreignId | FK to `subcontractors`, cascadeOnDelete |
| `title` | string | Nullable — position/title |
| `name` | string | Required |
| `phone` | string | Nullable |
| `email` | string | Nullable |
| `notes` | text | Nullable |
| `timestamps` | | created_at, updated_at |

### Migration 2: Add subcontractor_employee_id to contracts
**File:** `database/migrations/2026_07_16_121923_add_subcontractor_employee_id_to_contracts_table.php`

- Added `subcontractor_employee_id` column (nullable foreign key, placed after `subcontractor_id`)
- Uses `nullOnDelete` — deleting an employee unlinks it from contracts (same behavior as `subcontractor_id`)

```php
$table->foreignId('subcontractor_employee_id')->nullable()->after('subcontractor_id')->constrained()->nullOnDelete();
```

## Model Changes

### SubcontractorEmployee (new)
**File:** `app/Models/SubcontractorEmployee.php`

- Fillable: `subcontractor_id`, `title`, `name`, `phone`, `email`, `notes`
- Relationships: `subcontractor()`, `contracts()`

### Subcontractor (updated)
**File:** `app/Models/Subcontractor.php`

- Added `employees()` hasMany relationship

### Contract (updated)
**File:** `app/Models/Contract.php`

- Added `subcontractor_employee_id` to fillable
- Added `subcontractorEmployee()` belongsTo relationship

## Livewire Component Changes

### SubcontractorShow (updated)
**Files:** `app/Livewire/Subcontractor/SubcontractorShow.php`, `resources/views/livewire/subcontractor/subcontractor-show.blade.php`

- New **Employees** tab (mirrors the Documents tab pattern) with a count badge
- Inline toggle add-form (`toggleEmployeeForm()` / `saveEmployee()`) with fields: Name (required), Title, Phone, Email, Notes
- Employee validation rules are passed explicitly to `$this->validate([...])` inside `saveEmployee()` so they don't interfere with the shared `rules()` method used by document uploads
- Employees table with `tel:` / `mailto:` links and delete via `wire:confirm` (`deleteEmployee()`, scoped to the subcontractor)
- Sidebar "Information" card shows the employee count

### ContractCreate / ContractEdit (updated)
**Files:** `app/Livewire/Contract/ContractCreate.php`, `ContractEdit.php` and their views

- New `subcontractor_employee_id` property
- A **Contact (Employee)** select appears once a subcontractor is chosen, listing that subcontractor's employees (name — title)
- Selecting or clearing the subcontractor resets the employee selection
- Validation: `Rule::exists('subcontractor_employees', 'id')->where('subcontractor_id', $this->subcontractor_id)` guarantees the employee belongs to the selected subcontractor

### ContractShow (updated)
**Files:** `app/Livewire/Contract/ContractShow.php`, `resources/views/livewire/contract/contract-show.blade.php`

- Eager-loads `subcontractorEmployee` (mount and refresh)
- Contract Details card shows a **Contact** row: employee name (title), or "Not specified"

## Behavior Notes

- Deleting an employee that is linked to contracts unlinks it (contract keeps its subcontractor); the delete confirmation warns about this
- Deleting a subcontractor cascade-deletes its employees, and contracts' `subcontractor_employee_id` is nulled
- No new routes — employees are managed entirely on the subcontractor detail page
