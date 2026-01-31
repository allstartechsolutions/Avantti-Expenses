# Purchase Order (PO) Module Documentation

## Overview

The Purchase Order module provides a complete workflow for managing purchase orders in the project management system. It mirrors the Expense system structure with additional approval workflow capabilities.

## Key Features

- Multi-item purchase orders with supplier, budget items, and catalog items
- Status workflow: `draft` → `pending` → `approved`/`rejected`/`cancelled`
- Automatic expense creation upon approval
- Status change tracking with audit trail
- Revision support for rejected POs
- Works at both project-level and job-site-level

---

## Database Schema

### 1. `purchase_orders` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Foreign key to projects (required) |
| job_site_id | bigint | Foreign key to job_sites (nullable - null = project-level) |
| supplier_id | bigint | Foreign key to suppliers (nullable) |
| expense_id | bigint | Foreign key to expenses (nullable - set when approved) |
| status | enum | draft, pending, approved, rejected, cancelled |
| revision_number | unsignedInteger | Default: 1, increments on revision |
| po_number | string | Optional PO reference number |
| po_date | date | Purchase order date |
| notes | text | Optional notes |
| receipt_path | string | Path to uploaded quote/document |
| payment_method | enum | cash, check, credit_card, debit_card, bank_transfer, pix, other |
| is_auto_payment | boolean | Default: false |
| total_installments | unsignedInteger | Default: 1 |
| payment_frequency | enum | weekly, biweekly, monthly (nullable) |
| payment_due_date | date | Payment due date (nullable) |
| total_amount | bigInteger | Total amount in cents |
| created_by | bigint | Foreign key to users |
| approved_by | bigint | Foreign key to users (nullable) |
| approved_at | timestamp | When approved (nullable) |
| timestamps | | created_at, updated_at |

### 2. `purchase_order_items` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| purchase_order_id | bigint | Foreign key (cascade on delete) |
| budget_item_id | bigint | Foreign key to budget_items (nullable) |
| catalog_item_id | bigint | Foreign key to catalog_items (nullable) |
| item_name | string | Item name |
| item_type | enum | catalog, custom |
| description | text | Optional description |
| quantity | decimal(10,2) | Default: 1 |
| unit | string(50) | Unit of measure (nullable) |
| unit_price | bigInteger | Price per unit in cents |
| total_amount | bigInteger | Total in cents |
| sort_order | integer | Display order |
| timestamps | | created_at, updated_at |

### 3. `purchase_order_status_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| purchase_order_id | bigint | Foreign key (cascade on delete) |
| old_status | enum | Previous status (nullable for creation) |
| new_status | enum | New status |
| changed_by | bigint | Foreign key to users |
| reason | text | Reason for change (optional) |
| revision_data | json | Snapshot of PO data before revision (nullable) |
| timestamps | | created_at, updated_at |

### 4. Modified `expenses` Table

Added column:
- `purchase_order_id` (bigint, nullable) - Links expense back to originating PO

---

## Models

### PurchaseOrder Model

**Location:** `app/Models/PurchaseOrder.php`

**Relationships:**
- `project()` - BelongsTo Project
- `jobSite()` - BelongsTo JobSite (nullable)
- `supplier()` - BelongsTo Supplier (nullable)
- `expense()` - BelongsTo Expense (nullable)
- `items()` - HasMany PurchaseOrderItem
- `statusHistories()` - HasMany PurchaseOrderStatusHistory
- `createdBy()` - BelongsTo User
- `approvedBy()` - BelongsTo User (nullable)

**Accessors (cents ↔ dollars):**
- `total_amount` - Automatically converts between cents (database) and dollars (application)

