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

5. **[Header Search](./header-search.md)** - Global search in the desktop header
   - Finds **projects and job sites**, grouped, 5 rows each
   - Matches names, client, contact person, street and city
   - Debounced search (300ms), minimum 2 characters
   - Lazy loading - zero queries on page load
   - Keyboard nav (arrows / enter / escape), loading, empty and below-minimum states

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
11. **[Contract Module](./contract-module.md)** - Subcontractor contracts with status workflow, change orders (additions/deductions), file attachments, and audit trail
12. **[Contract Payments](./contract-payments.md)** - Payment tracking for subcontractor contracts, automatic status transitions for all non-cancelled statuses (including active), balance calculated from adjusted amount (original + change orders)
13. **[Contract Payments Dashboard](./contract-payments-dashboard.md)** - Batch payment processing with filters, change orders column with expandable details, CSV export with change order detail rows, PDF export (summary and detailed with payment history)
14. **[Payment Batch Module](./payment-batch-module.md)** - Pre-payment staging system with draft/approve lifecycle, saved contract filters per batch, individual and bulk approval, automatic ContractPayment creation on approval
15. **[Income Module](./income-module.md)** - Money coming in at project and job site level, received vs expected receivables, and distribution of one project-level income across several job sites
16. **[File Repository (Documents)](./file-repository-plan.md)** - The document repository at project and job site level: folders, categories and tags, versioning with full history, a preview stage with full screen for PDFs, images and video, soft delete with a trash and a purge command, an activity trail on every action, and expiring public share links for clients and vendors. Files go straight from the browser to Cloudflare R2 (multi-gigabyte uploads, multipart with progress and retry), with a local-disk fallback for installs that have no bucket. Setup: **[Cloudflare R2 deployment](./deployment-cloudflare-r2.md)**
17. **[Cost Codes on Expenses and Change Orders](./expense-changeorder-costcode-plan.md)** - A change order carries two sides: what the client is billed and what it does to each cost code's budget, with an approval that gates the cost side only. One service (`CostCodeLedger`) answers Original → Changes → Revised → Committed → Actual → Remaining per code; every budget screen, a cost code drill-down, the financial reports and their PDFs read from it. Expenses can be edited at last, so a wrong cost code can be corrected, with the change written to history. Phases 1-6 built 2026-08-19/20; **phase 7, the review, is what remains** (§14 of the plan). Deploy summary: **[changelog](./changelog-2026-08-20-costcodes-changeorders.md)**.

### Planned

- **[Quotation Module plan](./quotation-module-plan.md)** - Buy-side quotations (BR: *Cotação*): requisition → quote several vendors → comparative map with equalization → negotiation rounds → justified award → contract (service) or purchase order (material). Researched against Brazilian practice; **phase 1 built**, phases 2–8 planned.
- **[Quotation Rounds](./quotation-module.md)** - Phase 2 of that chain, as built: one scope, several vendors invited, the request e-mailed from the app with a priceable PDF per vendor, every send on the record, the 2/3-proposal rule visible from the start, each vendor's proposal keyed in with equalized totals, the comparison map with its PDF, negotiation rounds kept with their before/after totals, and the award with its justification, 2/3-proposal rule and split-by-line option. and conversion into the draft purchase orders or contracts that actually get paid. Awarded prices are taught back to the catalog so the next round opens with what was really paid. Phase 9, the review, is what remains.
- **[Review and Improvements](./review-and-improvements.md)** - The standing final phase every module now gets, and the backlog of things noticed mid-build waiting to be worked.
- **[Permissions Module — as built](./permissions-module.md)** - What is actually in the code, step by step. E1 (the ability catalogue, the eight tables, the seeded roles and templates, `php artisan permissions:sync`) is complete and deployed-safe: it changes nothing on screen. Read this one for what exists, the plan for why.
- **[Permissions Module plan](./permissions-module-plan.md)** - The design that answers those notations, planned 2026-08-20: abilities instead of three flat roles, a per-user Company-wide/Assigned switch, project and job-site memberships with an action matrix, reusable templates, invitations for staff and external guests, and the ten phases that get there without changing what anyone sees on deploy day.
- **[Permissions — running notations](./permissions-notes.md)** - Notes only, nothing built: what the role system does today, where the approval gate can be walked around, and the decisions needed before permission work starts. Add new observations here rather than fixing them one screen at a time.
- **[Purchase Requisitions](./requisition-module.md)** - Phase 1 of that chain, as built: the site asks for what it needs, an admin or manager approves it, and the approved requisition waits to be quoted. Project and job-site pages, full-page form and detail, status history, attachments.

### Session handoff

- **[Open Items](./open-items.md)** - **Start here each session:** current repo state, the next feature, open engineering items, and the local verification patterns.

### User Management

16. **[User Profile](./user-profile.md)** - Profile page for updating name, email, phone, and password
    - Live password requirements checklist (Alpine.js)
    - Logout on password change

### Deployment

- **[Scheduler on Forge](./deployment-scheduler.md)** - The one `schedule:run` cron entry that drives all four recurring jobs (task overdue mail, weekly task digest, R2 upload pruning, document purge), plus the `EST` timezone caveat
- **[Cloudflare R2](./deployment-cloudflare-r2.md)** - Bucket, credentials and CORS for the document repository

### Architecture

16. **[Project-Level Resources](./project-level-resources.md)** - Dual foreign key pattern for project/job site resources
17. **[Project & Job Site Parity Rule](./project-jobsite-parity-rule.md)** - Mandatory parity between project and job site levels
18. **[JobSite Tabs to Pages Migration](./jobsite-tabs-to-pages.md)** - Migration from monolithic tabs to separate page components
19. **[Sidebar Navigation](./sidebar-navigation.md)** - Sidebar navigation structure
20. **[Project Manager Field](./project-manager-field.md)** - Optional project manager assignment from active users
21. **[Job Site Supervisor Field](./jobsite-supervisor-field.md)** - Optional supervisor assignment with change history tracking

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

1. User types at least 2 characters in the search field
2. After a 300ms pause, Livewire queries the database
3. Results are grouped into **Projects** and **Job Sites**, up to 5 rows each, each row
   showing a status dot, the name, its parent (client / project), the address and the status
4. Arrows browse, enter opens, escape closes; clicking a row navigates

### Performance Features

- **Debounced**: 300ms delay prevents excessive queries
- **Lazy**: zero database queries until the term reaches 2 characters — verified
- **Limited**: 5 rows per group, narrow `select`, relations eager-loaded
- **Safe**: `%` and `_` in the term are escaped before they reach the `LIKE`

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

**Change minimum length or rows per group:**
```php
HeaderSearch::MIN_LENGTH   // default 2
HeaderSearch::PER_GROUP    // default 5
```
