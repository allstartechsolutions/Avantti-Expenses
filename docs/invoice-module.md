# Invoice Module Documentation

## Overview

The Invoice module provides a complete workflow for creating, managing, and tracking invoices sent to clients. Invoices can be created standalone or converted from accepted estimates. The module shares the same architecture as the Estimate module — catalog and custom line items, per-item and overall discounts, tax calculations, predefined payment terms, message templates, PDF generation, email sending with tracking pixel open detection, and status history tracking.

## Key Features

- Client, project, and job site association (cascading search dropdowns)
- Catalog items with inherited tax settings + custom items
- Per-item discounts (percentage or fixed)
- Overall invoice discount (percentage or fixed)
- Per-item tax calculations with configurable tax rates
- Predefined payment terms (Net 15/30/60/90) with auto-calculated due dates
- Message templates from DocumentMessage system (type=invoice), editable per invoice
- Status workflow: Draft → Sent → Pending → Partial → Paid
- Past due detection (pending/partial + due date in past)
- Payment recording (manual and credit card via CardPointe)
- Partial payment tracking with progress bar
- Client saved payment methods (CardPointe profiles)
- Sequential invoice numbering (INV-0001, INV-0002, etc.)
- Conversion from accepted estimates (copies all items, discounts, taxes)
- Status change audit trail with user attribution
- Public payment link for clients to pay online (no login required)

---

## Database Schema

### 1. `invoices` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| client_id | bigint | FK to clients (cascadeOnDelete) |
| project_id | bigint | FK to projects (nullable, nullOnDelete) |
| job_site_id | bigint | FK to job_sites (nullable, nullOnDelete) |
| estimate_id | bigint | FK to estimates (nullable, nullOnDelete) — source estimate |
| invoice_number | string | Unique (INV-0001 format) |
| invoice_date | date | Date of invoice |
| terms | enum | net_15, net_30, net_60, net_90 |
| due_date | date | Auto-calculated from invoice_date + terms |
| status | enum | draft, sent, pending, partial, paid (default: draft) |
| message_title | string | Snapshot from DocumentMessage (nullable) |
| message_body | text | Snapshot, editable by user (nullable) |
| discount_type | enum | percentage, fixed (nullable) |
| discount_value | decimal(10,2) | The % or $ value entered (nullable) |
| discount_amount | unsignedBigInteger | Calculated discount in cents |
| subtotal | unsignedBigInteger | Sum of line net amounts in cents |
| tax_total | unsignedBigInteger | Sum of line tax amounts in cents |
| total_amount | unsignedBigInteger | subtotal - discount + tax in cents |
| notes | text | Internal notes (nullable) |
| payment_token | string(36) | UUID for public payment link (unique, nullable, auto-generated) |
| sent_at | timestamp | When marked as sent (nullable) |
| paid_at | timestamp | When marked as paid (nullable) |
| created_by | bigint | FK to users |
| timestamps | | created_at, updated_at |

### 2. `invoice_items` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint | FK to invoices (cascadeOnDelete) |
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

### 3. `invoice_emails` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint | FK to invoices (cascadeOnDelete) |
| sent_to | string | Recipient email address |
| cc | string | Comma-separated CC addresses (nullable) |
| subject | string | Email subject used |
| body | longText | Email body HTML sent |
| sent_by | bigint | FK to users (cascadeOnDelete) — who clicked Send |
| sent_at | timestamp | When the email was sent |
| opened_at | timestamp | When the tracking pixel was loaded (nullable) |
| tracking_token | uuid | Unique token for tracking pixel URL |
| timestamps | | created_at, updated_at |

### 4. `invoice_status_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint | FK to invoices (cascadeOnDelete) |
| old_status | enum | draft, sent, pending, partial, paid (nullable — null for creation) |
| new_status | enum | draft, sent, pending, partial, paid |
| changed_by | bigint | FK to users (nullable — null for guest payments) |
| timestamps | | created_at, updated_at |

**Indexes:** invoice_id

---

## Models

### Invoice (`app/Models/Invoice.php`)

**Relationships:**
- `client()` - BelongsTo Client
- `project()` - BelongsTo Project (nullable)
- `jobSite()` - BelongsTo JobSite (nullable)
- `estimate()` - BelongsTo Estimate (source estimate, nullable)
- `items()` - HasMany InvoiceItem (ordered by sort_order)
- `createdBy()` - BelongsTo User
- `emailsSent()` - HasMany InvoiceEmail (ordered by sent_at desc)
- `statusHistories()` - HasMany InvoiceStatusHistory (ordered by created_at desc)
- `payments()` - HasMany InvoicePayment (ordered by payment_number)

