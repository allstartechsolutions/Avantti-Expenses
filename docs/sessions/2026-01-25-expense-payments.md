# Session: Expense Payments & Installments

**Date:** 2026-01-25
**Goal:** Implement payment tracking for expenses with support for installments

---

## Overview

This feature adds the ability to:
1. Track payment status for all expenses (paid, unpaid, partial, overdue, cancelled)
2. Record payment method and auto-payment flag for all expenses
3. Split expenses into multiple installment payments
4. View upcoming and overdue payments in a dedicated dashboard
5. Generate reports for payments due by period

---

## Design Decisions

### Two Payment Scenarios

| Scenario | Description | Storage |
|----------|-------------|---------|
| **One-Time** | Single payment (default) | Payment info stored on `expenses` table |
| **Installments** | Multiple payments over time | Payment records in `expense_payments` table |

### Expense Statuses

| Status | Description | Auto/Manual |
|--------|-------------|-------------|
| `unpaid` | No payment made yet | Auto (default for new) |
| `partial` | Some installments paid | Auto (calculated) |
| `paid` | Fully paid | Auto (when all paid) |
| `overdue` | Has overdue payment(s) | Manual |
| `cancelled` | Expense voided | Manual |

### Payment Methods

- `cash`
- `check`
- `credit_card`
- `debit_card`
- `bank_transfer`
- `pix` (Brazil)
- `other`

### Status Flow

```
ONE-TIME EXPENSE:
Create → Paid (default) or Unpaid → Mark as Paid → Paid
                                 → Cancel → Cancelled

INSTALLMENT EXPENSE:
Create → Unpaid → Pay first → Partial → Pay all → Paid
                           → Mark overdue → Overdue → Pay remaining → Paid
```

### Editing Rules

| State | Can Edit Amount? | Can Edit Installments? |
|-------|------------------|------------------------|
| Unpaid (no payments) | Yes | Yes |
| Partial (some paid) | No | No |
| Paid | No | No |
| Overdue | No | No |
| Cancelled | No | No |

---

## Database Schema

### Migration 1: Add payment columns to `expenses` table

**File:** `database/migrations/2026_01_25_140000_add_payment_fields_to_expenses_table.php`

**New Columns:**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `status` | enum | 'unpaid' | unpaid, partial, paid, overdue, cancelled |
| `payment_method` | enum | null | cash, check, credit_card, debit_card, bank_transfer, pix, other |
| `is_auto_payment` | boolean | false | Is on automatic recurring payment? |
| `total_installments` | unsignedInteger | 1 | Number of payments |
| `payment_frequency` | enum | null | weekly, biweekly, monthly |
| `payment_due_date` | date | null | Due date (one-time) or first installment date |
| `paid_date` | date | null | When paid (one-time only) |

**Backfill Logic:**
- All existing expenses: `status = 'paid'`, `total_installments = 1`
- Sets `paid_date = expense_date` for existing records

### Migration 2: Create `expense_payments` table

**File:** `database/migrations/2026_01_25_140001_create_expense_payments_table.php`

**Columns:**

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `expense_id` | foreignId | References expenses |
| `payment_number` | unsignedInteger | 1, 2, 3... |
| `amount` | bigInteger | Amount in cents |
| `due_date` | date | When payment is due |
| `paid_date` | date (nullable) | When actually paid |
| `status` | enum | pending, paid, overdue |
| `payment_method` | enum (nullable) | Override expense default |
| `notes` | text (nullable) | Payment notes |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `expense_id` (foreign key)
- `due_date` (for date range queries)
- `status` (for filtering)

---

## Models

### Expense Model Updates

**File:** `app/Models/Expense.php`

**New Fillable:**
- `status`
- `payment_method`
- `is_auto_payment`
- `total_installments`
- `payment_frequency`
- `payment_due_date`
- `paid_date`

**New Casts:**
- `is_auto_payment` => 'boolean'
- `payment_due_date` => 'date'
- `paid_date` => 'date'

**New Relationships:**
- `payments()` - HasMany ExpensePayment

**New Methods:**
- `isInstallment(): bool` - Returns true if total_installments > 1
- `isOneTime(): bool` - Returns true if total_installments == 1
- `isEditable(): bool` - Returns true if can be edited (unpaid, no payments made)
- `getPaidAmount(): float` - Returns total amount paid (for installments)
- `getPendingAmount(): float` - Returns total amount pending
- `getPaidInstallmentsCount(): int` - Returns number of paid installments
- `updateStatusFromPayments(): void` - Recalculates status based on payments
- `generatePaymentSchedule(): void` - Creates payment records for installments

### ExpensePayment Model (New)

**File:** `app/Models/ExpensePayment.php`

