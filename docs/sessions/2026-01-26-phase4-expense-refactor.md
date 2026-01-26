# Phase 4: Expense Refactor - Multi-Item Expenses with Cost Code Tracking

**Date:** 2026-01-26
**Status:** Completed (Ready for testing)

---

## Overview

Transform the expense system from a flat structure (one expense = one item) to a header-detail model (one expense = multiple items), with each item linked to a budget item (cost code).

### Previous State
- `expenses` table stored both header and item data together
- One expense record = one item
- No link to budget/cost codes

### Current State (After Refactor)
- `expenses` table is the header (supplier, date, payment info)
- New `expense_items` table stores line items
- Each line item links to a `budget_item` (cost code)
- Auto-creates "Miscellaneous" budget item if no cost code selected
- Standalone ExpenseCreate page for adding expenses
- Modal within page for adding/editing individual items

---

## Database Changes Completed

### 1. Added `supplier_id` to Expenses
**Migration:** `2026_01_26_153157_add_supplier_id_to_expenses_table.php`
```php
$table->foreignId('supplier_id')->nullable()->after('job_site_id')
    ->constrained('suppliers')->nullOnDelete();
```

### 2. Created `expense_items` Table
**Migration:** `2026_01_26_153220_create_expense_items_table.php`
```php
Schema::create('expense_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
    $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
    $table->foreignId('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
    $table->string('item_name');
    $table->enum('item_type', ['catalog', 'custom'])->default('custom');
    $table->text('description')->nullable();
    $table->decimal('quantity', 10, 2)->default(1);
    $table->string('unit', 50)->nullable();
    $table->unsignedBigInteger('unit_price')->default(0);      // cents
    $table->unsignedBigInteger('total_amount')->default(0);    // cents
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### 3. Made Old Item Columns Nullable
**Migration:** `2026_01_26_172737_make_expense_item_columns_nullable.php`

Made the following columns nullable on `expenses` table (for backward compatibility):
- `item_name`
- `unit_type_used`
- `quantity`
- `unit_price`

### 4. Data Migration
**Command:** `php artisan app:migrate-expense-items`

Migrated 7 existing expenses to the new structure (each expense got 1 expense_item).

---

## Files Created

| File | Description |
|------|-------------|
| `database/migrations/2026_01_26_153157_add_supplier_id_to_expenses_table.php` | Add supplier FK to expenses |
| `database/migrations/2026_01_26_153220_create_expense_items_table.php` | Create expense_items table |
| `database/migrations/2026_01_26_172737_make_expense_item_columns_nullable.php` | Make old columns nullable |
| `app/Models/ExpenseItem.php` | ExpenseItem model with relationships and money accessors |
| `app/Services/BudgetService.php` | Service for auto-creating budgets and Miscellaneous items |
| `app/Console/Commands/MigrateExpenseItems.php` | Command to migrate existing expenses |
| `app/Livewire/Expense/ExpenseCreate.php` | Standalone expense creation component |
| `resources/views/livewire/expense/expense-create.blade.php` | Expense creation view with item modal |

## Files Modified

| File | Changes |
|------|---------|
| `app/Models/Expense.php` | Added `supplier()` and `items()` relationships |
| `resources/views/livewire/project/project-show.blade.php` | Updated "Add Expense" buttons to link to standalone page |
| `resources/views/livewire/job-site/job-site-show.blade.php` | Updated "Add Expense" buttons to link to standalone page |
| `routes/web.php` | Added expense creation routes |

---

## Routes Added

```php
// Expense routes
Route::get('projects/{project}/expenses/create', ExpenseCreate::class)
    ->name('expenses.project.create');
Route::get('job-sites/{jobSite}/expenses/create', ExpenseCreate::class)
    ->name('expenses.jobsite.create');
```

---

## Key Features

### ExpenseCreate Component
- **Location selector**: Choose project-level or specific job site
- **Supplier search**: Autocomplete search for existing suppliers
- **Items management**: Add/edit/remove items via modal
- **Cost code search**: Autocomplete search for budget items (cost codes)
- **Catalog/Custom toggle**: Use catalog items or enter custom items
- **Auto-calculation**: Item totals and expense total calculated automatically
- **Payment options**: Single payment or installments with frequency settings
- **Receipt upload**: Optional file upload for receipt

### BudgetService
- `getOrCreateBudget()`: Gets or creates a budget for a project/job site
- `getOrCreateMiscellaneousItem()`: Gets or creates the Miscellaneous cost code (999999)
- `getMiscellaneousItem()`: Shorthand to get Miscellaneous for a location
- `ensureBudgetItem()`: Assigns Miscellaneous to items without a cost code

### Auto-Creation Logic
When saving an expense item without a cost code:
1. System checks if a budget exists for the location
2. If no budget exists, creates one with name "Project Budget" or "Job Site Budget"
3. Creates a "Miscellaneous" budget item with code `999999`
4. Assigns the expense item to the Miscellaneous cost code

---

## Testing Checklist

- [ ] Create expense with single item
- [ ] Create expense with multiple items
- [ ] Create expense without selecting cost code (should auto-assign Miscellaneous)
- [ ] Create expense when no budget exists (should auto-create budget)
- [ ] Edit existing expense items
- [ ] Delete expense (should cascade delete items)
- [ ] Payment tracking with installments
- [ ] Receipt upload
- [ ] View expense modal shows items table

---

## Future Improvements (Out of Scope)

1. **ExpenseEdit component**: Create standalone edit page similar to ExpenseCreate
2. **ExpenseShow component**: Create standalone view page or keep modal
3. **Remove deprecated columns**: After full testing, remove old item columns from expenses table
4. **Budget tracking**: Show spent vs budgeted amounts per cost code

---

## Notes

1. **Payment tracking stays on header** - One payment schedule per expense (not per item)
2. **Receipts stay on header** - One receipt per expense (covers all items)
3. **Supplier is optional** - Can still create expenses without selecting a supplier
4. **Budget auto-creation** - System creates budget + Miscellaneous if none exists
5. **Backward compatibility** - Existing expenses migrated to have 1 item each
6. **Total amount cached** - Stored on expense header, recalculated when items change
