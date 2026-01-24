# Currency and Locale Formatting

## Overview

This application supports multi-currency formatting with locale-specific number formats. The system automatically formats monetary values with the correct currency symbol, thousands separators, and decimal separators based on the configured locale.

## Configuration

Currency and locale are configured in the `.env` file:

```env
APP_LOCALE=en_US
APP_CURRENCY=USD
```

### Environment Variables

| Variable | Description | Example Values |
|----------|-------------|----------------|
| `APP_LOCALE` | Number formatting locale | `en_US`, `de_DE`, `pt_BR`, `fr_FR` |
| `APP_CURRENCY` | Currency code (ISO 4217) | `USD`, `EUR`, `BRL`, `GBP`, `JPY` |

**Important:** The `APP_LOCALE` should match the region where your currency is used to ensure proper number formatting.

## Configuration Examples

### United States (Default)
```env
APP_LOCALE=en_US
APP_CURRENCY=USD
```
**Format:** `$1,500.00`
- Symbol: `$` (before amount)
- Thousands: `,` (comma)
- Decimals: `.` (period)

### Germany / Euro Zone
```env
APP_LOCALE=de_DE
APP_CURRENCY=EUR
```
**Format:** `1.500,00 €`
- Symbol: `€` (after amount)
- Thousands: `.` (period)
- Decimals: `,` (comma)

### Brazil
```env
APP_LOCALE=pt_BR
APP_CURRENCY=BRL
```
**Format:** `R$ 1.500,00`
- Symbol: `R$` (before amount)
- Thousands: `.` (period)
- Decimals: `,` (comma)

### United Kingdom
```env
APP_LOCALE=en_GB
APP_CURRENCY=GBP
```
**Format:** `£1,500.00`
- Symbol: `£` (before amount)
- Thousands: `,` (comma)
- Decimals: `.` (period)

### France / Euro
```env
APP_LOCALE=fr_FR
APP_CURRENCY=EUR
```
**Format:** `1 500,00 €`
- Symbol: `€` (after amount)
- Thousands: ` ` (space)
- Decimals: `,` (comma)

### Spain / Euro
```env
APP_LOCALE=es_ES
APP_CURRENCY=EUR
```
**Format:** `1.500,00 €`
- Symbol: `€` (after amount)
- Thousands: `.` (period)
- Decimals: `,` (comma)

