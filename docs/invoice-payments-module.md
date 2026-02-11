# Invoice Payments & CardPointe Integration

## Overview

The Invoice Payments module handles recording payments against invoices (manual and credit card), partial payment tracking, and automatic status updates. It integrates with the **CardPointe Gateway** for credit card processing and includes a client-level **Payment Methods** management system for storing and reusing cards.

## Key Features

- Manual payment recording (cash, check, bank transfer, etc.)
- Credit card payments via CardPointe Gateway (iFrame tokenizer for PCI compliance)
- Saved payment methods per client (one CardPointe profile per client, multiple cards)
- Partial payment tracking with progress bar
- Void/refund support (auto-selects void or refund based on batch settlement status)
- Payment method management on Client Show page (add, edit, set default, remove)
- Automatic invoice status transitions based on payment state

---

## Database Schema

### 1. `invoice_payments` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint | FK to invoices (cascadeOnDelete) |
| payment_number | unsignedInteger | Sequential per invoice (1, 2, 3...) |
| amount | unsignedBigInteger | Payment amount in cents |
| payment_method | enum | cash, check, credit_card, debit_card, bank_transfer, pix, other |
| payment_date | date | Date of payment |
| status | enum | pending, completed, failed, refunded, voided (default: completed) |
| reference_number | string | Check number, transfer ref, etc. (nullable) |
| notes | text | Payment notes (nullable) |
| gateway | enum | cardpointe, manual (default: manual) |
| gateway_transaction_id | string | CardPointe retref (nullable) |
| gateway_auth_code | string | CardPointe authcode (nullable) |
| gateway_status | string | CardPointe respstat (nullable) |
| card_last_four | string(4) | Last 4 digits of card (nullable) |
| card_brand | string | Visa, Mastercard, etc. (nullable) |
| refund_amount | unsignedBigInteger | Refund amount in cents (nullable) |
| refunded_at | timestamp | When refund was processed (nullable) |
| refund_transaction_id | string | CardPointe refund retref (nullable) |
| created_by | bigint | FK to users |
| timestamps | | created_at, updated_at |

**Indexes:** invoice_id, status, payment_date

### 2. `client_payment_methods` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| client_id | bigint | FK to clients (cascadeOnDelete) |
| cardpointe_profile_id | string | CardPointe profile ID |
| acctid | string | CardPointe account ID within profile |
| card_last_four | string(4) | Last 4 digits |
| card_brand | string | Visa, Mastercard, etc. (nullable) |
| card_name | string | Name on card (nullable) |
| expiry | string(4) | MMYY format (nullable) |
| token | string | CardPointe tokenized card number |
| is_default | boolean | Default payment method flag |
| created_by | bigint | FK to users |
| timestamps | | created_at, updated_at |
| deleted_at | timestamp | Soft delete (nullable) |

**Indexes:** (client_id, is_default)

### 3. `clients` Table (added column)

| Column | Type | Description |
|--------|------|-------------|
| cardpointe_profile_id | string | Client's CardPointe profile ID (nullable) |

---

## Models

### InvoicePayment (`app/Models/InvoicePayment.php`)

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `createdBy()` - BelongsTo User (via `created_by`)

**Money Accessors (cents <-> dollars):**
- `amount` - Payment amount
- `refundAmount` - Refund amount

**Status Helpers:**
- `isCompleted()`, `isPending()`, `isFailed()`, `isRefunded()`, `isVoided()`

**Actions:**
- `markAsCompleted()` - Sets status to completed, triggers invoice status update
- `markAsVoided()` - Sets status to voided, triggers invoice status update

**Display Helpers:**
- `getPaymentMethodLabel()` - Human-readable payment method (e.g., "Credit Card")
- `getCardDisplayName()` - e.g., "Visa ending in 4242"
- `getStatusLabel()` - Human-readable status
- `getStatusColor()` - Tailwind CSS classes for status badge

### ClientPaymentMethod (`app/Models/ClientPaymentMethod.php`)

**Traits:** `SoftDeletes`

**Relationships:**
- `client()` - BelongsTo Client
- `createdBy()` - BelongsTo User (via `created_by`)

**Display Helpers:**
- `getDisplayName()` - e.g., "Visa ending in 4242 (exp 12/26)"
- `getExpiryFormatted()` - e.g., "12/26" from "1226"

### Invoice (updated relationships)

- `payments()` - HasMany InvoicePayment (ordered by payment_number)