**Fillable:**
- `expense_id`
- `payment_number`
- `amount`
- `due_date`
- `paid_date`
- `status`
- `payment_method`
- `notes`

**Casts:**
- `due_date` => 'date'
- `paid_date` => 'date'

**Relationships:**
- `expense()` - BelongsTo Expense

**Accessors:**
- `amount` - Get/Set as dollars (stored as cents)

**Methods:**
- `markAsPaid(?string $paymentMethod = null, ?Carbon $paidDate = null): void`
- `markAsOverdue(): void`
- `isPending(): bool`
- `isPaid(): bool`
- `isOverdue(): bool`

---

## UI Changes

### Phase 1: Expense Create/Edit Form

**Files:**
- `app/Livewire/Project/ProjectShow.php`
- `resources/views/livewire/project/project-show.blade.php`
- `app/Livewire/JobSite/JobSiteShow.php`
- `resources/views/livewire/job-site/job-site-show.blade.php`

**New Form Fields:**
1. Payment Method dropdown
2. Auto Payment checkbox
3. Status dropdown (for one-time: Paid/Unpaid)
4. Payment Due Date (shows when Unpaid)
5. Paid Date (shows when Paid)
6. "Split into installments" checkbox
7. Installment options (when checked):
   - Number of installments
   - First payment date
   - Frequency (weekly, biweekly, monthly)
   - Amount type (equal/custom)
8. Payment schedule preview table

### Phase 2: Expense List Updates

**Changes:**
- Add Status column with colored badge
- Add Payments column (1x or 3/10)
- Add status filter dropdown

### Phase 3: Expense Detail View

**Changes:**
- Show payment information section
- For installments: show payment schedule table with actions
- Mark as Paid button for pending payments
- Mark as Overdue button
- Progress bar for installments

### Phase 4: Payments Dashboard (New Page)

**File:** `app/Livewire/Payment/PaymentDashboard.php`
**View:** `resources/views/livewire/payment/payment-dashboard.blade.php`
**Route:** `/payments`

**Features:**
- Summary cards (pending, overdue, this month, paid)
- Upcoming payments list
- Overdue payments list
- Filters (project, status, date range)
- Quick Pay action

---

## Implementation Progress

### Step 1: Migrations
- [x] Create migration for expenses table changes
- [x] Create migration for expense_payments table
- [x] Run migrations

### Step 2: Models
- [x] Update Expense model
- [x] Create ExpensePayment model

### Step 3: Expense Form (ProjectShow)
- [x] Add new properties to component
- [x] Add validation rules
- [x] Update save method
- [x] Update view with new fields
- [x] Add installment preview logic

### Step 4: Expense Form (JobSiteShow)
- [x] Add new properties to component (completed earlier)
- [x] Add validation rules (completed earlier)
- [x] Update save method (completed earlier)
- [x] Update view with new fields (filters, summary, table columns, view mode, create/edit form)
- [x] Add installment preview logic (completed earlier)

### Step 5: Expense List Updates
- [x] Add status/payments columns
- [x] Add status filter

### Step 6: Expense Detail View
- [x] Show payment schedule
- [x] Add mark as paid functionality

### Step 7: Payments Dashboard
- [x] Create component (`app/Livewire/Payment/PaymentDashboard.php`)
- [x] Create view (`resources/views/livewire/payment/payment-dashboard.blade.php`)
- [x] Add route (`/payments` -> `payments.index`)
- [x] Add to navigation (sidebar)

### Step 8: Testing
- [ ] Test one-time paid expense
- [ ] Test one-time unpaid expense with future date
- [ ] Test installment expense (equal amounts)
- [ ] Test installment expense (custom amounts)
- [ ] Test marking payments as paid
- [ ] Test status transitions
- [ ] Test editing restrictions
- [ ] Test payments dashboard

---

## Session Log

### 2026-01-25 - Initial Implementation

**Completed:**

1. **Created Migrations:**
   - `2026_01_25_140000_add_payment_fields_to_expenses_table.php`
     - Added: status, payment_method, is_auto_payment, total_installments, payment_frequency, payment_due_date, paid_date
     - Backfilled existing expenses as 'paid' with paid_date = expense_date
   - `2026_01_25_140001_create_expense_payments_table.php`
     - Created expense_payments table for installment tracking
     - Columns: payment_number, amount (cents), due_date, paid_date, status, payment_method, notes
     - Indexes on due_date, status, and expense_id+payment_number

2. **Created ExpensePayment Model:**
   - File: `app/Models/ExpensePayment.php`
   - Includes: amount accessor (dollars/cents), relationships, helper methods
   - Methods: markAsPaid(), markAsOverdue(), markAsPending(), status checkers

