# Estimate Module Documentation

## Overview

The Estimate module provides a complete workflow for creating, managing, and tracking estimates sent to clients. Estimates support catalog and custom line items with per-item and overall discounts, tax calculations, predefined payment terms, and message templates from the DocumentMessage system. The schema is designed for future invoice conversion.

## Key Features

- Client, project, and job site association (cascading search dropdowns)
- Catalog items with inherited tax settings + custom items
- Per-item discounts (percentage or fixed)
- Overall estimate discount (percentage or fixed)
- Per-item tax calculations with configurable tax rates
- Predefined payment terms (Net 15/30/60/90) with auto-calculated due dates
- Message templates from DocumentMessage system (type=estimate), editable per estimate
- Status workflow: Draft → Sent → Accepted / Declined
- Sequential estimate numbering (EST-0001, EST-0002, etc.)
- Prepared for future invoice conversion (`converted_to_invoice_id` field)

---

## Database Schema

### 1. `estimates` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| client_id | bigint | FK to clients (cascadeOnDelete) |
| project_id | bigint | FK to projects (nullable, nullOnDelete) |
| job_site_id | bigint | FK to job_sites (nullable, nullOnDelete) |
| estimate_number | string | Unique (EST-0001 format) |
| estimate_date | date | Date of estimate |
| terms | enum | net_15, net_30, net_60, net_90 |
| due_date | date | Auto-calculated from estimate_date + terms |
| status | enum | draft, sent, accepted, declined (default: draft) |
| message_title | string | Snapshot from DocumentMessage (nullable) |
| message_body | text | Snapshot, editable by user (nullable) |
| discount_type | enum | percentage, fixed (nullable) |
| discount_value | decimal(10,2) | The % or $ value entered (nullable) |
| discount_amount | unsignedBigInteger | Calculated discount in cents |
| subtotal | unsignedBigInteger | Sum of line net amounts in cents |
| tax_total | unsignedBigInteger | Sum of line tax amounts in cents |
| total_amount | unsignedBigInteger | subtotal - discount + tax in cents |
| notes | text | Internal notes (nullable) |
| converted_to_invoice_id | unsignedBigInteger | Future FK for invoice conversion (nullable) |
| sent_at | timestamp | When marked as sent (nullable) |
| accepted_at | timestamp | When accepted (nullable) |
| declined_at | timestamp | When declined (nullable) |
| created_by | bigint | FK to users |
| timestamps | | created_at, updated_at |

**Indexes:** client_id, project_id, status, estimate_number, estimate_date

### 2. `estimate_items` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| estimate_id | bigint | FK to estimates (cascadeOnDelete) |
| catalog_item_id | bigint | FK to catalog_items (nullable, nullOnDelete) |
| item_type | enum | catalog, custom |
| item_name | string | Item name |
| description | text | Optional description (nullable) |
| quantity | decimal(10,2) | Quantity |
| unit | string | Unit of measure (nullable) |
| unit_price | unsignedBigInteger | Price per unit in cents |
| discount_type | enum | percentage, fixed (nullable) |
| discount_value | decimal(10,2) | Discount % or $ value (nullable) |
| discount_amount | unsignedBigInteger | Calculated discount in cents |
| is_taxable | boolean | Default false |
| tax_rate | decimal(5,4) | Snapshot of rate (e.g., 0.0825 for 8.25%) |
| tax_amount | unsignedBigInteger | Calculated tax in cents |
| total_amount | unsignedBigInteger | (qty*price - discount) + tax in cents |
| sort_order | unsignedInteger | Display order (default 0) |
| timestamps | | created_at, updated_at |

**Indexes:** estimate_id, catalog_item_id

---

## Calculation Logic

### Per Line Item

1. `line_subtotal = quantity * unit_price`
2. `line_discount = discount_type == 'percentage' ? line_subtotal * (discount_value/100) : discount_value`
3. `line_net = line_subtotal - line_discount`
4. `line_tax = is_taxable ? line_net * tax_rate : 0`
5. `line_total = line_net + line_tax`

### Estimate Totals

1. `subtotal = sum of all line_net values`
2. `overall_discount = discount_type == 'percentage' ? subtotal * (value/100) : value`
3. `tax_total = sum of all line_tax values` (tax calculated before overall discount)
4. `total_amount = subtotal - overall_discount + tax_total`

---

## Models

### Estimate (`app/Models/Estimate.php`)