### Client (updated)

- `paymentMethods()` - HasMany ClientPaymentMethod
- `cardpointe_profile_id` - Added to fillable

---

## CardPointe Gateway Integration

### Configuration (`config/services.php`)

```php
'cardpointe' => [
    'env' => env('CARDPOINTE_ENV', 'uat'),
    'merchant_id' => env('CARDPOINTE_MERCHANT_ID'),
    'api_user' => env('CARDPOINTE_API_USER'),
    'api_pass' => env('CARDPOINTE_API_PASS'),
    'site' => env('CARDPOINTE_SITE', 'fts-uat'),
],
```

### Environment Variables (`.env`)

```env
CARDPOINTE_ENV=uat           # uat or production
CARDPOINTE_MERCHANT_ID=xxx
CARDPOINTE_API_USER=xxx
CARDPOINTE_API_PASS=xxx
CARDPOINTE_SITE=fts-uat      # Site prefix for gateway URL
```

### CardPointeService (`app/Services/CardPointeService.php`)

| Method | Description |
|--------|-------------|
| `isConfigured()` | Returns true if merchant ID, API user, and API pass are set |
| `getGatewayUrl()` | Returns base REST API URL (UAT or production) |
| `getIframeUrl()` | Returns iFrame tokenizer URL with CSS styling |
| `authorize(array $params)` | Auth + capture (or $0 validation). Returns normalized response |
| `void(string $retref)` | Void a transaction (before batch settlement) |
| `refund(string $retref, int $amountCents)` | Refund a settled transaction |
| `getProfile(string $profileId, string $acctId)` | Retrieve stored profile/card |
| `addCardToProfile(string $profileId, array $data)` | Add a new card to existing profile |
| `updateProfile(string $profileId, string $acctId, string $account, array $data)` | Update card details (expiry, name, email) |
| `deleteProfile(string $profileId, string $acctId)` | Delete a stored profile/card |

### CardPointe Profile Management (Critical Patterns)

The `profile` field format in API calls determines the behavior:

| `profile` value | Behavior |
|-----------------|----------|
| `"y"` | Create a new profile (used with first card via $0 auth) |
| `"profileid"` | Add a new card to an existing profile |
| `"profileid/acctid"` | Reference/update a specific existing card |

**Important rules:**
- One CardPointe profile per client (`cardpointe_profile_id` stored on `clients` table)
- Multiple cards (acctids) live under one profile
- `account` field = the tokenized card number from the iFrame (reusable, same card always returns same token)
- `account` is REQUIRED for both add and update operations
- When updating, also send `name` (cardholder name) and `email` (client email)
- Do NOT use `profileid` and `acctid` as separate fields — always use the `profile` field with the combined format

### iFrame Tokenizer (PCI Compliance)

Card numbers are never handled by the application. The CardPointe iFrame tokenizer:
1. Renders a secure iframe with a card number input field
2. On input, posts a token back via `window.postMessage`
3. The token is captured by Alpine.js and sent to Livewire via `$wire.setCardToken(token)`
4. The token is used in API calls as the `account` parameter

---

## Payment Workflows

### Manual Payment Recording

1. User clicks "Record Payment" on Invoice Show
2. Payment modal opens with balance due pre-filled
3. User selects payment method (cash, check, etc.), enters amount, date, optional reference
4. Payment is created with `status: completed`, `gateway: manual`
5. Invoice status auto-updates based on payment totals

### Credit Card Payment (New Card)

1. User selects "Credit Card" payment type
2. Card number entered in iFrame → token returned
3. Name, expiry (MMYY), CVV, and billing zip entered
4. Optional "Save card for future use" checkbox
5. `authorize()` API call with card details
6. If save requested and no profile exists: `profile: 'y'` creates profile during auth
7. If save requested and profile exists: separate `addCardToProfile()` call after auth
8. Payment record created with gateway details

### Credit Card Payment (Saved Card)

1. User selects a saved card from dropdown
2. Amount entered (balance due pre-filled)
3. `authorize()` with `profile: "profileid/acctid"` (no card details needed)
4. Payment record created

### Void/Refund

1. User clicks "Void" on a completed payment
2. For CardPointe payments:
   - Try `void()` first (works before daily batch settlement)
   - If void fails (already settled), fall back to `refund()`
3. For manual payments: direct void (status change only)
4. Invoice status auto-updates

---

## Invoice Status Workflow (Updated with Payments)

