# Payment Batch (Pre-Payment) Module

## Overview

The Payment Batch system provides a **staging area** for planning contract payments before they are committed. Unlike the Contract Payments Dashboard (which processes payments immediately), this module lets users create a batch, add planned payment amounts for multiple contracts, review/adjust, and only approve when ready — turning them into real `ContractPayment` records.

**Routes:**
- `GET /payment-batches` → `payment-batches.index` (list all batches)
- `GET /payment-batches/create` → `payment-batches.create` (create form)
- `GET /payment-batches/{id}` → `payment-batches.show` (read-only view)
- `GET /payment-batches/{id}/edit` → `payment-batches.edit` (main working page)

**Sidebar Location:** Projects > Payment Batches (after "Contract Payments")

---

## Database Schema

### `payment_batches` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Batch name (e.g., "March 2026 Payments") |
| status | enum | `draft`, `partially_approved`, `approved`, `cancelled` (default: `draft`) |
| payment_date | date | Target date for all payments in this batch |
| notes | text | Optional batch notes (nullable) |
| client_id | bigint | Saved filter: client (nullable, FK → clients, nullOnDelete) |
| project_id | bigint | Saved filter: project (nullable, FK → projects, nullOnDelete) |
| subcontractor_id | bigint | Saved filter: subcontractor (nullable, FK → subcontractors, nullOnDelete) |
| project_manager_id | bigint | Saved filter: project manager (nullable, FK → users, nullOnDelete) |
| contract_status_filter | string | Saved filter: contract status value (nullable) |
| show_zero_balance | boolean | Saved filter: include paid/cancelled contracts (default: false) |
| created_by | bigint | FK → users (nullable, nullOnDelete) |
| approved_by | bigint | FK → users (nullable, nullOnDelete) |
| approved_at | timestamp | When the batch was fully approved (nullable) |
| timestamps | | created_at, updated_at |

**Indexes:** `status`, `payment_date`

### `payment_batch_items` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| payment_batch_id | bigint | FK → payment_batches (cascadeOnDelete) |
| contract_id | bigint | FK → contracts (constrained) |
| amount | unsignedBigInteger | Planned payment amount in cents |
| payment_method | enum | Same options as contract_payments (nullable) |
| notes | text | Optional item notes (nullable) |
| status | enum | `pending`, `approved`, `rejected` (default: `pending`) |
| approved_at | timestamp | When this item was approved (nullable) |
| timestamps | | created_at, updated_at |

**Indexes:** `payment_batch_id`, `contract_id`, `status`
**Unique constraint:** `(payment_batch_id, contract_id)` — one item per contract per batch

---

## Models

### `app/Models/PaymentBatch.php`

#### Relationships

| Relationship | Type | Target |
|-------------|------|--------|
| `items()` | HasMany | PaymentBatchItem |
| `createdBy()` | BelongsTo | User (via `created_by`) |
| `approvedBy()` | BelongsTo | User (via `approved_by`) |
| `client()` | BelongsTo | Client (saved filter) |
| `project()` | BelongsTo | Project (saved filter) |
| `subcontractor()` | BelongsTo | Subcontractor (saved filter) |
| `projectManager()` | BelongsTo | User (via `project_manager_id`, saved filter) |

#### Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isDraft()` | bool | True when status is `draft` |
| `canBeEdited()` | bool | True when status is `draft` or `partially_approved` |
| `getTotalAmount()` | float | Sum of all items in dollars |
| `getStatusLabel()` | string | Human-readable status (e.g., "Partially Approved") |
| `getStatusColor()` | string | Color key: `gray`, `amber`, `green`, `red` |

### `app/Models/PaymentBatchItem.php`

#### Accessors (cents ↔ dollars)
- `amount` — converts between cents (database) and dollars (application), same pattern as `ContractPayment`

#### Relationships

| Relationship | Type | Target |
|-------------|------|--------|
| `batch()` | BelongsTo | PaymentBatch |
| `contract()` | BelongsTo | Contract |

#### Display Helpers
- `getPaymentMethodLabel()` — Returns human-readable label (e.g., "Credit Card")
- `getStatusLabel()` — Returns "Pending", "Approved", or "Rejected"
- `getStatusColor()` — Returns `amber`, `green`, or `red`

---

## Batch Status Lifecycle