**Relationships:**
- `client()` - BelongsTo Client
- `project()` - BelongsTo Project (nullable)
- `jobSite()` - BelongsTo JobSite (nullable)
- `items()` - HasMany EstimateItem (ordered by sort_order)
- `createdBy()` - BelongsTo User

**Money Accessors (cents ↔ dollars):**
- `subtotal`, `taxTotal`, `totalAmount`, `discountAmount`

**Status Helpers:**
- `isDraft()` - Status is 'draft'
- `isSent()` - Status is 'sent'
- `isAccepted()` - Status is 'accepted'
- `isDeclined()` - Status is 'declined'
- `canBeEdited()` - Returns true if draft or sent
- `canBeSent()` - Returns true if draft with items

**Static Methods:**
- `generateEstimateNumber()` - Generates next sequential EST-XXXX number
- `calculateDueDate($date, $terms)` - Adds term days to estimate date

**Computed Attributes:**
- `status_color` - CSS classes for status badge
- `status_label` - Human-readable status text
- `terms_label` - Human-readable terms (e.g., "Net 30")

### EstimateItem (`app/Models/EstimateItem.php`)

**Relationships:**
- `estimate()` - BelongsTo Estimate
- `catalogItem()` - BelongsTo CatalogItem (nullable)

**Money Accessors:**
- `unitPrice`, `discountAmount`, `taxAmount`, `totalAmount`

**Helpers:**
- `isCustom()` - Returns true if item_type is 'custom'

---

## Status Workflow

```
┌─────────┐     markAsSent()     ┌────────┐
│  DRAFT  │ ───────────────────► │  SENT  │
└─────────┘                      └────────┘
                                      │
                        markAsAccepted() │  markAsDeclined()
                                   │     │
                                   ▼     ▼
                             ┌──────────┐  ┌──────────┐
                             │ ACCEPTED │  │ DECLINED │
                             └──────────┘  └──────────┘
```

### Status Transition Rules

| From | To | Method | Conditions |
|------|-----|--------|------------|
| draft | sent | `markAsSent()` | Must be draft |
| sent | accepted | `markAsAccepted()` | Must be sent |
| sent | declined | `markAsDeclined()` | Must be sent |

### Edit/Delete Permissions

| Status | Can Edit | Can Delete |
|--------|----------|------------|
| Draft | Yes | Yes |
| Sent | Yes | Yes |
| Accepted | No | No |
| Declined | No | No |

---

## Livewire Components

### 1. EstimateIndex (`app/Livewire/Estimate/EstimateIndex.php`)

**View:** `resources/views/livewire/estimate/estimate-index.blade.php`

**Features:**
- List all estimates with search (by estimate_number or client company_name)
- Status filter tabs with counts (All, Draft, Sent, Accepted, Declined)
- Table columns: Estimate #, Client, Project, Date, Due Date, Status badge, Total, Actions
- Pagination
- View/Edit buttons (edit only for draft/sent)
- Delete with `wire:confirm` (only for draft/sent)

### 2. EstimateCreate (`app/Livewire/Estimate/EstimateCreate.php`)

**View:** `resources/views/livewire/estimate/estimate-create.blade.php`

**Features:**

**Header Section:**
- Client search dropdown (required, debounce 300ms)
- Project search dropdown (optional, filtered by selected client)
- Job Site search dropdown (optional, filtered by selected project)
- Estimate Number (auto-generated, readonly)
- Estimate Date (defaults to today)
- Terms dropdown (Net 15/30/60/90)
- Due Date (auto-calculated, readonly)

**Items Section (modal-based):**
- Add Item button opens modal
- Toggle: Custom Item / From Catalog
- Catalog search with debounce (auto-fills name, unit, price, tax settings)
- Item Name, Description, Quantity, Unit (predefined dropdown), Unit Price
- Per-item discount: type toggle (% / $) + value
- Taxable toggle with tax rate selector (from TaxRate model)
- Live-calculated: Line Subtotal, Discount, Tax, Line Total

**Predefined Unit Options:**
Unit, Hour, Day, Week, Month, Sq Ft, Ln Ft, Cu Yd, Ton, Load, Lot

**Totals Section:**
- Subtotal (sum of line nets)
- Overall Discount: type toggle (% / $) + value
- Tax Total
- Grand Total

**Message Section:**
- Dropdown to select from active DocumentMessage (type=estimate)
- Pre-selects default message (is_default=true)
- Message body displayed in TinyMCE editor (editable)
- Changes stored as snapshot on estimate, not modifying template