```
                                                    ┌──────────┐
┌─────────┐    markAsSent()    ┌────────┐          │ PARTIAL  │◄──┐
│  DRAFT  │───────────────────►│  SENT  │──┐       └──────────┘   │
└─────────┘                    └────────┘  │            │          │
                                    │      │  payment   │  payment │ void/refund
                         markAsPending()   │            │          │
                                    │      │            ▼          │
                                    ▼      │       ┌─────────┐    │
                              ┌─────────┐  └──────►│  PAID   │────┘
                              │ PENDING │──────────►└─────────┘
                              └─────────┘  payment
```

### Auto-Status Logic (`updateStatusFromPayments()`)

| Condition | New Status |
|-----------|------------|
| Balance due = 0, amount paid > 0 | `paid` |
| Amount paid > 0, balance due > 0 | `partial` |
| No completed payments, was partial/paid | Reverts to `pending` |

### Status Enum Values

`draft`, `sent`, `pending`, `partial`, `paid`

Note: `partial` was added to support partial payment tracking.

---

## Client Payment Methods Management

### Features (on Client Show page)

- **View cards**: List all saved cards with brand, last four, expiry, default badge
- **Add card**: iFrame tokenizer + card details form. First card: $0 auth + void creates profile. Subsequent cards: direct `addCardToProfile()`.
- **Edit card**: Update cardholder name and expiry (synced to CardPointe via `updateProfile()`)
- **Set default**: Mark a card as the default payment method
- **Remove card**: Soft delete locally only (card remains on CardPointe profile)

### Add Card Flow (Client Show)

**First card (no existing profile):**
1. $0 auth with `capture: 'n'`, `profile: 'y'`, card details
2. Void the $0 auth (non-critical if fails)
3. Store `profileid` on client record
4. Create `ClientPaymentMethod` record

**Subsequent cards (profile exists):**
1. `addCardToProfile()` with `profile: existingProfileId`, `account`, `name`, `email`, `expiry`
2. Create `ClientPaymentMethod` record with new `acctid`

### Edit Card Flow

1. User updates name and/or expiry in modal
2. `updateProfile()` called with `profile: "profileid/acctid"`, `account` (token), `name`, `email`, `expiry`
3. Local record updated

### Remove Card Flow

1. Soft delete the `ClientPaymentMethod` record (`deleted_at` set)
2. If the removed card was default, next most recent card becomes default
3. No CardPointe API call — card remains on the gateway profile

---

## Livewire Components

### InvoiceShow (`app/Livewire/Invoice/InvoiceShow.php`)

Payment-related properties:
- `$showPaymentModal`, `$paymentAmount`, `$paymentMethod`, `$paymentDate`, `$paymentReference`, `$paymentNotes`
- `$paymentType` (manual or card)
- `$cardToken`, `$cardName`, `$cardExpiry`, `$cardCvv`, `$cardZip`, `$saveCard`
- `$selectedPaymentMethodId` — ID of saved card to charge
- `$clientPaymentMethods` — array of saved cards for dropdown
- `$cardPaymentError` — error message from gateway

Payment methods:
- `openPaymentModal()` — Pre-fills balance, loads saved cards
- `recordPayment()` — Manual payment recording
- `processCardPayment()` — CardPointe auth + payment creation + optional card save
- `voidPayment($paymentId)` — Void or refund with auto-fallback
- `setCardToken($token)` — Receive token from iFrame

### ClientShow (`app/Livewire/Client/ClientShow.php`)

Card management properties:
- `$paymentMethods`, `$cardPointeConfigured`
- Add card: `$showAddCardModal`, `$cardToken`, `$cardName`, `$cardExpiry`, `$cardCvv`, `$cardZip`, `$cardError`
- Edit card: `$showEditCardModal`, `$editingCardId`, `$editCardName`, `$editExpiry`, `$editCardDisplayName`, `$editError`

Card management methods:
- `loadPaymentMethods()` — Refresh list from DB (excludes soft-deleted)
- `openAddCardModal()` / `addCard()` — Add new card (first or subsequent)
- `openEditCardModal($id)` / `updateCard()` — Edit card name/expiry on CardPointe + local
- `setDefaultCard($id)` — Set as default, unset others
- `deleteCard($id)` — Soft delete

### InvoiceSendEmail (`app/Livewire/Invoice/InvoiceSendEmail.php`)

