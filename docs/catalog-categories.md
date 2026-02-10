# Catalog Categories

This document describes the Catalog Categories module implementation and how categories are used in the catalog system.

---

## Overview

Catalog Categories organize catalog items (products, services, rentals) into logical groups. Categories support:

- **Type filtering**: Categories can be restricted to specific item types
- **Hierarchical structure**: Categories can have parent-child relationships
- **Active/Inactive status**: Categories can be enabled or disabled
- **Display ordering**: Custom sort order for UI display

---

## Database Structure

### Table: `catalog_categories`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Category name (unique) |
| `slug` | string | URL-friendly name (auto-generated) |
| `applicable_types` | JSON | Array of item types this category applies to |
| `parent_id` | bigint (nullable) | Foreign key to parent category |
| `is_active` | boolean | Whether category is active (default: true) |
| `display_order` | integer | Sort order for display (default: 0) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

### Migration File

`database/migrations/2026_01_02_103205_create_catalog_categories_table.php`

---

## Applicable Types Array

The `applicable_types` field is a JSON array that controls which item types a category can be assigned to.

### Possible Values

```php
['product']           // Category only for products
['service']           // Category only for services
['rental']            // Category only for rentals
['product', 'rental'] // Category for products and rentals
['product', 'service', 'rental'] // Category for all types
null                  // Category for all types (legacy support)
```

### How It Works

1. **When creating/editing a catalog item**: The category dropdown only shows categories that include the item's type in their `applicable_types` array.

2. **Query example** (from `CatalogItemCreate.php`):
   ```php
   $categories = CatalogCategory::active()
       ->where(function ($query) {
           $query->whereJsonContains('applicable_types', $this->type)
               ->orWhereNull('applicable_types');
       })
       ->orderBy('name')
       ->get();
   ```

3. **Filtering in category list**: Categories can be filtered by type to show only categories applicable to products, services, or rentals.

### Example Categories

| Category Name | Applicable Types | Used For |
|---------------|------------------|----------|
| Hardware & Fasteners | `["product"]` | Products only |
| General Labor | `["service"]` | Services only |
| Heavy Equipment | `["rental"]` | Rentals only |
| Tools & Equipment | `["product", "rental"]` | Products and Rentals |

---

## Model

### File: `app/Models/CatalogCategory.php`

```php
class CatalogCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'applicable_types',
        'parent_id',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'applicable_types' => 'array',  // Auto JSON encode/decode
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];
}
```

### Relationships

| Relationship | Type | Description |
|--------------|------|-------------|
| `parent()` | BelongsTo | Parent category (for hierarchy) |
| `children()` | HasMany | Child categories |
| `items()` | HasMany | Catalog items in this category |

### Scopes

| Scope | Description |
|-------|-------------|
| `active()` | Only active categories (`is_active = true`) |
| `root()` | Only root categories (`parent_id IS NULL`) |

### Auto-generated Slug

The model automatically generates a slug from the name when creating:

```php
protected static function boot()
{
    parent::boot();

    static::creating(function ($category) {
        if (empty($category->slug)) {
            $category->slug = Str::slug($category->name);
        }
    });
}
```

---

## Livewire Components

### Index (List)

**File:** `app/Livewire/Catalog/CatalogCategoryIndex.php`

**View:** `resources/views/livewire/catalog/catalog-category-index.blade.php`

**Route:** `GET /catalog/categories` → `catalog.categories.index`

**Features:**
- Search by category name
- Filter by applicable type (product, service, rental)
- Filter by status (active, inactive)
- Pagination (15 per page)
- Delete category (only if no items assigned)
- Shows item count per category

### Create

**File:** `app/Livewire/Catalog/CatalogCategoryCreate.php`

**View:** `resources/views/livewire/catalog/catalog-category-create.blade.php`

**Route:** `GET /catalog/categories/create` → `catalog.categories.create`

**Fields:**
- Name (required, unique)
- Applicable Types (required, checkboxes)
- Parent Category (optional, dropdown)
- Display Order (optional, number)
- Status (toggle, default: active)

### Edit

**File:** `app/Livewire/Catalog/CatalogCategoryEdit.php`

**View:** `resources/views/livewire/catalog/catalog-category-edit.blade.php`

**Route:** `GET /catalog/categories/{category}/edit` → `catalog.categories.edit`

**Features:**
- Same fields as Create
- Prevents setting self as parent category
- Unique name validation excludes current category

---

## Routes

```php
// Catalog Category routes
Route::get('catalog/categories', CatalogCategoryIndex::class)
    ->name('catalog.categories.index');
Route::get('catalog/categories/create', CatalogCategoryCreate::class)
    ->name('catalog.categories.create');
Route::get('catalog/categories/{category}/edit', CatalogCategoryEdit::class)
    ->name('catalog.categories.edit');
```

---

## Validation Rules

### Create

```php
[
    'name' => 'required|string|max:255|unique:catalog_categories,name',
    'applicable_types' => 'required|array|min:1',
    'applicable_types.*' => 'in:product,service,rental',
    'parent_id' => 'nullable|exists:catalog_categories,id',
    'is_active' => 'boolean',
    'display_order' => 'integer|min:0',
]
```

