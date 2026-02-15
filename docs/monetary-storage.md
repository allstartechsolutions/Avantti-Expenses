# Monetary Value Storage

## Overview

This application stores all monetary values as **integers** (in cents) in the database, rather than as decimal values. This approach eliminates floating-point precision errors and aligns with industry best practices used by payment processors like Stripe and PayPal.

## Why Integer Storage?

### Problems with Decimal Storage
```php
// Decimal storage can have precision issues
$price1 = 10.00 / 3;  // 3.3333333333...
$price2 = $price1 * 3; // 9.9999999999... (not exactly 10.00!)
```

### Benefits of Integer Storage
- **Exact precision**: No floating-point rounding errors
- **Payment processor compatibility**: Stripe, PayPal, etc. all use cents
- **Better for calculations**: Integer arithmetic is exact
- **Smaller storage**: Integers are more efficient than decimals

## Database Schema

All monetary columns are stored as `unsignedBigInteger`:

```php
// Migration example
Schema::create('projects', function (Blueprint $table) {
    $table->unsignedBigInteger('initial_amount')->default(0); // Stored in cents
});

// Examples:
// $100.00 → stored as 10000
// $1.50 → stored as 150
// $1,234.56 → stored as 123456
```

### Affected Tables and Columns

| Table | Column(s) |
|-------|-----------|
| `job_sites` | `job_amount` |
| `projects` | `initial_amount` |
| `change_orders` | `amount` |
| `catalog_items` | `current_cost` |
| `catalog_item_price_history` | `old_cost`, `new_cost` |
| `expenses` | `unit_price`, `total_amount` |
| `contracts` | `amount` |
| `contract_payments` | `amount` |

## Model Implementation

### Accessors and Mutators

Each model uses Laravel's Attribute class to automatically convert between cents and dollars:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

class Project extends Model
{
    /**
     * Get/Set initial_amount as dollars (stored as cents)
     */
    protected function initialAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),  // DB → Application (cents to dollars)
            set: fn ($value) => round($value * 100),      // Application → DB (dollars to cents)
        );
    }
}
```

### How It Works

**When reading from database:**
```php
// Database has: 150000 (cents)
$project = Project::find(1);
echo $project->initial_amount;  // Outputs: 1500.00 (dollars)
```

**When writing to database:**
```php
$project = new Project();
$project->initial_amount = 1500.50;  // Input in dollars
$project->save();
// Database stores: 150050 (cents)
```

**In calculations:**
```php
// Database stores in cents: 11500 + 13500 = 25000
$expense1->unit_price = 115.00;  // Stored as 11500
$expense2->unit_price = 135.00;  // Stored as 13500
$total = $expense1->unit_price + $expense2->unit_price;
echo $total;  // 250.00 (accessors handle conversion)
```

## Livewire Integration

Livewire components work seamlessly with the accessor/mutator pattern:

```php
class ProjectCreate extends Component
{
    public $initial_amount = '';  // User enters: "1500.00"

    public function createProject()
    {
        $this->validate([
            'initial_amount' => 'required|numeric|min:0',
        ]);

        Project::create([
            'initial_amount' => $this->initial_amount,  // Mutator converts to cents
        ]);
    }
}
```

**No code changes needed!** The mutators automatically handle the conversion.

## Validation Rules

Use standard numeric validation - the conversion is handled automatically:

```php
protected $rules = [
    'job_amount' => 'required|numeric|min:0',
    'initial_amount' => 'required|numeric|min:0',
    'unit_price' => 'required|numeric|min:0',
];
```

## Calculated Attributes

For calculated monetary values, ensure rounding to 2 decimal places:

```php
// CatalogItem model
public function getUnitCostAttribute()
{
    if ($this->type === 'product' && $this->units_per_purchase > 0) {
        return round($this->current_cost / $this->units_per_purchase, 2);
    }
    return $this->current_cost;
}
```

## Aggregations and Sums

Aggregations work correctly because accessors are applied to retrieved models:

```php
// Get all expenses (accessors convert cents → dollars)
$expenses = $jobSite->expenses;

// Sum works on the converted dollar values
$total = $expenses->sum('total_amount');  // Correct total in dollars

// Database sum (returns cents, needs manual conversion)
$totalCents = Expense::where('job_site_id', 1)->sum('total_amount');
$totalDollars = round($totalCents / 100, 2);
```

## Migration from Decimal to Integer

The migration that converted the system:

```php
// database/migrations/2026_01_06_133116_convert_monetary_columns_to_integer.php

public function up(): void
{
    // Convert existing data (multiply by 100)
    DB::statement('UPDATE projects SET initial_amount = initial_amount * 100');

    // Change column type
    Schema::table('projects', function (Blueprint $table) {
        $table->unsignedBigInteger('initial_amount')->default(0)->change();
    });
}

public function down(): void
{
    // Revert column type
    Schema::table('projects', function (Blueprint $table) {
        $table->decimal('initial_amount', 15, 2)->default(0)->change();
    });

    // Revert data (divide by 100)
    DB::statement('UPDATE projects SET initial_amount = initial_amount / 100');
}
```

## Testing Examples

```php
// Create a project
$project = Project::create(['initial_amount' => 1500.00]);

// Database check
$rawValue = DB::table('projects')->find($project->id)->initial_amount;
echo $rawValue;  // 150000 (cents in DB)

// Model check
echo $project->initial_amount;  // 1500.00 (dollars via accessor)

// Update
$project->initial_amount = 2000.50;
$project->save();

$rawValue = DB::table('projects')->find($project->id)->initial_amount;
echo $rawValue;  // 200050 (cents in DB)
```

## Payment Processor Integration

This storage format is ready for payment processors:

```php
// Stripe example
$stripeAmount = $project->initial_amount * 100;  // Convert to cents
\Stripe\Charge::create([
    'amount' => $stripeAmount,  // Stripe expects cents
    'currency' => 'usd',
]);

// PayPal example (also uses cents for precision)
$paypalAmount = $expense->total_amount * 100;
```

## Best Practices

1. **Always use model attributes** - Never access raw database values directly
2. **Round calculated values** - Use `round($value, 2)` for divisions
3. **Validate as numeric** - Let accessors/mutators handle conversion
4. **Test with cents** - Test values like 1.15, 10.99 to ensure precision
5. **Use transactions** - For operations involving multiple monetary records

## Common Pitfalls to Avoid

❌ **Don't cast to decimal in model:**
```php
// WRONG - This conflicts with the Attribute accessor
protected $casts = [
    'initial_amount' => 'decimal:2',  // Remove this!
];
```

✅ **Do use Attribute accessors:**
```php
// CORRECT
protected function initialAmount(): Attribute
{
    return Attribute::make(
        get: fn ($value) => round($value / 100, 2),
        set: fn ($value) => round($value * 100),
    );
}
```

❌ **Don't query raw values expecting dollars:**
```php
// WRONG - Returns cents, not dollars
$total = DB::table('expenses')->sum('total_amount');
```

✅ **Do use Eloquent models:**
```php
// CORRECT - Accessor converts to dollars
$total = Expense::all()->sum('total_amount');
```

## Summary

- **Database**: Stores values as integers (cents)
- **Application**: Works with decimals (dollars)
- **Conversion**: Automatic via accessors/mutators
- **Precision**: Exact, no floating-point errors
- **Compatible**: Ready for Stripe, PayPal, etc.

For display formatting with currency symbols and locale-specific number formats, see [Currency Formatting](./currency-formatting.md).
