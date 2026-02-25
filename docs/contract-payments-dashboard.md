# Contract Payments Dashboard

## Overview

The Contract Payments Dashboard replaces the previous Excel spreadsheet workflow for managing subcontractor payments. It provides a single page where users can view all contracts across all projects/job sites, apply filters, and process batch payments — entering "pay today" amounts for multiple contracts and submitting them all at once.

**Route:** `GET /contract-payments`
**Route Name:** `contract-payments.index`
**Sidebar Location:** Projects > Contract Payments (after "Payments")

---

## Files Created

### 1. `app/Livewire/Contract/ContractPayments.php`

Full-page Livewire component that powers the entire dashboard.

#### Public Properties

| Property | Type | Purpose |
|---|---|---|
| `$clientFilter` | `string` | Filter contracts by client (persisted in URL) |
| `$projectFilter` | `string` | Filter contracts by project — cascading, updates based on client selection (persisted in URL) |
| `$subcontractorFilter` | `string` | Filter contracts by subcontractor (persisted in URL) |
| `$statusFilter` | `string` | Filter by status: active, completed, partially_paid, paid, cancelled (persisted in URL) |
| `$showZeroBalance` | `bool` | Toggle to include paid/cancelled contracts in the table. Default: `false` (persisted in URL) |
| `$paymentDate` | `string` | Single date input shared by all batch payments. Defaults to today |
| `$payAmounts` | `array` | Keyed by contract ID — inline amount inputs from the table |
| `$payMethods` | `array` | Keyed by contract ID — inline payment method dropdowns from the table |
| `$payNotes` | `array` | Keyed by contract ID — inline notes text inputs from the table |
| `$expandedContracts` | `array` | Array of contract IDs whose change order details are expanded in the table |

All filter properties use the `#[Url]` attribute so filter state is preserved in the browser URL and survives page refreshes.

#### Computed Properties

| Property | Description |
|---|---|
| `clients` | Clients that have projects with at least one contract. Used for the client dropdown |
| `projects` | Projects that have contracts, filtered by current client selection. Used for the project dropdown |
| `subcontractors` | Subcontractors that have at least one contract. Used for the subcontractor dropdown |
| `contracts` | Main data query — eager loads `project.client`, `jobSite`, `subcontractor`, `latestPayment`, `changeOrders` and uses `withSum('payments as total_paid_cents', 'amount')` + `withSum('changeOrders as change_orders_total_cents', 'amount')` to get totals in single subqueries (avoids N+1). Applies all active filters. Excludes paid/cancelled by default unless `$showZeroBalance` is true. Ordered by `project_id` then `job_site_id` |
| `summary` | Returns 4 metrics: pending balance, active contracts count, paid this month, total contract value. All respect the currently active filters |

#### Methods