```
                          ┌──────────┐
       Create             │  draft   │
       ─────────────────► │          │
                          └────┬─────┘
                               │
                     Approve item(s)
                               │
                    ┌──────────▼──────────┐
                    │ partially_approved   │
                    │                     │
                    └──────────┬──────────┘
                               │
                     All items processed
                     (approved or rejected)
                               │
                    ┌──────────▼──────────┐
                    │     approved        │
                    │                     │
                    └─────────────────────┘

  At any point (if no approved items):
                          ┌──────────┐
       Cancel             │cancelled │
       ─────────────────► │          │
                          └──────────┘
```

### Status Transition Rules

| From | To | Trigger |
|------|----|---------|
| `draft` | `partially_approved` | First item approved |
| `draft` | `cancelled` | User cancels (no approved items) |
| `partially_approved` | `approved` | All pending items resolved (approved or rejected) |
| `partially_approved` | `cancelled` | Not allowed (has approved items) |

---

## Item Status Lifecycle

```
  pending ──► approved   (creates real ContractPayment)
  pending ──► rejected   (no payment created, shown with strikethrough)
```

---

## Components

### 1. PaymentBatchIndex (`app/Livewire/PaymentBatch/PaymentBatchIndex.php`)

List page with search, status filter tabs, and paginated table.

#### Public Properties

| Property | Type | Purpose |
|----------|------|---------|
| `$search` | string | Search by batch name (URL-persisted) |
| `$statusFilter` | string | Filter by batch status (URL-persisted) |

#### Features
- Status tabs with counts: All, Draft, Partially Approved, Approved, Cancelled
- Search by batch name (debounced 300ms)
- Table columns: Name (with filter badges), Payment Date, Items Count, Total Amount, Status, Created By, Created At, Actions
- Filter badges displayed under batch name: Client (gray), Project (blue), Subcontractor (purple), PM (amber), Contract Status (green)
- Delete with `wire:confirm` (draft batches only)
- View/Edit buttons via `x-ui.view-edit-buttons`

#### Query Strategy
```php
PaymentBatch::query()
    ->with(['createdBy', 'client', 'project', 'subcontractor', 'projectManager'])
    ->withCount('items')
    ->withSum('items', 'amount')
    ->paginate(10)
```

---

### 2. PaymentBatchCreate (`app/Livewire/PaymentBatch/PaymentBatchCreate.php`)

Simple form to create a batch with name, date, notes, and **contract filters**.

#### Public Properties

| Property | Type | Purpose |
|----------|------|---------|
| `$name` | string | Batch name (required) |
| `$payment_date` | string | Payment date (required, defaults to today) |
| `$notes` | string | Optional notes |
| `$client_id` | string | Filter: client |
| `$project_id` | string | Filter: project (cascaded from client) |
| `$subcontractor_id` | string | Filter: subcontractor |
| `$project_manager_id` | string | Filter: project manager |
| `$contract_status_filter` | string | Filter: contract status |
| `$show_zero_balance` | bool | Filter: include paid/cancelled |

#### Behavior
- Client filter cascades to project filter (changing client resets invalid project)
- All filters are saved to the batch record
- On save: creates batch as `draft`, redirects to Edit page

---

### 3. PaymentBatchEdit (`app/Livewire/PaymentBatch/PaymentBatchEdit.php`)

**This is the core page.** Replicates the Contract Payments Dashboard pattern but saves to batch items instead of creating real payments immediately.

#### Public Properties

| Property | Type | Purpose |
|----------|------|---------|
| `$name` | string | Editable batch name |
| `$payment_date` | string | Editable payment date |
| `$notes` | string | Editable notes |
| `$clientFilter` | string | Contract filter: client (loaded from batch) |
| `$projectFilter` | string | Contract filter: project (loaded from batch) |
| `$subcontractorFilter` | string | Contract filter: subcontractor (loaded from batch) |
| `$projectManagerFilter` | string | Contract filter: PM (loaded from batch) |
| `$statusFilter` | string | Contract filter: contract status (loaded from batch) |
| `$showZeroBalance` | bool | Contract filter: show paid/cancelled (loaded from batch) |
| `$payAmounts` | array | Keyed by contract ID — inline amount inputs |
| `$payMethods` | array | Keyed by contract ID — inline payment method selects |
| `$payNotes` | array | Keyed by contract ID — inline notes inputs |

> **Note:** Filters are NOT `#[Url]`-persisted. They are loaded from the batch on mount and saved back to the batch on "Save Draft".

#### Computed Properties

