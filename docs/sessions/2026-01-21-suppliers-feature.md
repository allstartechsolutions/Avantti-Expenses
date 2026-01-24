# Suppliers Feature Implementation

**Date:** 2026-01-21
**Goal:** Create a suppliers management section with basic information

## Requirements

### Fields for Suppliers
- **name** (required) - Supplier company name
- **street** - Street address
- **address_2** - Complement/Apt/Suite
- **neighborhood** - Bairro (required for Brazilian addresses)
- **city** - City
- **state** - State/UF
- **postal_code** - CEP (Brazil) / ZIP (US)
- **country** - 2-char country code (default 'BR')
- **phone** - Contact phone
- **email** - Contact email
- **description** - Notes/description about the supplier

### Notes
- Not linked to catalog yet (future feature)
- Following Brazilian address format when country is 'BR'
- Following existing CRUD patterns from Client/Project modules

---

## Implementation Progress

### Step 1: Database Migration
- [x] Create suppliers table migration
- [x] Run migration

### Step 2: Model
- [x] Create Supplier model with relationships and attributes

### Step 3: CRUD Pages (one at a time)
- [x] SupplierIndex - List all suppliers (with delete functionality)
- [x] SupplierCreate - Add new supplier
- [x] SupplierEdit - Edit existing supplier
- [x] SupplierShow - View supplier details

### Step 4: Routes
- [x] Add routes to web.php

---

## Files Created

| Type | Path | Description |
|------|------|-------------|
| Migration | `database/migrations/2026_01_21_091531_create_suppliers_table.php` | Suppliers table |
| Model | `app/Models/Supplier.php` | Supplier model |
| Component | `app/Livewire/Supplier/SupplierIndex.php` | List component |
| View | `resources/views/livewire/supplier/supplier-index.blade.php` | List view |
| Component | `app/Livewire/Supplier/SupplierCreate.php` | Create component |
| View | `resources/views/livewire/supplier/supplier-create.blade.php` | Create form view |
| Component | `app/Livewire/Supplier/SupplierEdit.php` | Edit component |
| View | `resources/views/livewire/supplier/supplier-edit.blade.php` | Edit form view |
| Component | `app/Livewire/Supplier/SupplierShow.php` | Show component |
| View | `resources/views/livewire/supplier/supplier-show.blade.php` | Show detail view |
| Routes | `routes/web.php` | Added supplier routes |
| Sidebar | `resources/views/components/layouts/inc/sidebar.blade.php` | Added menu link |

---

## Technical Decisions

1. **Address handling**: Using same pattern as Project/JobSite with Brazilian format support
2. **Country default**: Set to 'BR' (Brazil) as default in migration
3. **No catalog link**: Will be added in future iteration
4. **Indexed columns**: name and email for faster searches

---

## Routes Added

```php
// Supplier routes
Route::get('suppliers', SupplierIndex::class)->name('suppliers.index');
Route::get('suppliers/create', SupplierCreate::class)->name('suppliers.create');
Route::get('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
Route::get('suppliers/{supplier}/edit', SupplierEdit::class)->name('suppliers.edit');
```

---

## Completed Features

- **Index**: List all suppliers with search, pagination, View/Edit/Delete buttons
- **Create**: Add new suppliers with country-aware address format
- **Show**: View supplier details with quick actions (email, call)
- **Edit**: Update supplier information
- **Delete**: Delete with confirmation modal

## Future Enhancements

- Link suppliers to catalog items
- Import/export suppliers
- Supplier categories/tags

