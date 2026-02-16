# Sistema de despesas - Documentation

## Documentation Index

### Monetary System

1. **[Monetary Storage](./monetary-storage.md)** - Integer-based storage system
   - Why we store values as integers (cents) instead of decimals
   - Database schema and migration details
   - Model accessors and mutators
   - How to work with monetary values in code
   - Payment processor integration

2. **[Currency Formatting](./currency-formatting.md)** - Multi-currency locale formatting
   - Configuration for different countries and currencies
   - How to format currency in views
   - Supported currencies and locales
   - Examples for US, Europe, Brazil, and more
   - Troubleshooting and best practices

### Address System

3. **[Address System](./address-system.md)** - Multi-country address handling with Google Places
   - Country-specific address formats (US and Brazil)
   - Google Places Autocomplete integration
   - Latitude/Longitude geocoding
   - Database schema and model implementation
   - Conditional field display based on country
   - Configuration and setup guide

### Weather System

4. **[Weather System](./weather-system.md)** - Weather data for Daily Reports
   - Visual Crossing API integration
   - Historical and forecast weather data
   - Manual weather observations
   - Temperature units based on country (°F for US, °C for BR)
   - Precipitation, humidity, and wind data
   - Daily weather snapshots

### UI Components

5. **[Header Search](./header-search.md)** - Project search in header
   - Debounced search (300ms) for performance
   - Searches by project name
   - Shows project name and address in results
   - Lazy loading - no queries on page load
   - Optimized for thousands of projects

### Data Management

