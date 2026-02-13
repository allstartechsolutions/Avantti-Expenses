# Module Access — System-Wide Feature Toggle

## Overview

The Module Access feature provides a system-wide toggle to enable or disable entire modules from **System Settings > Modules**. When a module is disabled, it is hidden from the sidebar and its routes return a 403 error. This allows tailoring the system per customer without code changes.

Core modules (Dashboard, Company) are always enabled and cannot be toggled.

---

## Key Features

- Toggle modules on/off from System Settings
- Disabled modules are hidden from the sidebar navigation
- Direct URL access to disabled modules returns 403
- Change history audit trail for every toggle action
- Results are cached for 5 minutes to avoid repeated DB queries
- Core modules (Dashboard, Company) are protected and always active

---

## Module Registry

All modules are defined in `config/modules.php`. Each entry maps a module key to its metadata and route prefixes:

| Module Key | Display Name | Core | Route Prefixes |
|------------|-------------|------|----------------|
| `dashboard` | Dashboard | Yes | `dashboard` |
| `company` | Company | Yes | `company.*`, `users.*`, `system-settings.*` |
| `projects` | Projects | No | `projects.*`, `subcontractors.*`, `clients.*`, `cost-codes.*`, `payments.*`, `jobsites.*`, `expenses.*`, `dailyreports.*`, `purchase-orders.*`, `budgets.*`, `job-sites.*`, `projects.budgets.*` |
| `catalog` | Catalog | No | `catalog.*`, `suppliers.*` |
| `estimates` | Estimates | No | `estimates.*` |
| `invoices` | Invoices | No | `invoices.*` |

To add a new module, add an entry to `config/modules.php` and run the seed migration (or insert a row manually into `module_access`).

---

## Database Schema

### 1. `module_access` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| module_key | string | Unique key matching `config/modules.php` (e.g., `invoices`) |
| module_name | string | Human-readable name (e.g., "Invoices") |
| description | text (nullable) | Brief description of the module |
| is_enabled | boolean | Whether the module is currently active (default: true) |
| is_core | boolean | Whether the module is core and cannot be disabled (default: false) |
| created_by | bigint | Foreign key to users |
| timestamps | | created_at, updated_at |

### 2. `module_access_histories` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| module_access_id | bigint | Foreign key to module_access (cascadeOnDelete) |
| action | string | Type of change (e.g., `updated`) |
| field_changed | string (nullable) | Which field was changed (e.g., `is_enabled`) |
| old_value | string (nullable) | Previous value (e.g., "Enabled") |
| new_value | string (nullable) | New value (e.g., "Disabled") |
| changed_by | bigint | Foreign key to users |
| created_at | timestamp | When the change occurred (no updated_at) |

---

## Models

### ModuleAccess

**Location:** `app/Models/ModuleAccess.php`

**Table:** `module_access`

**Fillable:** `module_key`, `module_name`, `description`, `is_enabled`, `is_core`, `created_by`

**Casts:**
- `is_enabled` → boolean
- `is_core` → boolean

**Relationships:**
- `createdBy()` — BelongsTo User
- `histories()` — HasMany ModuleAccessHistory

**Static Methods:**
- `isEnabled(string $moduleKey): bool` — Returns whether a module is enabled. Cached for 5 minutes. Core modules always return true.
- `clearCache(string $moduleKey): void` — Clears the cache for a specific module key.
- `logHistory($id, $action, $field, $oldValue, $newValue)` — Creates a history entry.

### ModuleAccessHistory

**Location:** `app/Models/ModuleAccessHistory.php`

**Fillable:** `module_access_id`, `action`, `field_changed`, `old_value`, `new_value`, `changed_by`

**Note:** No `updated_at` column (`const UPDATED_AT = null`)

**Relationships:**
- `moduleAccess()` — BelongsTo ModuleAccess
- `changedBy()` — BelongsTo User

---

## Middleware

### CheckModuleAccess

**Location:** `app/Http/Middleware/CheckModuleAccess.php`

**Registered in:** `bootstrap/app.php` → appended to the `web` middleware group

**How it works:**
1. Gets the current route name
2. Loops through `config('modules')` to find which module owns the route
3. Skips core modules (always allowed)
4. If the module is disabled → `abort(403, 'This module is currently disabled.')`
5. If no module matches the route → request passes through