| Property | Description |
|----------|-------------|
| `clients` | Clients with contracts, for dropdown |
| `projects` | Projects with contracts, filtered by client, for dropdown |
| `projectManagers` | Users managing projects with contracts, for dropdown |
| `subcontractors` | Subcontractors with contracts, for dropdown |
| `batchSummary` | Total items, total amount, pending/approved/rejected counts and amounts |

#### Methods

| Method | Description |
|--------|-------------|
| `mount()` | Loads batch fields + saved filters, redirects to Show if not editable |
| `loadExistingItems()` | Populates `$payAmounts`, `$payMethods`, `$payNotes` from pending batch items |
| `saveDraft()` | Validates, updates batch (name, date, notes, filters), upserts items via `updateOrCreate`, removes cleared pending items |
| `approveItem($id)` | Validates balance, creates `ContractPayment` in transaction, marks item approved, calls `updateStatusFromPayments()` |
| `approveAll()` | Validates all pending items, processes in single transaction, updates batch status |
| `rejectItem($id)` | Marks item as rejected (no payment created) |
| `cancelBatch()` | Sets batch to cancelled (only if no approved items), redirects to index |
| `updateBatchStatus()` | Auto-transitions batch: all done → approved, mixed → partially_approved |

#### Query Strategy (render)

```php
Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment'])
    ->withSum('payments as total_paid_cents', 'amount')
    ->withSum('changeOrders as change_orders_total_cents', 'amount')
    // filters applied via ->when()
    ->unless($showZeroBalance, exclude paid/cancelled)
    ->orderBy('project_id')->orderBy('job_site_id')
    ->paginate(50)
```

Batch items are loaded separately and keyed by `contract_id` for O(1) lookup in the view.

#### Validation Rules (saveDraft)

| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| payment_date | required, date |
| notes | nullable, string |

#### Approval Validation (per item)

- Payment amount must not exceed contract's remaining balance (including change orders)
- Balance check: `contract.amount + changeOrdersTotal - totalPaid`
- Tolerance of +$0.01 for floating-point rounding

---

### 4. PaymentBatchShow (`app/Livewire/PaymentBatch/PaymentBatchShow.php`)

Read-only view of a batch with details, summary, and items table.

#### Computed Properties

| Property | Description |
|----------|-------------|
| `summary` | Total, approved, pending, rejected amounts |

#### View Sections (top to bottom)
1. **Header** — Batch name + status badge + Back/Edit buttons
2. **Batch Details Card** — Payment date, created by/at, approved by/at, notes, saved filter badges
3. **Summary Cards (4)** — Total Amount (blue), Approved (green), Pending (amber), Rejected (red)
4. **Items Table** — Subcontractor, Project, Job Site, Contract #, Amount, Method, Notes, Status badge

#### Items Table Styling
- Approved items: green background tint
- Rejected items: red background tint, text with strikethrough
- Pending items: default styling

---

## View Layout

### Index Page Table Columns

| # | Column | Notes |
|---|--------|-------|
| 1 | Name | With filter badges below (Client, Project, Subcontractor, PM, Status) |
| 2 | Payment Date | Formatted m/d/Y |
| 3 | Items | Count from `withCount` |
| 4 | Total Amount | Sum from `withSum` (cents → dollars) |
| 5 | Status | Color-coded badge |
| 6 | Created By | User name |
| 7 | Created At | Date formatted |
| 8 | Actions | View, Edit (if editable), Delete (if draft) |

### Edit Page Table Columns

| # | Column | Notes |
|---|--------|-------|
| 1 | Subcontractor | Company name |
| 2 | Project | Project name + client name subtitle |
| 3 | Job Site / Lot | Job site name or "Project General" |
| 4 | Contract # | Clickable link to contract show page |
| 5 | Amount | Adjusted amount (original + change orders) |
| 6 | Paid | Total paid (green) |
| 7 | Balance | Remaining balance (amber if > 0, green if 0) |
| 8 | Batch Amount | Inline input (pending), read-only display (approved/rejected) |
| 9 | Method | Inline select (pending), read-only label (approved/rejected) |
| 10 | Notes | Inline input (pending), read-only (approved/rejected) |
| 11 | Item Status | Badge: Pending (amber), Approved (green), Rejected (red) |
| 12 | Actions | Approve/Reject buttons (pending only) |

---

## Saved Filters

Contract filters are persisted on the `payment_batches` table so they reload automatically when reopening a batch. This means each batch has its own "scope" of contracts.