### Japan
```env
APP_LOCALE=ja_JP
APP_CURRENCY=JPY
```
**Format:** `¥1,500`
- Symbol: `¥` (before amount)
- No decimals (JPY doesn't use cents)
- Thousands: `,` (comma)

## Usage in Blade Views

### Basic Usage

Use Laravel's `Number::currency()` helper with three parameters:

```blade
{{ Number::currency($amount, config('app.currency'), config('app.locale')) }}
```

**Parameters:**
1. `$amount` - The numeric value (in dollars/euros/etc, NOT cents)
2. `config('app.currency')` - The currency code from configuration
3. `config('app.locale')` - The locale for number formatting

### Examples in Views

```blade
{{-- Project amount --}}
<p>{{ Number::currency($project->initial_amount, config('app.currency'), config('app.locale')) }}</p>

{{-- Change order amount --}}
<td>{{ Number::currency($changeOrder->amount, config('app.currency'), config('app.locale')) }}</td>

{{-- Expense with fallback to 0 for empty values --}}
<p>{{ Number::currency($expense_unit_price ?: 0, config('app.currency'), config('app.locale')) }}</p>

{{-- Catalog item cost --}}
<div>{{ Number::currency($item->current_cost, config('app.currency'), config('app.locale')) }}</div>
```

### Handling Empty Values

Always provide a fallback to prevent errors when values might be empty strings:

```blade
{{-- Use ?: operator to default to 0 --}}
{{ Number::currency($amount ?: 0, config('app.currency'), config('app.locale')) }}

{{-- Alternative with null coalescing --}}
{{ Number::currency($amount ?? 0, config('app.currency'), config('app.locale')) }}
```

## Output Examples

### Same Value, Different Locales

**Value:** `1500.00`

| Locale | Currency | Output |
|--------|----------|--------|
| `en_US` | `USD` | `$1,500.00` |
| `de_DE` | `EUR` | `1.500,00 €` |
| `pt_BR` | `BRL` | `R$ 1.500,00` |
| `en_GB` | `GBP` | `£1,500.00` |
| `fr_FR` | `EUR` | `1 500,00 €` |
| `ja_JP` | `JPY` | `¥1,500` |

**Value:** `12345.67`

| Locale | Currency | Output |
|--------|----------|--------|
| `en_US` | `USD` | `$12,345.67` |
| `de_DE` | `EUR` | `12.345,67 €` |
| `pt_BR` | `BRL` | `R$ 12.345,67` |
| `en_GB` | `GBP` | `£12,345.67` |
| `fr_FR` | `EUR` | `12 345,67 €` |

**Value:** `1.15`

| Locale | Currency | Output |
|--------|----------|--------|
| `en_US` | `USD` | `$1.15` |
| `de_DE` | `EUR` | `1,15 €` |
| `pt_BR` | `BRL` | `R$ 1,15` |
| `en_GB` | `GBP` | `£1.15` |
| `fr_FR` | `EUR` | `1,15 €` |

## Testing Currency Formatting

You can test currency formatting using Laravel Tinker:

```bash
php artisan tinker
```

```php
// Test current configuration
Number::currency(1500.00, config('app.currency'), config('app.locale'));

// Test specific locales
Number::currency(1500.00, 'USD', 'en_US');  // $1,500.00
Number::currency(1500.00, 'EUR', 'de_DE');  // 1.500,00 €
Number::currency(1500.00, 'BRL', 'pt_BR');  // R$ 1.500,00
Number::currency(1500.00, 'GBP', 'en_GB');  // £1,500.00

// Test edge cases
Number::currency(1.15, 'EUR', 'de_DE');     // 1,15 €
Number::currency(0, 'USD', 'en_US');        // $0.00
Number::currency(1850000, 'EUR', 'de_DE');  // 1.850.000,00 €
```

## Switching Currencies for Different Customers

### Scenario: US Company (Default)

```env
APP_LOCALE=en_US
APP_CURRENCY=USD
```
All monetary values display as: `$1,500.00`

### Scenario: German Customer

```env
APP_LOCALE=de_DE
APP_CURRENCY=EUR
```
All monetary values display as: `1.500,00 €`

### Scenario: Brazilian Customer

```env
APP_LOCALE=pt_BR
APP_CURRENCY=BRL
```
All monetary values display as: `R$ 1.500,00`

**Steps to switch:**
1. Update `.env` file with new `APP_LOCALE` and `APP_CURRENCY`
2. Clear configuration cache: `php artisan config:clear`
3. All views automatically update with new format

## Supported Currencies (ISO 4217)

| Code | Currency | Typical Locales |
|------|----------|-----------------|
| `USD` | US Dollar | `en_US`, `en_CA` |
| `EUR` | Euro | `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `nl_NL` |
| `GBP` | British Pound | `en_GB` |
| `BRL` | Brazilian Real | `pt_BR` |
| `JPY` | Japanese Yen | `ja_JP` |
| `CAD` | Canadian Dollar | `en_CA`, `fr_CA` |
| `AUD` | Australian Dollar | `en_AU` |
| `MXN` | Mexican Peso | `es_MX` |
| `CNY` | Chinese Yuan | `zh_CN` |
| `INR` | Indian Rupee | `hi_IN`, `en_IN` |
| `CHF` | Swiss Franc | `de_CH`, `fr_CH`, `it_CH` |
| `SEK` | Swedish Krona | `sv_SE` |
| `NOK` | Norwegian Krone | `nb_NO` |
| `DKK` | Danish Krone | `da_DK` |
| `PLN` | Polish Zloty | `pl_PL` |
| `RUB` | Russian Ruble | `ru_RU` |
| `ZAR` | South African Rand | `en_ZA` |

For a complete list of currency codes, see: https://en.wikipedia.org/wiki/ISO_4217

## How It Works Internally

The `Number::currency()` helper uses PHP's `NumberFormatter` class:

```php
// Simplified internal logic
$formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
return $formatter->formatCurrency($amount, $currency);
```

The formatter automatically handles:
- Currency symbol placement
- Thousands separator
- Decimal separator
- Decimal places (2 for most currencies, 0 for JPY, 3 for some Middle Eastern currencies)
- Right-to-left text for some locales

## Files with Currency Formatting

All currency formatting is implemented in Blade views:

```
resources/views/livewire/
├── job-site/
│   └── job-site-show.blade.php      (11 instances)
├── project/
│   ├── project-index.blade.php      (1 instance)
│   └── project-show.blade.php       (2 instances)
└── catalog/
    ├── catalog-item-index.blade.php (2 instances)
    ├── catalog-item-create.blade.php (1 instance)
    └── catalog-item-edit.blade.php  (3 instances)
```

## Common Issues and Solutions

### Issue: Wrong decimal separator
```env
# Wrong - Using en locale with EUR
APP_LOCALE=en_US
APP_CURRENCY=EUR
# Output: €1,500.00 (wrong for Europe)
```

**Solution:** Match locale to currency region
```env
APP_LOCALE=de_DE
APP_CURRENCY=EUR
# Output: 1.500,00 € (correct)
```

### Issue: TypeError with empty strings

**Problem:** `Number::currency()` receives empty string instead of number

**Solution:** Use fallback values
```blade
{{-- Before (causes error) --}}
{{ Number::currency($amount, config('app.currency'), config('app.locale')) }}

{{-- After (safe) --}}
{{ Number::currency($amount ?: 0, config('app.currency'), config('app.locale')) }}
```

### Issue: Formatting doesn't change after updating .env

**Solution:** Clear configuration cache
```bash
php artisan config:clear
```

## Best Practices

1. **Match locale to currency region** - Use `de_DE` with `EUR`, not `en_US` with `EUR`
2. **Always provide fallback values** - Use `?: 0` to prevent errors with empty values
3. **Test with real amounts** - Test `1.15`, `1500.00`, and `12345.67` to verify formatting
4. **Clear cache after changes** - Run `php artisan config:clear` after updating `.env`
5. **Document customer settings** - Keep track of which customer uses which locale/currency

## Integration with Monetary Storage

This formatting system works seamlessly with the integer storage system:

```php
// Database stores: 115000 (cents)
$project = Project::find(1);

// Accessor converts: 115000 → 1150.00 (dollars)
$amount = $project->initial_amount;  // 1150.00

// View formats with locale
// Blade: {{ Number::currency($project->initial_amount, config('app.currency'), config('app.locale')) }}

// Output examples:
// en_US + USD: $1,150.00
// de_DE + EUR: 1.150,00 €
// pt_BR + BRL: R$ 1.150,00
```

The complete flow:
1. **Database**: `115000` (integer, cents)
2. **Accessor**: `1150.00` (float, dollars)
3. **Formatter**: `$1,150.00` (string, localized with currency symbol)

## Summary

- **Configuration**: Set `APP_LOCALE` and `APP_CURRENCY` in `.env`
- **Usage**: `Number::currency($amount, config('app.currency'), config('app.locale'))`
- **Benefits**: Automatic symbol, thousands separator, decimal separator
- **Multi-tenant**: Easy to configure per customer/country
- **170+ currencies** supported via ISO 4217

For information about how monetary values are stored in the database, see [Monetary Storage](./monetary-storage.md).