6. **[Delete Functionality](./delete-functionality.md)** - Project & Job Site deletion with confirmation modals
   - Confirmation modal with related data counts (not just `wire:confirm`)
   - Manual file cleanup before cascade delete (Eloquent events won't fire on cascade)
   - Polymorphic image cleanup (DailyReportImage)
   - DB transaction wrapping
   - Actions slot pattern for shared layouts

### Modules

7. **[Purchase Order Module](./purchase-order-module.md)** - Complete PO workflow with approval, expense creation, and revision support
8. **[Estimate Module](./estimate-module.md)** - Client estimates with catalog/custom items, discounts, tax, payment terms, message templates, PDF generation, email sending with tracking pixel open detection, email history log, and status change tracking
9. **[Invoice Module](./invoice-module.md)** - Client invoices with the same feature set as estimates, plus conversion from accepted estimates, past due detection, and status change tracking (Draft → Sent → Pending → Partial → Paid)
10. **[Invoice Payments & CardPointe](./invoice-payments-module.md)** - Payment recording (manual + credit card), CardPointe Gateway integration, client saved payment methods, partial payment tracking, void/refund support
11. **[Contract Module](./contract-module.md)** - Subcontractor contracts with status workflow, file attachments, and audit trail
12. **[Contract Payments](./contract-payments.md)** - Payment tracking for subcontractor contracts, automatic status transitions (completed → partially_paid → paid)

### User Management

13. **[User Profile](./user-profile.md)** - Profile page for updating name, email, phone, and password
    - Live password requirements checklist (Alpine.js)
    - Logout on password change

### Architecture

14. **[Project-Level Resources](./project-level-resources.md)** - Dual foreign key pattern for project/job site resources
15. **[Project & Job Site Parity Rule](./project-jobsite-parity-rule.md)** - Mandatory parity between project and job site levels
16. **[JobSite Tabs to Pages Migration](./jobsite-tabs-to-pages.md)** - Migration from monolithic tabs to separate page components
17. **[Sidebar Navigation](./sidebar-navigation.md)** - Sidebar navigation structure
18. **[Project Manager Field](./project-manager-field.md)** - Optional project manager assignment from active users
19. **[Job Site Supervisor Field](./jobsite-supervisor-field.md)** - Optional supervisor assignment with change history tracking

### Quick Reference

#### Storage Format
```
Database:   150000 (integer, cents)
           ↓ (Accessor)
Application: 1500.00 (float, dollars)
           ↓ (Formatter)
Display:    $1,500.00 (string, localized)
```

#### Configuration (`.env`)
```env
# United States (Default)
APP_LOCALE=en_US
APP_CURRENCY=USD
# Output: $1,500.00

# Germany / Europe
APP_LOCALE=de_DE
APP_CURRENCY=EUR
# Output: 1.500,00 €

# Brazil
APP_LOCALE=pt_BR
APP_CURRENCY=BRL
# Output: R$ 1.500,00
```

#### Common Tasks

**Display a monetary value in a view:**
```blade
{{ Number::currency($project->initial_amount, config('app.currency'), config('app.locale')) }}
```

**Create/Update a monetary value:**
```php
$project = Project::create([
    'initial_amount' => 1500.00,  // Just use dollars, mutator handles conversion
]);
```

**Calculate totals:**
```php
$total = $expenses->sum('total_amount');  // Works correctly with accessors
```

**Switch currency for a different customer:**
1. Edit `.env` file
2. Update `APP_LOCALE` and `APP_CURRENCY`
3. Run `php artisan config:clear`
4. All monetary values automatically update

### Benefits of This System

✅ **Precision**: No floating-point errors
✅ **International**: Supports 170+ currencies with correct formatting
✅ **Payment-ready**: Compatible with Stripe, PayPal, etc.
✅ **Simple**: Developers work with regular dollar amounts
✅ **Automatic**: Conversions happen transparently

### Getting Started

1. Read [Monetary Storage](./monetary-storage.md) to understand how values are stored
2. Read [Currency Formatting](./currency-formatting.md) to learn about display formatting
3. See examples in the existing codebase (models and views)
4. Test with different locales to see formatting changes

### Questions?

- How are values stored? → See [Monetary Storage](./monetary-storage.md)
- How to format for different countries? → See [Currency Formatting](./currency-formatting.md)
- How to change currency? → Update `.env` file (see Currency Formatting docs)
- Having precision issues? → Check that you're using the accessor pattern correctly

---

## Address System Quick Reference

### Configuration (`.env`)
```env
# Country (affects address form fields and autocomplete)
APP_COUNTRY=US  # or BR for Brazil

# Google Maps API Key (for address autocomplete)
GOOGLE_MAPS_API_KEY=your_api_key_here
```

### Country Differences

| Feature | US | BR |
|---------|-----|-----|
| Neighborhood field | Hidden | Visible (Bairro) |
| Postal code label | "Postal Code" | "CEP" |
| Autocomplete | US addresses | Brazilian addresses |

### Common Tasks

**Display full address:**
```php
echo $project->full_address;
// US: "123 Main St, Suite 100, New York, NY, 10001"
// BR: "Rua Augusta, 1234, Consolação, São Paulo, SP, 01310-100"
```

**Switch country:**
1. Edit `.env` file: `APP_COUNTRY=BR`
2. Run `php artisan config:clear`
3. Forms automatically show country-specific fields

**Enable address autocomplete:**
1. Get API key from [Google Cloud Console](https://console.cloud.google.com/)
2. Enable: Places API, Geocoding API, Maps JavaScript API
3. Add to `.env`: `GOOGLE_MAPS_API_KEY=your_key`

### Benefits

- Multi-country support (US, Brazil)
- Google Places autocomplete
- Automatic lat/long geocoding
- Country-specific form fields
- Easy country switching via ENV

---

## Weather System Quick Reference

### Configuration (`.env`)
```env
# Visual Crossing Weather API
VISUAL_CROSSING_API_KEY=your_api_key_here
```

### Country-Specific Units

| Measurement | US | BR |
|-------------|-----|-----|
| Temperature | Fahrenheit (°F) | Celsius (°C) |
| Precipitation | Inches (in) | Millimeters (mm) |
| Wind Speed | mph | km/h |

### Common Tasks

**Fetch weather in Daily Report:**
1. Create/edit a daily report for a job site with geocoded address
2. Click "Fetch Weather" button
3. Weather data displays automatically
4. Save report to persist data

**Add manual observation:**
1. Click "Add Observation" in Observed Weather Conditions section
2. Fill in time, conditions, and optional notes
3. Save the observation
4. Save report to persist all observations

**Format temperature in code:**
```php
use App\Services\WeatherService;

// Format for display (auto-detects country)
WeatherService::formatTemperature(72); // "72°F" (US) or "22°C" (BR)

// Get current unit
WeatherService::getTemperatureUnit(); // "F" or "C"
```

### Benefits

- Historical and forecast weather data
- Manual observation tracking
- Weather delay documentation
- Automatic unit conversion based on country
- Precipitation accumulation tracking (1, 2, 3 days)

---

## Header Search Quick Reference

### How It Works

1. User types at least 2 characters in search field
2. After 300ms pause, Livewire queries the database
3. Results show project name and address (max 8 results)
4. Click result to navigate to project page

### Performance Features

- **Debounced**: 300ms delay prevents excessive queries
- **Lazy**: No database queries until user searches
- **Limited**: Maximum 8 results returned
- **Indexed**: `project_name` column is indexed

### Files

| File | Purpose |
|------|---------|
| `app/Livewire/Shared/HeaderSearch.php` | Livewire component |
| `resources/views/livewire/shared/header-search.blade.php` | Search view |
| `resources/views/components/layouts/inc/header.blade.php` | Header integration |

### Customization

**Change debounce time:**
```blade
wire:model.live.debounce.500ms="search"
```

**Change result limit:**
```php
->limit(10)  // In getResultsProperty()
```