**Money Accessors (cents ↔ dollars):**
- `subtotal`, `taxTotal`, `totalAmount`, `discountAmount`

**Boot Events:**
- `creating` — Auto-generates UUID `payment_token` if not set

**Status Tracking:**
- `recordStatusChange(?User $user, ?string $old, string $new)` - Creates an InvoiceStatusHistory record (user nullable for guest payments)

**Status Helpers:**
- `isDraft()` - Status is 'draft'
- `isSent()` - Status is 'sent'
- `isPending()` - Status is 'pending'
- `isPartial()` - Status is 'partial'
- `isPaid()` - Status is 'paid'
- `isPastDue()` - Pending or partial + due date is in the past
- `canBeEdited()` - Returns true if draft or sent
- `canBeSent()` - Returns true if draft with items

**Payment Helpers:**
- `getAmountPaid()` - Sum of completed payments in dollars
- `getBalanceDue()` - Total minus amount paid
- `getPaymentProgress()` - Percentage paid (0-100)
- `updateStatusFromPayments()` - Auto-transitions status based on payments (paid/partial/pending)
- `getPaymentUrl()` - Returns the public payment URL for this invoice

**Static Methods:**
- `generateInvoiceNumber()` - Generates next sequential INV-XXXX number
- `calculateDueDate($date, $terms)` - Adds term days to invoice date

**Computed Attributes:**
- `status_color` - CSS classes for status badge (includes past due red)
- `status_label` - Human-readable status text (includes "Past Due")
- `terms_label` - Human-readable terms (e.g., "Net 30")

### InvoiceStatusHistory (`app/Models/InvoiceStatusHistory.php`)

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `changedBy()` - BelongsTo User (via `changed_by` column)

**Display Helpers:**
- `getChangeDescription()` - e.g., "Sent → Paid" or "Created as Draft"
- `getStatusColor()` - Returns color name (gray, blue, yellow, green)

### InvoiceEmail (`app/Models/InvoiceEmail.php`)

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `sentBy()` - BelongsTo User (via `sent_by` column)

### InvoiceItem (`app/Models/InvoiceItem.php`)

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `catalogItem()` - BelongsTo CatalogItem (nullable)

**Money Accessors:**
- `unitPrice`, `discountAmount`, `taxAmount`, `totalAmount`

---

## Status Workflow

```
                                                         ┌──────────┐
┌─────────┐    markAsSent()    ┌────────┐               │ PARTIAL  │◄──┐
│  DRAFT  │───────────────────►│  SENT  │──┐            └──────────┘   │
└─────────┘                    └────────┘  │                 │          │
                                    │      │   payment       │  payment │ void/refund
                         markAsPending()   │                 │          │
                                    │      │                 ▼          │
                                    ▼      │            ┌─────────┐    │
                              ┌─────────┐  └───────────►│  PAID   │────┘
                              │ PENDING │──────────────►└─────────┘
                              └─────────┘   payment
```

### Status Transition Rules

All transitions are logged to `invoice_status_histories` with the user who made the change.

| From | To | Method | Conditions |
|------|-----|--------|------------|
| draft | sent | `markAsSent()` | Must be draft |
| draft | sent | `sendEmail()` | Auto-transition when emailing a draft |
| sent | pending | `markAsPending()` | Must be sent |
| sent/pending | partial | `updateStatusFromPayments()` | Partial payment recorded |
| sent/pending/partial | paid | `updateStatusFromPayments()` | Full balance paid |
| partial/paid | pending | `updateStatusFromPayments()` | Payments voided/refunded |

See **[Invoice Payments Module](./invoice-payments-module.md)** for full payment workflow details.

### Edit/Delete Permissions

| Status | Can Edit | Can Delete |
|--------|----------|------------|
| Draft | Yes | Yes |
| Sent | Yes | Yes |
| Pending | No | No |
| Paid | No | No |

### Delete Behavior
- When an invoice is deleted and it was converted from an estimate, the estimate's `converted_to_invoice_id` is cleared so the estimate can be converted to a new invoice again.

---

## Livewire Components

### 1. InvoiceIndex (`app/Livewire/Invoice/InvoiceIndex.php`)

**View:** `resources/views/livewire/invoice/invoice-index.blade.php`

**Features:**
- List all invoices with search (by invoice_number or client company_name)
- Status filter tabs with counts (All, Draft, Sent, Pending, Paid)
- Table columns: Invoice #, Client, Project, Date, Due Date, Status badge, Total, Actions
- Pagination
- View/Edit buttons (edit only for draft/sent)
- Delete with `wire:confirm` (only for draft/sent)

### 2. InvoiceCreate (`app/Livewire/Invoice/InvoiceCreate.php`)

**View:** `resources/views/livewire/invoice/invoice-create.blade.php`

