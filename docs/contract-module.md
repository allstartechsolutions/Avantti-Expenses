# Contract Module Documentation

## Overview

The Contract module provides management of subcontractor contracts within projects and job sites. Contracts track agreements with subcontractors including financial terms, dates, attached documents, and a full status lifecycle with audit trail.

## Key Features

- Subcontractor contract tracking with financial details
- Payment tracking with automatic status transitions (see [Contract Payments](./contract-payments.md))
- Status workflow: `active` → `completed` → `paid` (with partial payment and cancellation paths)
- Status change tracking with audit trail and optional reasons
- Contract file upload (PDF, JPG, PNG)
- Works at both project-level and job-site-level
- Auto-generated sequential contract numbers (CTR-0001, CTR-0002, ...)

---

## Database Schema

### 1. `contracts` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Foreign key to projects (required, cascadeOnDelete) |
| job_site_id | bigint | Foreign key to job_sites (nullable - null = project-level, cascadeOnDelete) |
| subcontractor_id | bigint | Foreign key to subcontractors (nullable, nullOnDelete) |
| contract_number | string | Auto-generated unique number (CTR-XXXX) |
| status | enum | active, completed, partially_paid, paid, cancelled |
| start_date | date | Contract start date |
| end_date | date | Contract end date (nullable) |
| amount | bigInteger | Contract amount in cents |
| notes | text | Optional notes (nullable) |
| contract_file_path | string | Path to uploaded document (nullable) |
| created_by | bigint | Foreign key to users (nullable, nullOnDelete) |
| timestamps | | created_at, updated_at |

### 2. `contract_payments` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| contract_id | bigint | Foreign key to contracts (cascadeOnDelete) |
| amount | unsignedBigInteger | Payment amount in cents |
| payment_date | date | Date the payment was made |
| payment_method | enum | cash, check, credit_card, debit_card, bank_transfer, pix, other (default: check) |
| reference_number | string | Check #, transaction ID, etc. (nullable) |
| notes | text | Optional notes (nullable) |
| created_by | bigint | Foreign key to users (nullable, nullOnDelete) |
| timestamps | | created_at, updated_at |

See [Contract Payments](./contract-payments.md) for full details.

### 3. `contract_change_orders` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| contract_id | bigint | Foreign key to contracts (cascadeOnDelete) |
| title | string | Change order title |
| date | date | Change order date |
| amount | bigInteger | Amount in cents (signed — negative for deductions) |
| description | text | Optional description (nullable) |
| file_path | string | Path to uploaded document (nullable) |
| created_by | bigint | Foreign key to users (nullable, nullOnDelete) |
| timestamps | | created_at, updated_at |

**Indexes:** `contract_id`