**Status Check Methods:**
- `isProjectLevel()` - Returns true if job_site_id is null
- `isDraft()` - Status is 'draft'
- `isPending()` - Status is 'pending'
- `isApproved()` - Status is 'approved'
- `isRejected()` - Status is 'rejected'
- `isCancelled()` - Status is 'cancelled'
- `canBeEdited()` - Returns true if draft or rejected
- `canBeSubmitted()` - Returns true if draft with items
- `canBeApproved()` - Returns true if pending
- `canBeRejected()` - Returns true if pending, or approved without payments
- `canChangeStatusFromApproved()` - Returns true if expense has no payments
- `canReviseAndResubmit()` - Returns true if rejected

**Status Action Methods:**
- `submitForApproval(User $user)` - Changes draft → pending
- `approve(User $approver)` - Changes pending → approved, creates expense
- `reject(User $user, ?string $reason)` - Changes to rejected
- `cancel(User $user, ?string $reason)` - Changes to cancelled
- `reviseAndResubmit(User $user)` - Changes rejected → pending, increments revision

**Expense Integration:**
- `createExpenseFromPO()` - Creates a linked expense with all items and payment schedule
- `deleteLinkedExpense()` - Deletes the linked expense (when status changes from approved)

**Other Methods:**
- `recordStatusChange()` - Records status change in history
- `recalculateTotal()` - Recalculates total from items
- `getStatusColor()` - Returns color for status badge
- `getStatusLabel()` - Returns human-readable status label
- `getLocationDisplay()` - Returns location display string

### PurchaseOrderItem Model

**Location:** `app/Models/PurchaseOrderItem.php`

**Relationships:**
- `purchaseOrder()` - BelongsTo PurchaseOrder
- `budgetItem()` - BelongsTo BudgetItem (nullable)
- `catalogItem()` - BelongsTo CatalogItem (nullable)

**Accessors:**
- `unit_price` - Converts cents ↔ dollars
- `total_amount` - Converts cents ↔ dollars

**Methods:**
- `isCustom()` - Returns true if item_type is 'custom'
- `calculateTotal()` - Returns quantity * unit_price

### PurchaseOrderStatusHistory Model

**Location:** `app/Models/PurchaseOrderStatusHistory.php`

**Relationships:**
- `purchaseOrder()` - BelongsTo PurchaseOrder
- `changedBy()` - BelongsTo User

**Casts:**
- `revision_data` - Cast to array

**Methods:**
- `getOldStatusLabel()` - Returns human-readable old status
- `getNewStatusLabel()` - Returns human-readable new status

---

## Livewire Components

### 1. PurchaseOrderCreate

**Location:** `app/Livewire/PurchaseOrder/PurchaseOrderCreate.php`

**View:** `resources/views/livewire/purchase-order/purchase-order-create.blade.php`

**Features:**
- Creates new purchase orders
- Supports project-level or job-site-level creation
- When accessed from job site, location is locked to that job site
- Item modal with catalog/custom item support
- Budget item (cost code) assignment
- Supplier search and selection
- Payment options (method, installments, frequency)
- Document/quote upload
- Save as draft or submit for approval

**Mount Parameters:**
- `?Project $project` - When creating from project level
- `?JobSite $jobSite` - When creating from job site level

### 2. PurchaseOrderEdit

**Location:** `app/Livewire/PurchaseOrder/PurchaseOrderEdit.php`

**View:** `resources/views/livewire/purchase-order/purchase-order-edit.blade.php`

**Features:**
- Edit existing purchase orders (only draft or rejected status)
- Same functionality as create
- Can submit for approval after editing

### 3. PurchaseOrderShow

**Location:** `app/Livewire/PurchaseOrder/PurchaseOrderShow.php`

**View:** `resources/views/livewire/purchase-order/purchase-order-show.blade.php`

**Features:**
- View PO details (read-only)
- Status history timeline
- Linked expense display (if approved)
- Action buttons based on status:
  - **Draft:** Edit, Submit for Approval, Cancel
  - **Pending:** Approve, Reject (with reason modal), Cancel
  - **Approved:** View Expense link (protected if expense has payments)
  - **Rejected:** Edit, Revise & Resubmit