### Edit

```php
[
    'name' => 'required|string|max:255|unique:catalog_categories,name,' . $this->category->id,
    // ... same as create
]
```

---

## Sidebar Navigation

The Catalog menu in the sidebar (`resources/views/components/layouts/inc/sidebar.blade.php`) has a submenu:

- **All Items** → `/catalog`
- **Categories** → `/catalog/categories`

The submenu auto-expands when on any `catalog.*` route (configured in `app.blade.php`).

---

## Default Categories (Seeder)

**File:** `database/seeders/CatalogCategorySeeder.php`

Default categories created by the seeder:

**Product Categories:**
- Hardware & Fasteners
- Lumber & Building Materials
- Electrical Supplies
- Plumbing Supplies
- Paint & Finishing
- Tools & Equipment
- Safety Equipment

**Service Categories:**
- General Labor
- Skilled Labor
- Specialized Services
- Subcontractor Services
- Professional Services

**Rental Categories:**
- Heavy Equipment
- Power Tools
- Scaffolding & Ladders
- Vehicles

### Running the Seeder

```bash
php artisan db:seed --class=CatalogCategorySeeder
```

---

## Delete Protection

Categories cannot be deleted if they have:
1. **Associated catalog items** - Must reassign or delete items first
2. **Child categories** - Must delete or reassign children first

The delete button is hidden in the UI when a category has items, and the controller validates before deletion:

```php
public function deleteCategory($id)
{
    $category = CatalogCategory::findOrFail($id);

    if ($category->items()->count() > 0) {
        session()->flash('error', 'Cannot delete category with associated catalog items.');
        return;
    }

    if ($category->children()->count() > 0) {
        session()->flash('error', 'Cannot delete category with subcategories.');
        return;
    }

    $category->delete();
    session()->flash('message', 'Category deleted successfully.');
}
```

---

## Usage in Catalog Items

When creating or editing a catalog item, the category dropdown is filtered based on the item type:

```php
// In CatalogItemCreate.php / CatalogItemEdit.php
$categories = CatalogCategory::active()
    ->where(function ($query) {
        $query->whereJsonContains('applicable_types', $this->type)
            ->orWhereNull('applicable_types');
    })
    ->orderBy('name')
    ->get();
```

This ensures users only see relevant categories for the item type they're creating/editing.

---

## Catalog Item Tax Configuration

Catalog items support an optional taxable flag with a linked tax rate from the System Settings tax rates.

### Database Fields

| Column | Type | Description |
|--------|------|-------------|
| `is_taxable` | boolean | Whether the item is taxable (default: false) |
| `tax_rate_id` | bigint (nullable) | Foreign key to `tax_rates` table (nullOnDelete) |

**Migration:** `database/migrations/2026_02_09_154514_add_tax_fields_to_catalog_items_table.php`

### How It Works

1. **Toggle OFF (default)**: Item is not taxable. `tax_rate_id` is null.
2. **Toggle ON**: A tax rate dropdown appears. The system default tax rate is auto-selected.
3. **Tax rate is a reference (FK)**, not a hardcoded value. When a tax rate is updated in System Settings, all catalog items using that rate automatically reflect the new rate.
4. If a tax rate is deleted from System Settings, the `tax_rate_id` is set to null (`nullOnDelete`), and the item remains taxable but without an assigned rate.

### Auto-Select Default Behavior

When the taxable toggle is turned on, the `updatedIsTaxable()` Livewire hook queries for the system default tax rate and pre-selects it:

```php
public function updatedIsTaxable($value)
{
    if ($value) {
        $default = TaxRate::where('is_default', true)->first();
        $this->tax_rate_id = $default?->id ?? '';
    } else {
        $this->tax_rate_id = '';
    }
}
```

### Tax Rate Dropdown

The dropdown displays all tax rates ordered by state in the format:
```
State - Rate% (Default)
```
Example: `TX - 8.25% (Default)`

The `(Default)` label is only shown for the system default tax rate.

### Validation Rules

```php
'is_taxable' => 'boolean',
'tax_rate_id' => 'required|exists:tax_rates,id', // Only when is_taxable is true
```

### Model Relationship

```php
// CatalogItem model
public function taxRate(): BelongsTo
{
    return $this->belongsTo(TaxRate::class);
}
```

### Affected Files

- **Model:** `app/Models/CatalogItem.php` — added `is_taxable`, `tax_rate_id` to fillable/casts, added `taxRate()` relationship
- **Create:** `app/Livewire/Catalog/CatalogItemCreate.php` — added tax properties, validation, `updatedIsTaxable()`, passes `$taxRates` to view
- **Edit:** `app/Livewire/Catalog/CatalogItemEdit.php` — same as create, plus mounts existing values
- **Create View:** `resources/views/livewire/catalog/catalog-item-create.blade.php` — Tax section after Pricing
- **Edit View:** `resources/views/livewire/catalog/catalog-item-edit.blade.php` — Tax section after Pricing
