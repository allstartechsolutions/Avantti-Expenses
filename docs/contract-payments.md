# Contract Payments Module

## Overview

Payment tracking for subcontractor contracts. Users can record payments made against contracts, with automatic status transitions based on payment activity. Simpler than InvoicePayments — no gateway integration, no payment status, no refunds.

---

## Database Schema

### `contract_payments` Table

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

**Indexes:** `contract_id`, `payment_date`

---

## Model

**Location:** `app/Models/ContractPayment.php`

### Accessors (cents <-> dollars)
- `amount` — converts between cents (database) and dollars (application)

### Relationships
- `contract()` — BelongsTo Contract
- `createdBy()` — BelongsTo User

### Display Helpers
- `getPaymentMethodLabel()` — Returns human-readable label (e.g., "Credit Card", "Bank Transfer", "PIX")

---

## Contract Model Additions

**Location:** `app/Models/Contract.php`

### New Relationship
- `payments()` — HasMany ContractPayment, ordered by `payment_date` desc

### New Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getAmountPaid()` | float (dollars) | Sum of all payment amounts |
| `getBalanceDue()` | float (dollars) | Contract amount minus amount paid |
| `updateStatusFromPayments()` | void | Auto-transitions contract status based on payment totals |

### Auto-Status Transitions (`updateStatusFromPayments`)

Only applies when current status is `completed`, `partially_paid`, or `paid`:

| Condition | New Status |
|-----------|------------|
| Amount paid >= contract amount | `paid` |
| Amount paid > 0 but < contract amount | `partially_paid` |
| No payments remaining | `completed` |

- Does **not** auto-update `active` contracts (must be manually completed first)
- Records status change in history with reason "Auto-updated from payment activity"

---

## ContractShow Component Updates

**Location:** `app/Livewire/Contract/ContractShow.php`

### New Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$showPaymentModal` | bool | false | Controls payment modal visibility |
| `$paymentAmount` | string | '' | Amount input (pre-filled with balance due) |
| `$paymentMethod` | string | 'check' | Selected payment method |
| `$paymentDate` | string | '' | Payment date (pre-filled with today) |
| `$paymentReference` | string | '' | Optional reference number |
| `$paymentNotes` | string | '' | Optional notes |

### New Methods

| Method | Description |
|--------|-------------|
| `openPaymentModal()` | Pre-fills amount with balance due and date with today |
| `closePaymentModal()` | Resets all payment fields |
| `recordPayment()` | Validates, creates ContractPayment, updates status, refreshes |
| `deletePayment($id)` | Deletes payment (scoped to contract), updates status, refreshes |
| `refreshContract()` | Extracted reusable `fresh()` call with all eager-loaded relationships |

### Validation Rules (recordPayment)

| Field | Rules |
|-------|-------|
| paymentAmount | required, numeric, min:0.01, max:balance_due |
| paymentDate | required, date |
| paymentMethod | required, in:cash,check,credit_card,debit_card,bank_transfer,pix,other |
| paymentReference | nullable, string, max:255 |
| paymentNotes | nullable, string |

---

## Blade View Updates

**Location:** `resources/views/livewire/contract/contract-show.blade.php`

### Financial Card Enhancement
- **Contract Amount** — always shown
- **Amount Paid** — green text, only shown when payments exist
- **Balance Due** — orange when > 0, green when fully paid

### Record Payment Button (Actions Card)
- Shown when contract status is `active`, `completed`, or `partially_paid`
- Uses `variant="success"` with plus icon

### Payment History Card (Sidebar)
- Only rendered when payments exist
- Divide-y list showing: amount, date, method label, reference, notes, created by
- Delete button per payment with `wire:confirm`

### Payment Modal
- Same `@if` + fixed overlay pattern as the existing status modal
- Fields: Amount ($, step 0.01), Payment Method (select), Date, Reference (optional), Notes (optional)
- Cancel and Record Payment buttons

---

## Cascade Deletes

- Deleting a **Contract** cascades to all its **ContractPayment** records (database-level)
- Deleting a **Project** cascades to Contracts, which cascades to ContractPayments
- Deleting a **JobSite** cascades to Contracts, which cascades to ContractPayments
- No file cleanup needed — ContractPayment has no file columns

---

## Files

### Created
- `database/migrations/2026_02_13_210000_create_contract_payments_table.php`
- `app/Models/ContractPayment.php`

### Modified
- `app/Models/Contract.php` — added `payments()`, `getAmountPaid()`, `getBalanceDue()`, `updateStatusFromPayments()`
- `app/Livewire/Contract/ContractShow.php` — added payment modal properties and methods, extracted `refreshContract()`
- `resources/views/livewire/contract/contract-show.blade.php` — enhanced Financial card, Record Payment button, Payment History card, Payment Modal