Email body includes an inline HTML "Invoice Summary" block with:
- Invoice Number, Date, Due Date, Total
- Amount Paid (shown only if > 0, in green)
- Balance Due (highlighted in brand color)

This block appears before "Best regards" in the default email body.

---

## Email Integration

### Invoice Email Body

The default email body (generated in `InvoiceSendEmail::mount()`) includes:
1. Greeting with client contact name
2. Reference to invoice number
3. **Invoice Summary** HTML table block:
   - Invoice Number, Date, Due Date, Total
   - Amount Paid (conditional, green text)
   - Balance Due (brand color, bold)
4. Contact prompt
5. Closing with company name

The email template (`resources/views/emails/invoice.blade.php`) renders `{!! $emailBody !!}` which includes the summary block.

### Email Tracking

- Each sent email gets a UUID `tracking_token`
- Hidden 1x1 transparent GIF in email HTML points to `GET /email/track/{token}`
- `EmailTrackingController` sets `opened_at` on first pixel load
- Supports both EstimateEmail and InvoiceEmail tracking

---

## Files

### New Files

**Migrations:**
- `database/migrations/2026_02_11_100000_create_invoice_payments_table.php`
- `database/migrations/2026_02_11_100001_add_partial_status_to_invoices_table.php`
- `database/migrations/2026_02_11_100002_add_partial_status_to_invoice_status_histories_table.php`
- `database/migrations/2026_02_11_200000_create_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200003_add_soft_deletes_to_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200004_add_card_name_to_client_payment_methods_table.php`
- `database/migrations/2026_02_11_200005_add_cardpointe_profile_id_to_clients_table.php`

**Models:**
- `app/Models/InvoicePayment.php`
- `app/Models/ClientPaymentMethod.php`

**Services:**
- `app/Services/CardPointeService.php`

**Exceptions:**
- `app/Exceptions/CardPointeException.php`

### Modified Files

- `app/Models/Invoice.php` — Added `payments()` relationship, payment helpers (`getAmountPaid`, `getBalanceDue`, `getPaymentProgress`, `updateStatusFromPayments`), `partial` status support, updated `isPastDue` to include partial
- `app/Models/Client.php` — Added `cardpointe_profile_id` to fillable, `paymentMethods()` relationship
- `app/Models/InvoiceStatusHistory.php` — Added `partial` to status enum display helpers
- `app/Livewire/Invoice/InvoiceShow.php` — Added payment modal, card payment processing, void/refund
- `app/Livewire/Invoice/InvoiceIndex.php` — Added "Partial" status tab with count
- `app/Livewire/Invoice/InvoiceSendEmail.php` — Invoice Summary block in email body with paid/balance amounts
- `app/Livewire/Client/ClientShow.php` — Added payment method management (add, edit, set default, remove)
- `app/Http/Controllers/InvoicePdfController.php` — Updated for payment info on PDF
- `resources/views/livewire/invoice/invoice-show.blade.php` — Payment modal, payment history, card payment form
- `resources/views/livewire/invoice/invoice-index.blade.php` — Partial status tab
- `resources/views/livewire/client/client-show.blade.php` — Payment Methods card, add/edit card modals
- `resources/views/emails/invoice.blade.php` — Simplified to render emailBody (summary now inline)
- `resources/views/pdf/invoice.blade.php` — Payment summary on PDF
- `routes/web.php` — Added email tracking route
- `config/services.php` — Added CardPointe configuration

---

## Technical Notes

### Cents vs Dollars
All monetary values (payment amount, refund amount) are stored in cents and converted via Eloquent accessors.

### Soft Deletes
`ClientPaymentMethod` uses `SoftDeletes`. When querying `paymentMethods()`, soft-deleted records are automatically excluded. This means removed cards won't appear in invoice payment dropdowns or client show.

### One Profile Per Client
Each client has at most one CardPointe profile (stored as `cardpointe_profile_id` on the `clients` table). Multiple cards are stored as separate `acctid` entries under that single profile. This is the correct CardPointe pattern — do NOT create a new profile per card.

### iFrame CSS
The iFrame tokenizer URL includes inline CSS that styles the card number input to match the application's form fields (rounded corners, brand color focus ring).

### Timeout
All CardPointe API calls use a 30-second timeout to accommodate UAT server latency.

### Cascade Deletes
- Deleting an Invoice cascades to all InvoicePayments
- Deleting a Client cascades to all ClientPaymentMethods
