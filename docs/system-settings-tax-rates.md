# System Settings — Tax Rates Module

## Overview

The System Settings page provides a centralized location for company-wide configuration. The first module is **Tax Rates**, which allows managing multiple tax rates by state with a default selection and full change history logging.

The System Settings page is separate from the existing `/settings/` routes, which handle user profile settings (Volt components).

## Key Features

- Tabbed settings page (extensible for future modules)
- Full CRUD for tax rates (state, rate, default flag)
- Only one default tax rate at a time (enforced via DB transaction)
- Change history logging per field on create, update, and delete

---

## Database Schema

### 1. `tax_rates` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| state | string | State abbreviation or name (e.g., TX, CA) |
| rate | decimal(5,4) | Tax rate as decimal (e.g., 0.0825 = 8.25%) |
| is_default | boolean | Whether this is the default tax rate (default: false) |
| created_by | bigint | Foreign key to users |
| timestamps | | created_at, updated_at |

**Indexes:** `state`, `is_default`

### 2. `tax_rate_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| tax_rate_id | bigint | Foreign key to tax_rates (nullable, nullOnDelete) |
| action | string | Type of change: `created`, `updated`, `deleted` |
| field_changed | string | Which field was changed (nullable) |
| old_value | string | Previous value (nullable) |
| new_value | string | New value (nullable) |
| changed_by | bigint | Foreign key to users |
| created_at | timestamp | When the change occurred (no updated_at) |

---

## Models

### TaxRate Model

**Location:** `app/Models/TaxRate.php`

**Fillable:** `state`, `rate`, `is_default`, `created_by`

**Casts:**
- `rate` → decimal:4
- `is_default` → boolean

**Relationships:**
- `createdBy()` — BelongsTo User
- `histories()` — HasMany TaxRateHistory

**Accessors:**
- `formatted_rate` — Returns rate as percentage string (e.g., "8.25%")

**Static Methods:**
- `logHistory($taxRateId, $action, $field, $oldValue, $newValue)` — Creates a history entry for the given tax rate

### TaxRateHistory Model

**Location:** `app/Models/TaxRateHistory.php`

**Fillable:** `tax_rate_id`, `action`, `field_changed`, `old_value`, `new_value`, `changed_by`

**Note:** No `updated_at` column (`const UPDATED_AT = null`)

**Relationships:**
- `taxRate()` — BelongsTo TaxRate
- `changedBy()` — BelongsTo User

---

## Livewire Components

### 1. SettingsIndex (Full-Page)

**Location:** `app/Livewire/SystemSettings/SettingsIndex.php`

**View:** `resources/views/livewire/system-settings/settings-index.blade.php`

**Features:**
- Full-page component with `->layout('components.layouts.app')`
- Tab bar for switching between settings modules
- Default tab: `tax-rates`
- Renders inline Livewire components per tab

### 2. TaxRateSettings (Inline)

**Location:** `app/Livewire/SystemSettings/TaxRateSettings.php`

**View:** `resources/views/livewire/system-settings/tax-rate-settings.blade.php`

**Features:**
- Table listing all tax rates (ordered by state)
- Create/Edit form modal
- Delete confirmation modal
- History viewer modal
- Default enforcement in DB transaction
- Per-field change history logging

**Methods:**
- `create()` — Opens empty form modal
- `edit($id)` — Opens form modal populated with existing data
- `save()` — Validates and saves (create or update), logs history
- `confirmDelete($id)` — Opens delete confirmation modal
- `delete()` — Deletes tax rate, logs deletion
- `viewHistory($id)` — Opens history modal with all entries
- `closeFormModal()` / `cancelDelete()` / `closeHistory()` — Close respective modals

---

## Route

```php
Route::get('system-settings', SettingsIndex::class)->name('system-settings.index');
```

**URL:** `/system-settings`
**Name:** `system-settings.index`

---

## Navigation

A "Settings" link with a gear icon is added to the sidebar, placed after the "Payments" link. Active state highlights when the route matches `system-settings.*`.

---

## Rate Storage

Tax rates are stored as decimals in the database and displayed as percentages in the UI:

| User Input | Stored Value | Display |
|------------|-------------|---------|
| 8.25 | 0.0825 | 8.25% |
| 10 | 0.1000 | 10.00% |
| 6.5 | 0.0650 | 6.50% |

---

## Default Enforcement

Only one tax rate can be the default at any time. When a tax rate is saved with `is_default = true`:

1. A DB transaction wraps the entire operation
2. All other tax rates with `is_default = true` are set to `false`
3. The current tax rate is saved with `is_default = true`

---

## History Logging

### On Create
- 1 row: action = `created`

### On Update
- 1 row per changed field: action = `updated`, with `field_changed`, `old_value`, `new_value`
- Tracked fields: `state`, `rate`, `is_default`

### On Delete
- 1 row: action = `deleted`, `old_value` contains the state and rate for reference

---

## Files Created

**Migrations:**
- `database/migrations/2026_02_09_100000_create_tax_rates_table.php`
- `database/migrations/2026_02_09_100001_create_tax_rate_histories_table.php`

**Models:**
- `app/Models/TaxRate.php`
- `app/Models/TaxRateHistory.php`

**Livewire Components:**
- `app/Livewire/SystemSettings/SettingsIndex.php`
- `app/Livewire/SystemSettings/TaxRateSettings.php`

**Views:**
- `resources/views/livewire/system-settings/settings-index.blade.php`
- `resources/views/livewire/system-settings/tax-rate-settings.blade.php`

**Modified Files:**
- `routes/web.php` — Added system-settings route
- `resources/views/components/layouts/inc/sidebar.blade.php` — Added Settings link

---

## Adding Future Settings Modules

To add a new tab to the settings page:

1. Create a new inline Livewire component (e.g., `app/Livewire/SystemSettings/NewModuleSettings.php`)
2. Create its Blade view
3. Add a new tab button in `settings-index.blade.php`
4. Add a conditional block to render the component when the tab is active

```blade
<!-- In settings-index.blade.php -->
<button wire:click="switchTab('new-module')" class="...">
    New Module
</button>

@if($activeTab === 'new-module')
    <livewire:system-settings.new-module-settings />
@endif
```