- **Set at creation** via the Create form
- **Editable on the Edit page** via live filter dropdowns
- **Saved on each "Save Draft"** click
- **Displayed on the Show page** as pill badges
- **Displayed on the Index page** as small badges under the batch name

---

## How It Works — User Workflow

1. Navigate to **Projects > Payment Batches** in the sidebar
2. Click **"New Batch"** — enter name, date, and optionally set contract filters
3. Redirected to the **Edit page** — contracts table shows filtered contracts
4. Enter **planned payment amounts**, methods, and notes for each contract
5. Click **"Save Draft"** — items are saved as `pending` batch items, filters are saved
6. Review the batch — adjust amounts as needed, save again
7. **Approve individually** — click the check icon per row to process that payment immediately
8. Or **"Approve All Pending"** — processes all pending items at once
9. **Reject items** — click the X icon to reject (shown with strikethrough, no payment created)
10. Once all items are processed → batch auto-transitions to `approved`
11. View the batch in **Show page** for a read-only summary

---

## Edge Cases Handled

| Scenario | Behavior |
|----------|----------|
| Payment exceeds balance | Validation error, approval blocked |
| Approve all with one exceeding balance | All blocked, error lists the specific contracts |
| Cancel with approved items | Blocked — "Cannot cancel a batch that has approved items" |
| Edit a non-editable batch | Redirected to Show page on mount |
| Delete a non-draft batch | Blocked — "Only draft batches can be deleted" |
| Cleared amount on save | Pending item removed from batch |
| Approved item on save | Not affected — only pending items are upserted/removed |
| Concurrent approval | DB transaction + `refresh()` before status update |
| Client filter changed | Project filter reset if current project doesn't belong to new client |
| Filter entity deleted | FK with nullOnDelete — filter cleared, batch still works |

---

## Relationship to Existing Payment Flow

The Payment Batch system is completely separate from the Contract Payments Dashboard:

| | Contract Payments Dashboard | Payment Batches |
|-|---------------------------|-----------------|
| **Table** | Uses `contract_payments` directly | Uses `payment_batch_items` (staging) |
| **Processing** | Immediate — payments created on submit | Staged — payments created only on approval |
| **Lifecycle** | Single action | draft → partially_approved → approved |
| **Filters** | URL-persisted, ephemeral | Saved on the batch record |
| **Integration** | N/A | On approval: creates `ContractPayment`, calls `updateStatusFromPayments()` |

Both use the same `ContractPayment` model and `Contract::updateStatusFromPayments()` method for the actual payment processing.

---

## Files

### Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_01_100000_create_payment_batches_table.php` | Batch header table |
| `database/migrations/2026_03_01_100001_create_payment_batch_items_table.php` | Batch items table |
| `database/migrations/2026_03_02_100000_add_filters_to_payment_batches_table.php` | Saved filter columns |
| `app/Models/PaymentBatch.php` | Batch model with relationships, helpers, status methods |
| `app/Models/PaymentBatchItem.php` | Item model with cents/dollars accessor, status helpers |
| `app/Livewire/PaymentBatch/PaymentBatchIndex.php` | List page component |
| `app/Livewire/PaymentBatch/PaymentBatchCreate.php` | Create form component |
| `app/Livewire/PaymentBatch/PaymentBatchEdit.php` | Main working page component |
| `app/Livewire/PaymentBatch/PaymentBatchShow.php` | Read-only view component |
| `resources/views/livewire/payment-batch/payment-batch-index.blade.php` | Index view |
| `resources/views/livewire/payment-batch/payment-batch-create.blade.php` | Create view |
| `resources/views/livewire/payment-batch/payment-batch-edit.blade.php` | Edit view |
| `resources/views/livewire/payment-batch/payment-batch-show.blade.php` | Show view |

### Modified

| File | Changes |
|------|---------|
| `routes/web.php` | Added 4 payment-batch routes + use statements |
| `resources/views/components/layouts/inc/sidebar.blade.php` | Added "Payment Batches" link in Projects submenu, updated `routeIs` check |

---

## Cascade Deletes

- Deleting a **PaymentBatch** cascades to all its **PaymentBatchItem** records (database-level via `cascadeOnDelete`)
- Deleting a **Contract** does NOT cascade to batch items (constrained, not cascade) — items would need manual cleanup if a contract is deleted while a batch references it
- No file cleanup needed — neither model has file columns