3. **Updated Expense Model:**
   - Added new fillable fields and casts
   - Added `payments()` HasMany relationship
   - Added helper methods:
     - `isInstallment()`, `isOneTime()`, `isEditable()`
     - `getPaidAmount()`, `getPendingAmount()`, `getPaymentProgress()`
     - `getPaymentLabel()`, `updateStatusFromPayments()`
     - `generatePaymentSchedule()`, `markAsPaid()`, `markAsCancelled()`

4. **Updated ProjectShow Component:**
   - Added 11 new payment-related properties
   - Added status filter property
   - Updated openExpenseCreateModal() to reset payment fields
   - Updated openExpenseEditModal() to load payment data and check editability
   - Updated openExpenseViewModal() to load payment data
   - Updated saveExpense() to handle payment fields and generate installments
   - Added payment schedule methods:
     - `updatedExpenseHasInstallments()`, `updatedExpenseTotalInstallments()`
     - `updatedExpensePaymentFrequency()`, `updatedExpensePaymentDueDate()`
     - `generatePaymentSchedulePreview()`, `initializeCustomAmounts()`
   - Added payment action methods:
     - `markPaymentAsPaid()`, `markPaymentAsOverdue()`, `markExpenseAsPaid()`
   - Updated render() to include payment totals and viewingExpense

5. **Updated ProjectShow View:**
   - Added status filter dropdown to filters section
   - Updated summary section to show 3 cards: Total, Paid, Pending
   - Updated expense table:
     - Replaced Quantity/Unit Price columns with Payments and Status columns
     - Added payment progress bar for installments
     - Added status badges with colors
     - Added conditional edit button (disabled when not editable)
     - Added quick "Mark as Paid" button for unpaid one-time expenses
   - Updated expense modal view mode:
     - Added Payment Information section
     - Added payment schedule table for installments
     - Added Mark Paid/Overdue buttons for each payment
     - Added progress bar and paid/pending totals
   - Updated expense modal create/edit mode:
     - Added Payment Method dropdown
     - Added Auto Payment checkbox
     - Added "Split into installments" toggle
     - Added one-time payment options (Status, Paid Date/Due Date)
     - Added installment options (count, frequency, first payment date)
     - Added equal/custom amounts toggle
     - Added payment schedule preview table
     - Added validation for custom amounts matching total

**Files Modified:**
- `database/migrations/2026_01_25_140000_add_payment_fields_to_expenses_table.php` (new)
- `database/migrations/2026_01_25_140001_create_expense_payments_table.php` (new)
- `app/Models/ExpensePayment.php` (new)
- `app/Models/Expense.php` (updated)
- `app/Livewire/Project/ProjectShow.php` (updated)
- `resources/views/livewire/project/project-show.blade.php` (updated)

### 2026-01-25 - Continued (Session 2)

**Completed:**

1. **Completed JobSiteShow View Updates:**
   - Added payment fields section to expense create/edit form
   - Includes: Payment method dropdown, Auto payment checkbox
   - Includes: Split into installments toggle
   - Includes: One-time payment options (Status, Paid Date/Due Date)
   - Includes: Installment options (number of installments, frequency, first payment date)
   - Includes: Equal/custom amounts toggle
   - Includes: Payment schedule preview table with custom amount inputs
   - Includes: Validation for custom amounts matching total

**Files Modified:**
- `resources/views/livewire/job-site/job-site-show.blade.php` (updated - payment section in create/edit form)

2. **Created Payments Dashboard:**
   - Component: `app/Livewire/Payment/PaymentDashboard.php`
   - View: `resources/views/livewire/payment/payment-dashboard.blade.php`
   - Route: `GET /payments` -> `payments.index`
   - Navigation: Added to sidebar

   **Features:**
   - Summary cards showing: Total Pending, Overdue, Due This Month, Paid This Month
   - View mode tabs: Upcoming, Overdue, All Pending
   - Filters: Project dropdown, Date range (from/to)
   - Payments table showing both installment payments and one-time unpaid expenses
   - Quick Pay modal with payment method and paid date selection
   - Mark as Overdue action
   - Combines data from `expense_payments` (installments) and `expenses` (one-time unpaid)
   - Color-coded overdue payments with time elapsed display

**Files Created:**
- `app/Livewire/Payment/PaymentDashboard.php`
- `resources/views/livewire/payment/payment-dashboard.blade.php`

**Files Modified:**
- `routes/web.php` (added payments route)
- `resources/views/components/layouts/inc/sidebar.blade.php` (added Payments navigation link)

3. **Bug Fixes for Payments Dashboard:**
   - Fixed column name references: `project_name` instead of `name` for projects, `job_site_name` for job sites
   - Fixed layout path: `components.layouts.app` instead of `layouts.app`
   - Fixed modal implementation: Changed from custom modal to `x-ui.modal` component with dispatch system for open/close

**Pending:**
- Testing