| Method | Description |
|---|---|
| `mount()` | Sets `$paymentDate` to today's date |
| `updatedClientFilter()` | When client changes, resets project filter if the currently selected project doesn't belong to the new client |
| `toggleChangeOrders($contractId)` | Toggles a contract's change order details expansion in the table. Adds/removes the contract ID from `$expandedContracts` array |
| `processPayments()` | Validates payment date, collects rows with amount > 0, validates each row (amount doesn't exceed balance), creates `ContractPayment` records in a DB transaction, calls `updateStatusFromPayments()` on each contract, resets inline inputs |
| `exportCsv()` | Exports a CSV file with all currently filtered contracts. Uses `response()->stream()` with `fputcsv()`. Includes a "Type" column (Contract/Change Order) with detail rows for each change order below its contract. Columns: Type, Subcontractor, Project, Client, Job Site, Contract #, Original Amount, Change Orders (+/-), Adjusted Amount, Paid, Balance, Status, Last Payment Date, Last Payment Amount, CO Date, CO Title, CO Description, CO Amount. Filename: `contract-payments-YYYY-MM-DD.csv` |

#### Query Strategy

The main `contracts` computed property uses a single query with eager loading:

```php
Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment', 'changeOrders'])
    ->withSum('payments as total_paid_cents', 'amount')
    ->withSum('changeOrders as change_orders_total_cents', 'amount')
    // filters applied via ->when()
    ->unless($showZeroBalance, exclude paid/cancelled)
    ->orderBy('project_id')->orderBy('job_site_id')
    ->get()
```

Balance is computed in the view: `$contract->amount + $changeOrdersTotal - $totalPaidDollars`

> The dashboard now accounts for change orders in the balance calculation, consistent with the Contract model's `getBalanceDue()` method.

#### Validation Rules (processPayments)

- Payment date is required
- At least one row must have an amount > 0
- Each row's amount must not exceed the contract's remaining balance
- Payment method is **optional** (can be left blank)
- Rows with blank/zero amounts are skipped entirely
- All payments are created inside a single DB transaction for data integrity

---

### 2. `resources/views/livewire/contract/contract-payments.blade.php`

The Blade view with the following layout sections (top to bottom):

#### Page Header
Title "Contract Payments" with subtitle.

#### Flash Messages
- **Success** (green): Shown after payments are processed successfully
- **Error** (red): Shown for validation errors or empty payment submissions

#### Summary Cards (4-column grid)

| Card | Color | Data |
|---|---|---|
| Pending Balance | Blue | Sum of (contract amount - paid) for non-paid/cancelled contracts |
| Active Contracts | Amber | Count of contracts with `status = 'active'` |
| Paid This Month | Green | Sum of all `ContractPayment` records with `payment_date` in the current month |
| Total Contract Value | Purple | Sum of all non-cancelled contract amounts |

All summary metrics respect the currently active filters.

#### Filters Bar
White card with a flex row containing:
- **Client dropdown** (`wire:model.live`) — cascading, affects project dropdown
- **Project dropdown** (`wire:model.live`) — only shows projects belonging to selected client
- **Subcontractor dropdown** (`wire:model.live`)
- **Status dropdown** (`wire:model.live`) — active, completed, partially_paid, paid, cancelled
- **"Show Paid/Cancelled" toggle** (`wire:model.live`) — Tailwind CSS toggle switch

#### Payment Date Bar
- Date input defaulting to today
- **"Export CSV"** button (`outline` variant, `download` icon) — downloads a CSV of all currently filtered contracts with change order detail rows
- **"Export PDF"** dropdown button (`outline` variant) with two options:
  - **Summary** — PDF with contracts table and change order details (no payment history)
  - **Detailed (w/ Payments)** — Same PDF plus full payment history per contract
- **"Process Payments"** button with `wire:confirm` for user confirmation before batch processing

#### Contracts Table (12 columns)

| # | Column | Source | Notes |
|---|---|---|---|
| 1 | Subcontractor | `contract.subcontractor.company_name` | Shows "-" if null |
| 2 | Project | `contract.project.project_name` | Clickable link to `projects.overview`. Client name shown as subtitle |
| 3 | Job Site / Lot | `contract.jobSite.job_site_name` | Clickable link to `jobsites.overview`. Shows plain text "Project General" if null |
| 4 | Contract # | `contract.contract_number` | Clickable link to `contracts.show` |
| 5 | Amount | `contract.amount` | Right-aligned, currency formatted |
| 6 | Change Orders | `change_orders_total_cents` | Green if >= 0, red if negative. Shows `+` prefix for positive values. Expand button (chevron) toggles detail sub-row. Shows "—" if no change orders |
| 7 | Paid | Computed from `total_paid_cents` | Right-aligned, green text |
| 8 | Balance | Amount + CO - Paid | Amber if > 0, green if 0. Now includes change orders |
| 9 | Last Payment | `contract.latestPayment` | Date on first line, amount on second line |
| 10 | Pay Today | Inline `<input type="number">` | `wire:model.blur`, only shown for rows with balance > 0 |
| 11 | Method | Inline `<select>` | `wire:model`, optional, only shown for rows with balance > 0 |
| 12 | Notes | Inline `<input type="text">` | `wire:model.blur`, only shown for rows with balance > 0 |

#### Expandable Change Order Detail Row

When the user clicks the chevron button in the Change Orders column, a sub-row expands below the contract showing:
- Section header: "Change Orders (count)"
- Nested table with columns: Date, Title, Description (truncated), Amount (green/red)
- Chevron rotates 180° when expanded

**Row styling:**
- Paid/cancelled rows: muted with `opacity-50 bg-slate-50`, no inline inputs shown
- Active rows: normal styling with hover effect
- Empty state shown when no contracts match current filters

**Payment method options:** Cash, Check, Credit Card, Debit Card, Bank Transfer, PIX (only when `config('app.country') === 'BR'`), Other

---

### 3. `database/migrations/2026_02_15_142743_make_payment_method_nullable_on_contract_payments_table.php`

Migration that alters the `contract_payments.payment_method` column:
- **Before:** `enum(...)->default('check')` — NOT NULL
- **After:** `enum(...)->nullable()->default(null)` — NULL allowed

This was needed because users don't always specify a payment method when processing batch payments.

---

## Files Modified

### 4. `app/Models/Contract.php`

**Added:**
- `use Illuminate\Database\Eloquent\Relations\HasOne;` import
- `latestPayment()` relationship:

```php
public function latestPayment(): HasOne
{
    return $this->hasOne(ContractPayment::class)->latestOfMany('payment_date');
}
```

This uses Laravel's `latestOfMany()` which generates an efficient single subquery to fetch the most recent payment by `payment_date`. Avoids N+1 queries when used with eager loading (`->with('latestPayment')`).

### 5. `app/Models/Subcontractor.php`

**Added:**
- `contracts()` relationship:

```php
public function contracts(): HasMany
{
    return $this->hasMany(Contract::class);
}
```

Needed for the `whereHas('contracts')` filter in the subcontractors dropdown on the dashboard.

### 6. `routes/web.php`

**Added:**
- `use App\Livewire\Contract\ContractPayments;` import
- Route definition after the existing payments route:

```php
Route::get('contract-payments', ContractPayments::class)->name('contract-payments.index');
```

### 7. `resources/views/components/layouts/inc/sidebar.blade.php`

**Modified:**
- Updated the Projects parent button `routeIs` check to include `contract-payments.*` so the Projects submenu highlights when on the contract payments page
- Added "Contract Payments" nav link after the existing "Payments" link in the Projects submenu, with a wallet/money icon

---

## How It Works — User Workflow

1. Navigate to **Projects > Contract Payments** in the sidebar
2. The page loads showing all non-paid/cancelled contracts grouped by project
3. Use the **filter dropdowns** to narrow down by client, project, subcontractor, or status
4. Toggle **"Show Paid/Cancelled"** to see fully paid or cancelled contracts (shown muted)
5. Optionally click **"Export CSV"** to download the currently filtered data as a CSV file (opens in Excel)
6. Set the **Payment Date** (defaults to today)
7. For each contract to pay, enter the amount in the **"Pay Today"** column
8. Optionally select a **payment method** and add **notes** for each row
9. Click **"Process Payments"** — a confirmation dialog appears
10. On confirm, all entered payments are created in a single database transaction
11. Contract statuses are automatically updated (completed → partially_paid → paid) via `updateStatusFromPayments()`
12. The table refreshes showing updated balances, and inline inputs are cleared

---

## Edge Cases Handled

| Scenario | Behavior |
|---|---|
| Active contracts fully paid | Auto-transitions to `paid` via `updateStatusFromPayments()` |
| Contract with change orders | Balance includes change orders: `amount + CO total - paid` |
| No change orders on contract | Change Orders column shows "—", no expand button |
| Null subcontractor | Displays "-" in the table |
| Null job site | Displays "Project General" |
| Payment exceeds balance | Validation error shown, payment blocked |
| No amounts entered | Flash error "No payment amounts entered" |
| Payment method not selected | Allowed — saved as NULL |
| Concurrent payments | DB transaction + `refresh()` before status update prevents overpayment |
| Client filter changes | Project filter resets if current project doesn't belong to new client |
| PIX payment method | Only shown when `config('app.country') === 'BR'` |

---

## Database Impact

### Tables Read
- `contracts` — main data with filters
- `contract_payments` — aggregated via `withSum`, latest via `latestOfMany`
- `projects` — eager loaded, used in filters
- `clients` — eager loaded, used in filters
- `subcontractors` — eager loaded, used in filters
- `job_sites` — eager loaded for display

### Tables Written
- `contract_payments` — new payment records created by `processPayments()`
- `contracts` — status updated via `updateStatusFromPayments()`
- `contract_status_histories` — status change history recorded automatically

### Migration Applied
- `contract_payments.payment_method` changed from NOT NULL with default `'check'` to NULLABLE with default `NULL`

---

## PDF Export

### Controller: `app/Http/Controllers/ContractPaymentsPdfController.php`

Generates a landscape-oriented PDF report using `barryvdh/laravel-dompdf`. Accepts the same filter query parameters as the dashboard.

**Routes:**
- `GET /contract-payments/pdf` → `contract-payments.pdf.download` (triggers download)
- `GET /contract-payments/pdf/view` → `contract-payments.pdf.view` (displays in browser)

**Query Parameters:**
- `client`, `project`, `subcontractor`, `project_manager`, `status`, `show_zero_balance` — same filters as the dashboard
- `include_payments` — when `1`, includes full payment history per contract (detailed version)

**Methods:**
| Method | Description |
|---|---|
| `download(Request)` | Generates and downloads the PDF |
| `stream(Request)` | Generates and streams the PDF in browser |
| `buildPdfData(Request)` | Shared data builder for both methods |
| `getFilteredContracts(Request, bool)` | Queries contracts with filters, conditionally eager loads `payments` |
| `getSummary($contracts)` | Computes totals: count, original value, change orders, paid, balance |
| `getActiveFilters(Request)` | Resolves filter IDs to human-readable names for display |

### View: `resources/views/pdf/contract-payments.blade.php`

Landscape letter-size PDF with the following sections:

1. **Header** — Company logo (base64), company name/address, "CONTRACT PAYMENTS" title, generated date, active filters
2. **Summary Bar** — 5 boxes: Contracts count, Original Value, Change Orders (+/-), Total Paid, Balance Due (color-coded)
3. **Contracts Table** — 11 columns: Subcontractor, Project, Job Site, Contract #, Amount, CO (+/-), Adjusted, Paid, Balance, Status, Last Payment
4. **Change Order Details** — Nested under each contract with COs: date, title, description (truncated), amount (green/red)
5. **Payment History** (detailed version only) — Nested under each contract: date, method, reference, notes, amount (green)
6. **Footer** — Company name, report title, date

**Two Export Modes:**
- **Summary** (`include_payments=0`) — Contracts + change order details only. Filename: `contract-payments-YYYY-MM-DD.pdf`
- **Detailed** (`include_payments=1`) — Same + full payment history per contract. Filename: `contract-payments-detailed-YYYY-MM-DD.pdf`

**Styling:** Matches existing PDF templates (DejaVu Sans, 7-8pt, #3F5189 brand color, alternating row colors, inline CSS)

---

## CSV Export Format

The CSV export includes a **Type** column to distinguish contract rows from change order detail rows:

| Type | Purpose | Filled Columns |
|---|---|---|
| Contract | Main contract data row | All contract columns (Subcontractor through Last Payment Amount) |
| Change Order | Detail row for each CO | Type, Contract #, CO Date, CO Title, CO Description, CO Amount |

**Columns (18 total):** Type, Subcontractor, Project, Client, Job Site, Contract #, Original Amount, Change Orders (+/-), Adjusted Amount, Paid, Balance, Status, Last Payment Date, Last Payment Amount, CO Date, CO Title, CO Description, CO Amount

This format allows users to filter by "Type" column in Excel to see just contracts or just change orders.

---

## Files Created/Modified (Dashboard Enhancements)

### Created
- `app/Http/Controllers/ContractPaymentsPdfController.php` — PDF export controller
- `resources/views/pdf/contract-payments.blade.php` — PDF template (landscape)

### Modified
- `app/Livewire/Contract/ContractPayments.php` — Added `$expandedContracts`, `toggleChangeOrders()`, change orders eager loading/withSum, updated balance calc, enhanced CSV export
- `resources/views/livewire/contract/contract-payments.blade.php` — Added Change Orders column with expand/collapse, PDF dropdown button, updated balance to include COs
- `routes/web.php` — Added PDF download/stream routes
