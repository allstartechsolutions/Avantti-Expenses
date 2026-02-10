# System Settings — Document Messages Module

## Overview

The **Messages** tab in System Settings allows users to create reusable messages for invoices and estimates. Each message has a title and rich text body (via TinyMCE editor). Messages can be marked as default (one per document type) and toggled active/inactive. When creating invoices or estimates later, users will pick from these pre-configured messages.

## Key Features

- CRUD for document messages with rich text editor (TinyMCE)
- Type-based filtering (All / Invoice / Estimate)
- One default message per type (invoice and estimate tracked independently)
- Active/inactive status toggle
- Full change history logging per field
- Tracks who created each message

---

## Database Schema

### 1. `document_messages` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| title | string | Message title |
| body | text | Rich text content (HTML from TinyMCE) |
| type | enum('invoice','estimate') | Which document type this applies to |
| is_default | boolean | Default message for its type (default: false) |
| is_active | boolean | Active or inactive (default: true) |
| created_by | bigint | Foreign key to users |
| timestamps | | created_at, updated_at |

**Indexes:** `(type, is_active)`, `is_default`

### 2. `document_message_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| document_message_id | bigint | Foreign key to document_messages (nullable, nullOnDelete) |
| action | string | Type of change: `created`, `updated`, `deleted` |
| field_changed | string | Which field was changed (nullable) |
| old_value | text | Previous value (nullable) |
| new_value | text | New value (nullable) |
| changed_by | bigint | Foreign key to users |
| created_at | timestamp | When the change occurred (no updated_at) |

---

## Models

### DocumentMessage Model

**Location:** `app/Models/DocumentMessage.php`

**Fillable:** `title`, `body`, `type`, `is_default`, `is_active`, `created_by`

**Casts:**
- `is_default` → boolean
- `is_active` → boolean

**Relationships:**
- `createdBy()` — BelongsTo User
- `histories()` — HasMany DocumentMessageHistory

**Static Methods:**
- `logHistory($messageId, $action, $field, $oldValue, $newValue)` — Creates a history entry

### DocumentMessageHistory Model

**Location:** `app/Models/DocumentMessageHistory.php`

**Fillable:** `document_message_id`, `action`, `field_changed`, `old_value`, `new_value`, `changed_by`

**Note:** No `updated_at` column (`const UPDATED_AT = null`)

**Relationships:**
- `documentMessage()` — BelongsTo DocumentMessage
- `changedBy()` — BelongsTo User

---

## Livewire Component

### DocumentMessageSettings (Inline)

**Location:** `app/Livewire/SystemSettings/DocumentMessageSettings.php`

**View:** `resources/views/livewire/system-settings/document-message-settings.blade.php`

**Features:**
- Table listing all messages (ordered by type, then title)
- Type filter tabs (All / Invoice / Estimate)
- Create/Edit form modal with TinyMCE rich text editor
- Delete confirmation modal
- History viewer modal
- Default enforcement per type in DB transaction
- Per-field change history logging

**Methods:**
- `create()` — Opens empty form modal
- `edit($id)` — Opens form modal populated with existing data
- `save()` — Validates and saves (create or update), logs history
- `confirmDelete($id)` — Opens delete confirmation modal
- `delete()` — Deletes message, logs deletion
- `viewHistory($id)` — Opens history modal with all entries
- `setFilter($type)` — Filters table by type (all, invoice, estimate)
- `closeFormModal()` / `cancelDelete()` / `closeHistory()` — Close respective modals

---

## Default Enforcement

Defaults are tracked **per document type** (one default invoice message, one default estimate message). When a message is saved with `is_default = true`:

1. A DB transaction wraps the entire operation
2. All other messages of **the same type** with `is_default = true` are set to `false`
3. The current message is saved with `is_default = true`

Setting an invoice message as default does **not** affect estimate defaults, and vice versa.

---

## History Logging

### On Create
- 1 row: action = `created`

### On Update
- 1 row per changed field: action = `updated`, with `field_changed`, `old_value`, `new_value`
- Tracked fields: `title`, `body`, `type`, `is_default`, `is_active`
- For `body` field, values are logged as "Changed" (not full HTML content)

### On Delete
- 1 row: action = `deleted`, `old_value` contains the title and type for reference

---

## TinyMCE Editor Integration

The `x-ui.tinymce-editor` component was updated with two changes:

### 1. `modalName` prop
Accepts a `modalName` prop (default: `'task-modal'` for backward compatibility). This allows the editor to properly initialize/destroy when used inside different modal contexts.

### 2. Dynamic script loading
The TinyMCE CDN script is now loaded dynamically via a `loadTinymce()` helper instead of `@once @push('scripts')`. This was necessary because `@push` does not work when the component is rendered dynamically by Livewire inside a conditional block (`@if`) that starts as `false` — the script tag never gets added to the page.

Additionally, `x-init` now calls `initEditor()` via `$nextTick` so the editor self-initializes when mounted into the DOM. This covers the pattern where a modal is conditionally rendered with `@if($showModal)` + `:show="true"` (the modal's `$watch('show')` doesn't fire because `show` starts as `true`, so `modal-opened` is never dispatched).

The `modal-opened` / `modal-closed` event listeners are kept for the always-in-DOM modal pattern (e.g., daily report task modal) where the editor needs to re-initialize after being destroyed on close.

Usage in this module:
```blade
<x-ui.tinymce-editor wire-model="body" modalName="message-form-modal" :height="250" />
```

---

## Files Created

**Migrations:**
- `database/migrations/2026_02_09_160000_create_document_messages_table.php`
- `database/migrations/2026_02_09_160001_create_document_message_histories_table.php`

**Models:**
- `app/Models/DocumentMessage.php`
- `app/Models/DocumentMessageHistory.php`

**Livewire Components:**
- `app/Livewire/SystemSettings/DocumentMessageSettings.php`

**Views:**
- `resources/views/livewire/system-settings/document-message-settings.blade.php`

**Modified Files:**
- `resources/views/components/ui/tinymce-editor.blade.php` — Added `modalName` prop; replaced `@push('scripts')` with dynamic script loading; added `x-init` auto-initialization
- `resources/views/livewire/system-settings/settings-index.blade.php` — Added Messages tab