- Back navigation returns to correct context (project or job site)

### 4. ProjectPurchaseOrders

**Location:** `app/Livewire/Project/ProjectPurchaseOrders.php`

**View:** `resources/views/livewire/project/project-purchase-orders.blade.php`

**Features:**
- Lists all POs for a project (project-level + all job sites)
- Uses `<x-project-layout>` with project navigation menu
- Search filter (PO#, supplier, notes)
- Location filter (All, Project General, specific job sites)
- Status filter (All, Draft, Pending, Approved, Rejected, Cancelled)
- Summary cards: Total Amount, Pending Approval, Approved Amount
- Pagination

---

## Routes

```php
// Project Purchase Order routes
Route::get('projects/{project}/purchase-orders', ProjectPurchaseOrders::class)
    ->name('projects.purchase-orders');

Route::get('projects/{project}/purchase-orders/create', PurchaseOrderCreate::class)
    ->name('purchase-orders.project.create');

// Job Site Purchase Order routes
Route::get('job-sites/{jobSite}/purchase-orders/create', PurchaseOrderCreate::class)
    ->name('purchase-orders.jobsite.create');

// Purchase Order detail routes
Route::get('purchase-orders/{purchaseOrder}', PurchaseOrderShow::class)
    ->name('purchase-orders.show');

Route::get('purchase-orders/{purchaseOrder}/edit', PurchaseOrderEdit::class)
    ->name('purchase-orders.edit');
```

---

## Navigation Integration

### Project Level

The project navigation menu (`resources/views/components/project-nav.blade.php`) includes a "Purchase Orders" link between "Expenses" and "Change Orders".

### Job Site Level

The job site detail page (`resources/views/livewire/job-site/job-site-show.blade.php`) includes a "Purchase Orders" tab with:
- Stats cards (Total POs, Pending, Approved Amount)
- PO list table with actions
- Add Purchase Order button

The `JobSiteShow` component (`app/Livewire/JobSite/JobSiteShow.php`) queries purchase orders filtered by `job_site_id`.

---

## Status Workflow

```
┌─────────┐     submitForApproval()     ┌─────────┐
│  DRAFT  │ ──────────────────────────► │ PENDING │
└─────────┘                             └─────────┘
     │                                       │
     │ cancel()                   approve()  │  reject()
     ▼                                 │     │
┌───────────┐                          ▼     ▼
│ CANCELLED │◄──────────────────  ┌──────────┐  ┌──────────┐
└───────────┘     cancel()        │ APPROVED │  │ REJECTED │
                  (if no payments)└──────────┘  └──────────┘
                        │                            │
                        │ reject()                   │ reviseAndResubmit()
                        │ (if no payments)           │
                        ▼                            ▼
                   ┌──────────┐              ┌─────────┐
                   │ REJECTED │              │ PENDING │
                   └──────────┘              └─────────┘
                                          (revision_number++)
```

### Status Transition Rules

| From | To | Method | Conditions |
|------|-----|--------|------------|
| draft | pending | `submitForApproval()` | Must have at least 1 item |
| draft | cancelled | `cancel()` | Always allowed |
| pending | approved | `approve()` | Creates linked expense |
| pending | rejected | `reject()` | Optional reason |
| pending | cancelled | `cancel()` | Optional reason |
| approved | rejected | `reject()` | Only if expense has no payments; deletes expense |
| approved | cancelled | `cancel()` | Only if expense has no payments; deletes expense |
| rejected | pending | `reviseAndResubmit()` | Increments revision_number |

---

## Expense Creation on Approval

When a PO is approved via `approve()`:

1. Status changes to 'approved'
2. `approved_by` and `approved_at` are set
3. New Expense is created with:
   - Same project_id, job_site_id, supplier_id
   - Item name: "PO #[po_number]" or "Purchase Order #[id]"
   - Same payment options (method, installments, frequency, due date)
   - Status: 'unpaid'
4. All PurchaseOrderItems are copied to ExpenseItems
5. Payment schedule is generated (if installments > 1)
6. PO's expense_id is set to link to the expense
7. Status change is recorded in history

---

## Protection Rules

### Editing Protection
- POs can only be edited when status is `draft` or `rejected`
- `canBeEdited()` method enforces this

### Status Change from Approved Protection
Before changing status from approved to rejected/cancelled:
1. Check if expense exists
2. If expense.status === 'paid' → **Block**
3. If expense has any paid payments → **Block**
4. Otherwise, delete expense and proceed

The `canChangeStatusFromApproved()` method enforces this.

---

## Files Created/Modified

### New Files

**Migrations:**
- `database/migrations/2026_01_30_100000_create_purchase_orders_table.php`
- `database/migrations/2026_01_30_100001_create_purchase_order_items_table.php`
- `database/migrations/2026_01_30_100002_create_purchase_order_status_histories_table.php`
- `database/migrations/2026_01_30_100003_add_purchase_order_id_to_expenses_table.php`

**Models:**
- `app/Models/PurchaseOrder.php`
- `app/Models/PurchaseOrderItem.php`
- `app/Models/PurchaseOrderStatusHistory.php`

**Livewire Components:**
- `app/Livewire/PurchaseOrder/PurchaseOrderCreate.php`
- `app/Livewire/PurchaseOrder/PurchaseOrderEdit.php`
- `app/Livewire/PurchaseOrder/PurchaseOrderShow.php`
- `app/Livewire/Project/ProjectPurchaseOrders.php`

**Views:**
- `resources/views/livewire/purchase-order/purchase-order-create.blade.php`
- `resources/views/livewire/purchase-order/purchase-order-edit.blade.php`
- `resources/views/livewire/purchase-order/purchase-order-show.blade.php`
- `resources/views/livewire/project/project-purchase-orders.blade.php`

### Modified Files

- `app/Models/Expense.php` - Added `purchaseOrder()` relationship and `isFromPurchaseOrder()` method
- `routes/web.php` - Added PO routes
- `resources/views/components/project-nav.blade.php` - Added Purchase Orders to navigation
- `resources/views/livewire/job-site/job-site-show.blade.php` - Added Purchase Orders tab
- `app/Livewire/JobSite/JobSiteShow.php` - Added purchase orders query

---

## Usage Examples

### Creating a Purchase Order from Project Level

1. Navigate to Project → Purchase Orders
2. Click "Add Purchase Order"
3. Select location (Project General or specific Job Site)
4. Add supplier, PO date, items
5. Configure payment options
6. Save as Draft or Submit for Approval

### Creating a Purchase Order from Job Site Level

1. Navigate to Job Site → Purchase Orders tab
2. Click "Add Purchase Order"
3. Location is automatically set to the job site (read-only)
4. Add supplier, PO date, items
5. Configure payment options
6. Save as Draft or Submit for Approval

### Approving a Purchase Order

1. Navigate to the pending PO
2. Click "Approve"
3. System creates linked expense with all items and payment schedule
4. PO status changes to "Approved"
5. Link to expense is displayed

### Handling Rejection

1. Navigate to the pending PO
2. Click "Reject"
3. Enter optional reason in modal
4. PO status changes to "Rejected"
5. User can edit and use "Revise & Resubmit" to increment revision and resubmit

---

## Technical Notes

### Cents vs Dollars
All monetary values are stored in cents (integer) in the database and converted to dollars in the application layer using Eloquent accessors.

### Cascade Deletes
- Deleting a PurchaseOrder cascades to items and status histories
- Deleting a PO does NOT cascade to the linked expense

### Receipt/Document Storage
Documents are stored in `storage/app/purchase-orders/` using Laravel's local disk.

### Auto-Assignment to Miscellaneous
Items without a budget_item_id are automatically assigned to the "Miscellaneous" budget item using `BudgetService::getMiscellaneousItem()`.