**Features:**
- Same form structure as EstimateCreate
- Client/Project/Job Site cascading search dropdowns
- Invoice Number (auto-generated, readonly)
- Invoice Date (defaults to today), Terms, Due Date (auto-calculated)
- Items section with modal-based add/edit (catalog + custom)
- Totals with overall discount
- Message templates from DocumentMessage (type=invoice)
- Internal notes

### 3. InvoiceShow (`app/Livewire/Invoice/InvoiceShow.php`)

**View:** `resources/views/livewire/invoice/invoice-show.blade.php`

**Features:**
- Two-column layout: main content + sidebar
- Invoice details card (client, project, jobsite, dates, terms, created by, source estimate link, timestamps)
- Items table with all columns and totals breakdown
- Payment summary (amount paid, balance due, progress bar)
- Payment history table with void/refund actions
- Message display (rendered HTML)
- Email History card (shown when emails have been sent)
- Credit card payment modal with iFrame tokenizer and saved cards dropdown
- Manual payment modal (cash, check, bank transfer, etc.)

**Sidebar Actions (status-based):**
- **Draft:** Email Invoice, Mark as Sent, View/Download PDF, Edit, Delete
- **Sent:** Email Invoice, Record Payment, Copy Payment Link*, Mark as Pending, View/Download PDF, Edit, Delete
- **Pending:** Past due warning (if applicable), Record Payment, Copy Payment Link*, Email Invoice, View/Download PDF
- **Partial:** Past due warning (if applicable), Record Payment, Copy Payment Link*, Email Invoice, View/Download PDF
- **Paid:** Paid confirmation with timestamp, View/Download PDF

\* Copy Payment Link only shown when CardPointe is configured and invoice has balance due. Uses `navigator.clipboard` with "Copied!" feedback.

**Sidebar Cards:**
- Summary Card: Item count, Subtotal, Discount, Tax, Total
- Source Estimate Card: Link to source estimate (if converted)
- Status History Card: Timeline UI with colored dots (gray=draft, blue=sent, yellow=pending, green=paid), user names, and timestamps

### 4. InvoiceEdit (`app/Livewire/Invoice/InvoiceEdit.php`)

**View:** `resources/views/livewire/invoice/invoice-edit.blade.php`

**Features:**
- Same form as Create but pre-populated with existing invoice data
- Only accessible when status is draft or sent
- On save: updates invoice, deletes old items, recreates from current array

### 5. PublicInvoicePay (`app/Livewire/Invoice/PublicInvoicePay.php`)

**View:** `resources/views/livewire/invoice/public-invoice-pay.blade.php`
**Layout:** `components.layouts.guest` (minimal, no sidebar/nav, no auth required)

Public-facing page that allows clients to pay invoices via credit card without logging in.

**Features:**
- Mounts by `payment_token` (UUID), not invoice ID — returns 404 for draft or missing invoices
- Invoice summary: number, client, dates, total, amount paid, balance due
- Read-only line items table with totals breakdown
- CardPointe iFrame for secure card tokenization (PCI compliant)
- Card fields: name, expiry (separate Month/Year dropdowns), CVV, billing zip
- Amount field pre-filled with balance due (allows partial payments)
- Already-paid screen (green checkmark) when invoice is fully paid
- Success confirmation screen after payment with remaining balance if partial
- Fallback message when CardPointe is not configured
- No card saving — card saving stays admin-only
- Sets `created_by` to null for guest payments

**Properties:**
- `$paymentAmount`, `$cardToken`, `$cardName`, `$cardExpiryMonth`, `$cardExpiryYear`, `$cardCvv`, `$cardZip`
- `$cardPaymentError`, `$paymentSuccess`, `$paidAmountDisplay`

**Methods:**
- `setCardToken($token)` — Receives token from iFrame via Alpine.js
- `processPayment()` — Validates, calls CardPointe authorize, creates InvoicePayment with `created_by: null`, updates invoice status

### 6. InvoiceSendEmail (`app/Livewire/Invoice/InvoiceSendEmail.php`)

**View:** `resources/views/livewire/invoice/invoice-send-email.blade.php`

Inline component rendered inside `InvoiceShow` via `<livewire:invoice.invoice-send-email />`.

**Features:**
- Pre-fills recipient from client email, subject from company name + invoice number
- Generates default body with client name, invoice number, and inline Invoice Summary table (number, date, due date, total, amount paid if any, balance due)
- CC field (comma-separated)
- TinyMCE editor for body
- Sends email with PDF attachment
- Generates UUID tracking token per send
- Creates `InvoiceEmail` log record after successful send
- Updates invoice status to "sent" if currently draft (logged to status history)
- Redirects back to invoice show page