Route matching uses Laravel's `Str::is()` with wildcard patterns (e.g., `invoices.*` matches `invoices.index`, `invoices.create`, etc.).

---

## Livewire Component

### ModuleAccessSettings (Inline)

**Location:** `app/Livewire/SystemSettings/ModuleAccessSettings.php`

**View:** `resources/views/livewire/system-settings/module-access-settings.blade.php`

**Features:**
- Card-based layout showing each toggleable module
- Toggle switch to enable/disable modules
- Status badge (Enabled/Disabled)
- History modal per module

**Methods:**
- `toggle(int $id)` — Flips `is_enabled`, logs history, clears cache
- `viewHistory(int $id)` — Opens history modal with all change entries
- `closeHistory()` — Closes history modal

**Note:** Unlike TaxRateSettings, there is no create/edit/delete — modules are predefined via migration seed. Only the enabled state can be toggled.

---

## Sidebar Integration

The sidebar (`resources/views/components/layouts/inc/sidebar.blade.php`) wraps each toggleable module section with:

```blade
@if(\App\Models\ModuleAccess::isEnabled('module_key'))
    <!-- module sidebar block -->
@endif
```

Wrapped sections:
- **Projects** — The full Projects dropdown (All Projects, Subcontractors, Clients, Cost Codes, Payments)
- **Catalog** — The full Catalog dropdown (All Items, Categories, Suppliers)
- **Estimates** — The Estimates link
- **Invoices** — The Invoices link

Dashboard and Company are core modules and are always visible.

---

## Settings Page Integration

The Modules tab is added to the existing System Settings page (`/system-settings`):

- **Tab button:** "Modules" (third tab after Tax Rates and Messages)
- **Tab content:** `<livewire:system-settings.module-access-settings />`
- **Livewire component:** `SettingsIndex` handles tab switching via `switchTab('modules')`

---

## Caching

Module enabled/disabled status is cached using Laravel's Cache facade:

- **Cache key:** `module_access.{module_key}` (e.g., `module_access.invoices`)
- **TTL:** 300 seconds (5 minutes)
- **Cleared on toggle:** `ModuleAccess::clearCache($moduleKey)` is called after every toggle

The sidebar calls `ModuleAccess::isEnabled()` on every page load, so the cache prevents repeated DB queries.

---

## History Logging

Every toggle creates a history entry:

| Field | Value |
|-------|-------|
| action | `updated` |
| field_changed | `is_enabled` |
| old_value | `Enabled` or `Disabled` |
| new_value | `Disabled` or `Enabled` |
| changed_by | Current authenticated user ID |

---

## Files

### Created

**Config:**
- `config/modules.php`

**Migrations:**
- `database/migrations/2026_02_13_100000_create_module_access_table.php`
- `database/migrations/2026_02_13_100001_create_module_access_histories_table.php`
- `database/migrations/2026_02_13_100002_seed_default_modules.php`

**Models:**
- `app/Models/ModuleAccess.php`
- `app/Models/ModuleAccessHistory.php`

**Middleware:**
- `app/Http/Middleware/CheckModuleAccess.php`

**Livewire:**
- `app/Livewire/SystemSettings/ModuleAccessSettings.php`
- `resources/views/livewire/system-settings/module-access-settings.blade.php`

### Modified

- `bootstrap/app.php` — Registered CheckModuleAccess middleware in web group
- `resources/views/livewire/system-settings/settings-index.blade.php` — Added Modules tab
- `resources/views/components/layouts/inc/sidebar.blade.php` — Wrapped module sections with `@if` checks

---

## Adding a New Module

1. Add the module entry to `config/modules.php`:
   ```php
   'reports' => [
       'name' => 'Reports',
       'description' => 'Generate and view reports.',
       'route_prefixes' => ['reports.*'],
   ],
   ```

2. Insert a row into the `module_access` table (via migration or tinker):
   ```php
   ModuleAccess::create([
       'module_key' => 'reports',
       'module_name' => 'Reports',
       'description' => 'Generate and view reports.',
       'is_enabled' => true,
       'is_core' => false,
       'created_by' => 1,
   ]);
   ```

3. Wrap the sidebar section for the new module:
   ```blade
   @if(\App\Models\ModuleAccess::isEnabled('reports'))
       <!-- Reports sidebar block -->
   @endif
   ```

The middleware will automatically enforce route access based on the config entry — no additional code needed.