See [Contract Change Orders](#contract-change-orders) section below for full details.

### 4. `contract_status_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| contract_id | bigint | Foreign key to contracts (cascadeOnDelete) |
| old_status | string | Previous status (nullable for creation) |
| new_status | string | New status |
| changed_by | bigint | Foreign key to users (nullable, nullOnDelete) |
| reason | text | Reason for change (nullable) |
| timestamps | | created_at, updated_at |

---

## Models

### Contract Model

**Location:** `app/Models/Contract.php`

**Relationships:**
- `project()` - BelongsTo Project
- `jobSite()` - BelongsTo JobSite (nullable)
- `subcontractor()` - BelongsTo Subcontractor (nullable)
- `createdBy()` - BelongsTo User
- `statusHistories()` - HasMany ContractStatusHistory
- `changeOrders()` - HasMany ContractChangeOrder (ordered by date desc)
- `payments()` - HasMany ContractPayment (ordered by payment_date desc)

**Accessors (cents ↔ dollars):**
- `amount` - Automatically converts between cents (database) and dollars (application)

**Status Check Methods:**
- `isProjectLevel()` - Returns true if job_site_id is null
- `isActive()` - Status is 'active'
- `isCompleted()` - Status is 'completed'
- `isPaid()` - Status is 'paid'
- `isPartiallyPaid()` - Status is 'partially_paid'
- `isCancelled()` - Status is 'cancelled'

**Change Order Methods:**
- `getChangeOrdersTotal()` - Returns sum of all change order amounts in dollars
- `getAdjustedAmount()` - Returns original amount + change orders total in dollars

**Payment Methods:**
- `getAmountPaid()` - Returns total paid in dollars
- `getBalanceDue()` - Returns adjusted amount minus amount paid in dollars
- `updateStatusFromPayments()` - Auto-transitions status based on payment totals (uses adjusted amount)

**Other Methods:**
- `generateContractNumber()` - Static method, returns next sequential CTR-XXXX number
- `recordStatusChange(User, ?oldStatus, newStatus, ?reason)` - Records status change in history

### ContractPayment Model

**Location:** `app/Models/ContractPayment.php`

**Relationships:**
- `contract()` - BelongsTo Contract
- `createdBy()` - BelongsTo User

**Accessors (cents <-> dollars):**
- `amount` - Converts between cents (database) and dollars (application)

**Display Helpers:**
- `getPaymentMethodLabel()` - Returns human-readable payment method label

### ContractChangeOrder Model

**Location:** `app/Models/ContractChangeOrder.php`

**Relationships:**
- `contract()` - BelongsTo Contract
- `createdBy()` - BelongsTo User

**Accessors (cents ↔ dollars):**
- `amount` - Automatically converts between cents (database) and dollars (application). Supports negative values for deductions.

### ContractStatusHistory Model

**Location:** `app/Models/ContractStatusHistory.php`

**Relationships:**
- `contract()` - BelongsTo Contract
- `changedBy()` - BelongsTo User

---

## Livewire Components

### 1. ContractCreate

**Location:** `app/Livewire/Contract/ContractCreate.php`

**View:** `resources/views/livewire/contract/contract-create.blade.php`

**Features:**
- Creates new contracts
- Supports project-level or job-site-level creation
- When accessed from job site, location is locked to that job site
- Subcontractor search and selection (typeahead)
- Contract file upload (PDF, JPG, PNG, max 10MB)
- Auto-generates contract number on save
- Initial status set to 'active' with status history recorded

**Mount Parameters:**
- `?Project $project` - When creating from project level
- `?JobSite $jobSite` - When creating from job site level

### 2. ContractShow

**Location:** `app/Livewire/Contract/ContractShow.php`

**View:** `resources/views/livewire/contract/contract-show.blade.php`

**Features:**
- View contract details (read-only)
- Three-column layout: main content (col-span-2) + sidebar (col-span-1)
- Main content cards: Details, Financial (with change orders breakdown and paid/balance due), Change Orders (inline component), Notes (conditional), Contract File (conditional)
- Sidebar cards: Actions, Status History timeline, Payment History (conditional)
- Financial card shows: Original Amount, Change Orders (green, positive total), Deductions (red, negative total), Adjusted Amount, Amount Paid, Balance Due
- Status change via modal with dropdown and optional reason
- Payment recording via modal with amount, method, date, reference, notes
- Computed `availableStatuses` property controls allowed transitions per current status
- Delete with `wire:confirm` — cleans up contract file and change order files from storage before deleting
- Back navigation returns to correct context (project or job site contracts index)
- View/Download links for attached contract file
- Listens for `change-orders-updated` event to refresh financial data when change orders are added/edited/deleted

**Action Buttons (sidebar):**
- **Change Status** - Opens modal (only shown when transitions are available)
- **Record Payment** - Opens payment modal (shown when active, completed, or partially_paid)
- **Edit Contract** - Links to ContractEdit
- **Delete Contract** - With confirmation dialog

### 3. ContractChangeOrders (Inline Component)

**Location:** `app/Livewire/Contract/ContractChangeOrders.php`

**View:** `resources/views/livewire/contract/contract-change-orders.blade.php`

**Type:** Inline component (embedded in ContractShow page)

**Features:**
- Lists all change orders for a contract in a table (Title, Date, Amount, Created By, File, Actions)
- Amounts are color-coded: green with `+` prefix for positive (additions), red for negative (deductions)
- Footer row shows total change orders amount
- Add/Edit modal with fields: Title (required), Date (required), Amount (required, allows negative for deductions), Description (optional), File upload (optional)
- File upload: PDF, JPG, PNG up to 10MB, stored in `contract-change-orders` directory
- Edit replaces existing file when new one is uploaded
- Delete cleans up file from storage before deleting record
- Dispatches `change-orders-updated` event to parent ContractShow to refresh financial data

**Properties:**
- `$contract` - The contract model
- `$showModal` - Controls modal visibility
- `$editingId` - ID of change order being edited (null for create)
- `$title`, `$date`, `$amount`, `$description`, `$file` - Form fields

### 4. ContractEdit

**Location:** `app/Livewire/Contract/ContractEdit.php`

**View:** `resources/views/livewire/contract/contract-edit.blade.php`

**Features:**
- Edit existing contracts (all statuses can be edited)
- Pre-fills all form fields from contract data
- Subcontractor search pre-populated with current selection
- Existing file management: view current file, remove, or replace
- File handling logic:
  - New upload → deletes old file, stores new
  - Remove clicked → deletes old file, sets null
  - No change → keeps existing file path
- Location (job site) can be changed
- Redirects to ContractShow on save

### 4. ProjectContracts

**Location:** `app/Livewire/Project/ProjectContracts.php`

**View:** `resources/views/livewire/project/project-contracts.blade.php`

**Features:**
- Lists all contracts for a project (project-level + all job sites)
- Uses `<x-project-layout>` with project navigation menu
- Search filter (contract #, notes, subcontractor name)
- Location filter (All, Project General, specific job sites)
- Status filter (All, Active, Completed, Partially Paid, Paid, Cancelled)
- Summary cards: Total Value, Active count, Completed count, Paid count
- View/Edit action buttons per row

### 5. JobSiteContracts

**Location:** `app/Livewire/JobSite/JobSiteContracts.php`

**View:** `resources/views/livewire/job-site/job-site-contracts.blade.php`

**Features:**
- Lists all contracts for a specific job site
- Uses `<x-jobsite-layout>` with job site navigation menu
- Search and status filters (no location filter since scoped to job site)
- Same summary cards and table layout as ProjectContracts
- View/Edit action buttons per row

---

## Routes

```php
// Contract index routes (nested in project/jobsite)
Route::get('projects/{project}/contracts', ProjectContracts::class)
    ->name('projects.contracts');

Route::get('job-sites/{jobSite}/contracts', JobSiteContracts::class)
    ->name('jobsites.contracts');

// Contract detail routes (standalone by contract ID)
Route::get('contracts/{contract}', ContractShow::class)
    ->name('contracts.show');

Route::get('contracts/{contract}/edit', ContractEdit::class)
    ->name('contracts.edit');

// Contract creation routes (nested in project/jobsite)
Route::get('projects/{project}/contracts/create', ContractCreate::class)
    ->name('contracts.project.create');

Route::get('job-sites/{jobSite}/contracts/create', ContractCreate::class)
    ->name('contracts.jobsite.create');
```

---

## Status Workflow

```
┌────────┐
│ ACTIVE │
└────────┘
     │
     ├── completed ──► ┌───────────┐
     │                 │ COMPLETED │
     │                 └───────────┘
     │                      │
     │                      ├── paid ──────────► ┌──────┐
     │                      │                    │ PAID │
     │                      │                    └──────┘
     │                      │                        ▲
     │                      └── partially_paid ──► ┌────────────────┐
     │                                             │ PARTIALLY PAID │──── paid
     │                                             └────────────────┘
     │
     └── cancelled ──► ┌───────────┐
                       │ CANCELLED │
                       └───────────┘
```

### Status Transition Rules

| From | To | Trigger | Description |
|------|-----|---------|-------------|
| active | completed | Manual | Work is finished |
| active | cancelled | Manual | Contract cancelled |
| completed | paid | Auto (payment) | Full payment recorded |
| completed | partially_paid | Auto (payment) | Partial payment recorded |
| partially_paid | paid | Auto (payment) | Remaining balance paid |
| paid | partially_paid | Auto (payment deleted) | Payment removed, balance remains |
| partially_paid | completed | Auto (all payments deleted) | All payments removed |

**Note:** Statuses can also be changed manually via the Change Status modal. Auto-transitions only apply when payments are recorded or deleted on contracts with status `completed`, `partially_paid`, or `paid`.

### Computed Available Statuses (ContractShow)

The `availableStatuses` computed property returns allowed next statuses:

```php
match ($this->contract->status) {
    'active'         => ['completed', 'cancelled'],
    'completed'      => ['paid', 'partially_paid'],
    'partially_paid' => ['paid'],
    default          => [],  // paid, cancelled = no transitions
};
```

---

## File Handling

### Storage
- Contract files are stored in `storage/app/contracts/` using Laravel's local disk
- Change order files are stored in `storage/app/contract-change-orders/` using Laravel's local disk

### Upload Rules
- Accepted formats: PDF, JPG, JPEG, PNG
- Max file size: 10MB (10240 KB)

### Cleanup
- **Contract delete:** `ContractShow::delete()` manually deletes the contract file and all change order files from storage before deleting the contract record (cascade will delete change order DB records)
- **Contract edit (replace):** `ContractEdit::save()` deletes the old file when a new file is uploaded or when the user clicks "Remove"
- **Change order delete:** `ContractChangeOrders::delete()` deletes the file from storage before deleting the change order record
- **Change order edit (replace):** `ContractChangeOrders::save()` deletes the old file when a new file is uploaded
- **On cascade delete:** File cleanup does NOT happen automatically (no Eloquent boot event). Parent record cascade deletes will orphan files. See `docs/delete-functionality.md` for the general pattern.

---

## Files Created

### Migrations
- `database/migrations/2026_02_13_200000_create_contracts_table.php`
- `database/migrations/2026_02_13_200001_create_contract_status_histories_table.php`
- `database/migrations/2026_02_13_210000_create_contract_payments_table.php`
- `database/migrations/2026_02_17_100000_create_contract_change_orders_table.php`

### Models
- `app/Models/Contract.php`
- `app/Models/ContractStatusHistory.php`
- `app/Models/ContractPayment.php`
- `app/Models/ContractChangeOrder.php`

### Livewire Components
- `app/Livewire/Contract/ContractCreate.php`
- `app/Livewire/Contract/ContractShow.php`
- `app/Livewire/Contract/ContractEdit.php`
- `app/Livewire/Contract/ContractChangeOrders.php` (inline, embedded in ContractShow)
- `app/Livewire/Project/ProjectContracts.php`
- `app/Livewire/JobSite/JobSiteContracts.php`

### Views
- `resources/views/livewire/contract/contract-create.blade.php`
- `resources/views/livewire/contract/contract-show.blade.php`
- `resources/views/livewire/contract/contract-edit.blade.php`
- `resources/views/livewire/contract/contract-change-orders.blade.php`
- `resources/views/livewire/project/project-contracts.blade.php`
- `resources/views/livewire/job-site/job-site-contracts.blade.php`

### Modified Files
- `routes/web.php` - Added contract routes and imports
- `resources/views/components/project-nav.blade.php` - Added Contracts to project navigation
- `resources/views/components/jobsite-nav.blade.php` - Added Contracts to job site navigation
- `app/Models/Project.php` - Added `contracts()` relationship
- `app/Models/JobSite.php` - Added `contracts()` relationship

---

## Technical Notes

### Cents vs Dollars
Contract amount is stored in cents (integer) in the database and converted to dollars in the application layer using an Eloquent `Attribute` accessor on the Contract model.

### Cascade Deletes
- Deleting a Contract cascades to ContractStatusHistory, ContractPayment, and ContractChangeOrder records (database-level)
- Deleting a Project cascades to its Contracts (database-level)
- Deleting a JobSite cascades to its Contracts (database-level)
- **Important:** Cascade deletes do NOT trigger Eloquent events, so contract and change order files will be orphaned. `ContractShow::delete()` manually cleans up both contract file and all change order files before deleting. Manual cleanup is required before deleting parent records (Project/JobSite).

### Contract Number Generation
`Contract::generateContractNumber()` finds the max existing contract_number, extracts the numeric part, increments it, and returns the next value (e.g., CTR-0001, CTR-0002, etc.).

### Subcontractor Search
The subcontractor typeahead searches by `company_name` with a minimum 2-character threshold, limited to 10 results. Selection stores the `subcontractor_id` and displays the company name.