---

## Routes

```php
// Public (no auth)
Route::get('pay/{token}', PublicInvoicePay::class)->name('invoice.pay')->middleware('throttle:20,1');

// Authenticated
Route::get('invoices', InvoiceIndex::class)->name('invoices.index');
Route::get('invoices/create', InvoiceCreate::class)->name('invoices.create');
Route::get('invoices/{invoice}', InvoiceShow::class)->name('invoices.show');
Route::get('invoices/{invoice}/edit', InvoiceEdit::class)->name('invoices.edit');

// PDF
Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
Route::get('invoices/{invoice}/pdf/view', [InvoicePdfController::class, 'stream'])->name('invoices.pdf.view');
```

---

## Files

### New Files

**Migrations:**
- `database/migrations/2026_02_10_200000_create_invoices_table.php`
- `database/migrations/2026_02_10_200001_create_invoice_items_table.php`
- `database/migrations/2026_02_10_200002_create_invoice_emails_table.php`
- `database/migrations/2026_02_10_210001_create_invoice_status_histories_table.php`
- `database/migrations/2026_02_11_100000_create_invoice_payments_table.php`
- `database/migrations/2026_02_11_100001_add_partial_status_to_invoices_table.php`
- `database/migrations/2026_02_11_100002_add_partial_status_to_invoice_status_histories_table.php`
- `database/migrations/2026_02_11_200000_create_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200003_add_soft_deletes_to_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200004_add_card_name_to_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200005_add_cardpointe_profile_id_to_clients_table.php`

**Models:**
- `app/Models/Invoice.php`
- `app/Models/InvoiceItem.php`
- `app/Models/InvoiceEmail.php`
- `app/Models/InvoiceStatusHistory.php`
- `app/Models/InvoicePayment.php`
- `app/Models/ClientPaymentMethod.php`

**Services:**
- `app/Services/CardPointeService.php`

**Exceptions:**
- `app/Exceptions/CardPointeException.php`

**Controllers:**
- `app/Http/Controllers/InvoicePdfController.php`

**Mailables:**
- `app/Mail/InvoiceMail.php`

**Livewire Components:**
- `app/Livewire/Invoice/InvoiceIndex.php`
- `app/Livewire/Invoice/InvoiceCreate.php`
- `app/Livewire/Invoice/InvoiceShow.php`
- `app/Livewire/Invoice/InvoiceEdit.php`
- `app/Livewire/Invoice/InvoiceSendEmail.php`
- `app/Livewire/Invoice/PublicInvoicePay.php`

**Views:**
- `resources/views/livewire/invoice/invoice-index.blade.php`
- `resources/views/livewire/invoice/invoice-create.blade.php`
- `resources/views/livewire/invoice/invoice-show.blade.php`
- `resources/views/livewire/invoice/invoice-edit.blade.php`
- `resources/views/livewire/invoice/invoice-send-email.blade.php`
- `resources/views/livewire/invoice/public-invoice-pay.blade.php`
- `resources/views/emails/invoice.blade.php`
- `resources/views/pdf/invoice.blade.php`

**Layouts:**
- `resources/views/components/layouts/guest.blade.php` — Minimal public-facing layout (no sidebar/nav/auth)

---

## Technical Notes

### Cents vs Dollars
All monetary values are stored in cents (unsignedBigInteger) in the database and converted to dollars in the application layer using Eloquent accessors.

### Cascade Deletes
- Deleting a Client cascades to all linked invoices
- Deleting an Invoice cascades to all invoice items, invoice emails, and status histories
- Deleting a User cascades to all invoice emails they sent (via `sent_by`)
- Deleting a Project or JobSite sets the FK to null (nullOnDelete)
- Deleting an Estimate sets the FK to null on invoices (nullOnDelete)
- Deleting a CatalogItem sets the FK to null on invoice items (nullOnDelete)

### Estimate Conversion
- Accepted estimates can be converted to draft invoices via `convertToInvoice()` in EstimateShow
- All items, discounts, taxes, and notes are copied to the new invoice
- The estimate's `converted_to_invoice_id` is set to link the two
- If the invoice is deleted, `converted_to_invoice_id` on the estimate is cleared, making the estimate available for conversion again

### Past Due Detection
Invoices with status `pending` or `partial` and a `due_date` in the past are flagged as "Past Due" with a red badge and warning in the sidebar.

### Payments & CardPointe Integration
See **[Invoice Payments Module](./invoice-payments-module.md)** for full documentation on:
- Manual and credit card payment recording
- CardPointe Gateway integration (authorize, void, refund)
- Client saved payment methods (CardPointe profiles)
- Partial payment tracking and automatic status transitions
- Public payment link for client self-service payments