**Notes Section:**
- Simple textarea for internal notes

**Actions:** Cancel, Save as Draft

### 3. EstimateShow (`app/Livewire/Estimate/EstimateShow.php`)

**View:** `resources/views/livewire/estimate/estimate-show.blade.php`

**Features:**
- Two-column layout: main content + sidebar
- Estimate details card (client, project, jobsite, dates, terms, created by, timestamps)
- Items table with all columns and totals breakdown
- Message display (rendered HTML)
- Internal notes display

**Sidebar Actions (status-based):**
- **Draft:** Mark as Sent, Edit Estimate, Delete
- **Sent:** Mark Accepted, Mark Declined, Edit Estimate, Delete
- **Accepted:** "This estimate has been accepted" message
- **Declined:** "This estimate has been declined" message

**Sidebar Summary Card:**
- Item count, Subtotal, Discount, Tax, Total

### 4. EstimateEdit (`app/Livewire/Estimate/EstimateEdit.php`)

**View:** `resources/views/livewire/estimate/estimate-edit.blade.php`

**Features:**
- Same form as Create but pre-populated with existing estimate data
- Only accessible when status is draft or sent (redirects to show otherwise)
- Loads all items from DB into editable array
- On save: updates estimate, deletes old items, recreates from current array
- Save redirects to show page

---

## Routes

```php
Route::get('estimates', EstimateIndex::class)->name('estimates.index');
Route::get('estimates/create', EstimateCreate::class)->name('estimates.create');
Route::get('estimates/{estimate}', EstimateShow::class)->name('estimates.show');
Route::get('estimates/{estimate}/edit', EstimateEdit::class)->name('estimates.edit');
```

---

## Navigation

Estimates appears as a top-level menu item in the sidebar, between Payments and Settings, with a calculator icon. Route detection: `request()->routeIs('estimates.*')`.

---

## Integration Points

### CatalogItem
When selecting a catalog item for a line item:
- Name, unit, and unit price are auto-filled
- `is_taxable` is inherited from catalog item
- `tax_rate` is inherited from the catalog item's linked TaxRate

### TaxRate
- Custom items default to the system default tax rate when taxable toggle is enabled
- Tax rate selector shows all active rates from the TaxRate model
- Rate is stored as a snapshot on the estimate item (decimal, e.g., 0.0825)

### DocumentMessage
- Messages of type `estimate` are available for selection
- Default message (is_default=true) is pre-selected on create
- Message title and body are stored as snapshots on the estimate
- Body is editable via TinyMCE without modifying the template

---

## Files

### New Files

**Migrations:**
- `database/migrations/2026_02_09_200000_create_estimates_table.php`
- `database/migrations/2026_02_09_200001_create_estimate_items_table.php`

**Models:**
- `app/Models/Estimate.php`
- `app/Models/EstimateItem.php`

**Livewire Components:**
- `app/Livewire/Estimate/EstimateIndex.php`
- `app/Livewire/Estimate/EstimateCreate.php`
- `app/Livewire/Estimate/EstimateShow.php`
- `app/Livewire/Estimate/EstimateEdit.php`

**Views:**
- `resources/views/livewire/estimate/estimate-index.blade.php`
- `resources/views/livewire/estimate/estimate-create.blade.php`
- `resources/views/livewire/estimate/estimate-show.blade.php`
- `resources/views/livewire/estimate/estimate-edit.blade.php`

### Modified Files

- `routes/web.php` - Added estimate routes
- `resources/views/components/layouts/inc/sidebar.blade.php` - Added Estimates nav item

---

## Technical Notes

### Cents vs Dollars
All monetary values are stored in cents (unsignedBigInteger) in the database and converted to dollars in the application layer using Eloquent accessors.

### Cascade Deletes
- Deleting a Client cascades to all linked estimates
- Deleting an Estimate cascades to all estimate items
- Deleting a Project or JobSite sets the FK to null (nullOnDelete)
- Deleting a CatalogItem sets the FK to null on estimate items (nullOnDelete)

### Search Dropdowns
Client, Project, Job Site, and Catalog Item fields all use search-based dropdowns with debounce (300ms for most, 500ms for discount values). This is designed for scalability with thousands of records — no full lists are loaded into memory.

### Message Snapshots
The message title and body are stored directly on the estimate record as snapshots. Changing or deleting the DocumentMessage template after an estimate is created does not affect existing estimates.
