# Address System

## Overview

The application supports multi-country address handling with Google Places Autocomplete integration. The system automatically adapts address forms and fields based on the configured country, with special support for United States (US) and Brazil (BR) address formats.

## Configuration

### Environment Variables

```env
# Country Configuration (ISO 3166-1 alpha-2)
# US - United States: Shows Street, City, State, Postal Code
# BR - Brazil:        Shows Street, City, State, Postal Code, Neighborhood (Bairro)
APP_COUNTRY=US

# Google Maps API Key (required for address autocomplete)
# Enable these APIs in Google Cloud Console: Places API, Geocoding API, Maps JavaScript API
GOOGLE_MAPS_API_KEY=your_api_key_here
```

### Configuration Files

- `config/app.php` - Contains `country` setting: `config('app.country')`
- `config/services.php` - Contains Google Maps API key: `config('services.google.maps_api_key')`

## Database Schema

### Address Fields

Both `projects` and `job_sites` tables share the same address structure:

| Column | Type | Description |
|--------|------|-------------|
| `street` | varchar(255) | Street address (e.g., "123 Main St") |
| `address_2` | varchar(255) | Secondary address line (Suite, Apt, Unit, etc.) |
| `city` | varchar(255) | City name |
| `state` | varchar(255) | State/Province code (e.g., "CA", "SP") |
| `postal_code` | varchar(20) | Postal/ZIP code (US: "12345", BR: "01310-100") |
| `neighborhood` | varchar(255) | Neighborhood/District (primarily used for Brazil - Bairro) |
| `country` | varchar(2) | ISO country code (e.g., "US", "BR") |
| `latitude` | decimal(10,8) | Geographic latitude coordinate |
| `longitude` | decimal(11,8) | Geographic longitude coordinate |

### Migration Files

```
database/migrations/
├── 2026_01_19_101917_add_address_fields_to_projects_and_job_sites_tables.php
└── 2026_01_19_104711_add_coordinates_to_projects_and_job_sites_tables.php
```

## Country-Specific Behavior

### United States (APP_COUNTRY=US)

**Form Fields Displayed:**
- Street Address
- Address Line 2
- City
- State
- Postal Code

**Address Format:**
```
123 Main Street, Suite 100
New York, NY 10001
```

### Brazil (APP_COUNTRY=BR)

**Form Fields Displayed:**
- Street Address (Logradouro)
- Address Line 2 (Complemento)
- Neighborhood (Bairro)
- City (Cidade)
- State (Estado)
- CEP (Postal Code)

**Address Format:**
```
Rua Augusta, 1234, Sala 56
Consolação
São Paulo, SP 01310-100
```

**Label Changes:**
- "Postal Code" → "CEP"
- Placeholder changes to Brazilian format "00000-000"

## Model Implementation

### Fillable Fields

```php
// app/Models/Project.php and app/Models/JobSite.php
protected $fillable = [
    // ... other fields
    'street',
    'address_2',
    'city',
    'state',
    'postal_code',
    'neighborhood',
    'country',
    'latitude',
    'longitude',
    // ... other fields
];
```

### Full Address Accessor

Both models include a `getFullAddressAttribute()` accessor that formats the address based on country:

```php
public function getFullAddressAttribute(): string
{
    if ($this->country === 'BR') {
        $addressParts = array_filter([
            $this->street,
            $this->address_2,
            $this->neighborhood,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);
    } else {
        $addressParts = array_filter([
            $this->street,
            $this->address_2,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);
    }

    return implode(', ', $addressParts);
}
```

**Usage:**
```php
$project = Project::find(1);
echo $project->full_address;
// US: "123 Main St, Suite 100, New York, NY, 10001"
// BR: "Rua Augusta, 1234, Sala 56, Consolação, São Paulo, SP, 01310-100"
```

## Google Places Autocomplete

### How It Works

1. User starts typing in the Street Address field
2. Google Places API shows address suggestions filtered by the configured country
3. When user selects an address, the following fields are auto-populated:
   - Street Address
   - City
   - State
   - Postal Code / CEP
   - Neighborhood (Bairro) - for Brazilian addresses
   - Latitude
   - Longitude

### Required Google Cloud APIs

Enable these APIs in your Google Cloud Console:

1. **Places API** - For address autocomplete suggestions
2. **Geocoding API** - For latitude/longitude coordinates
3. **Maps JavaScript API** - For the autocomplete widget

### API Key Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create or select a project
3. Enable the required APIs
4. Create an API key with appropriate restrictions
5. Add the key to your `.env` file

### Implementation Details

The autocomplete is implemented using Alpine.js and the Google Maps JavaScript API:

```javascript
// Loaded in resources/views/components/layouts/inc/head.blade.php
// Only loads if GOOGLE_MAPS_API_KEY is configured

// Alpine.js component pattern used in forms:
x-data="addressAutocomplete({
    country: '{{ config('app.country') }}',
    streetInputId: 'project-street'
})"
```

### Address Component Mapping

Google Places API returns address components that are mapped as follows:

| Google Component | Application Field |
|------------------|-------------------|
| `street_number` + `route` | `street` |
| `locality` | `city` |
| `administrative_area_level_2` | `city` (fallback) |
| `administrative_area_level_1` | `state` |
| `postal_code` | `postal_code` |
| `sublocality_level_1` / `sublocality` | `neighborhood` |
| `geometry.location.lat()` | `latitude` |
| `geometry.location.lng()` | `longitude` |

## Livewire Components

### Updated Components

The following Livewire components handle address fields:

| Component | Purpose |
|-----------|---------|
| `ProjectCreate` | Create new project with address |
| `ProjectEdit` | Edit existing project address |
| `ProjectShow` | Display project address + Job Site forms |

### Component Properties

```php
// Address properties in Livewire components
public $street = '';
public $address_2 = '';
public $city = '';
public $state = '';
public $postal_code = '';
public $neighborhood = '';
public $latitude = null;
public $longitude = null;
```

### Validation Rules

```php
protected $rules = [
    'street' => 'nullable|string|max:255',
    'address_2' => 'nullable|string|max:255',
    'city' => 'nullable|string|max:255',
    'state' => 'nullable|string|max:255',
    'postal_code' => 'nullable|string|max:20',
    'neighborhood' => 'nullable|string|max:255',
    'latitude' => 'nullable|numeric|between:-90,90',
    'longitude' => 'nullable|numeric|between:-180,180',
];
```

## Blade Views

### Conditional Field Display

```blade
@if(config('app.country') === 'BR')
<!-- Neighborhood (Brazil only) -->
<div>
    <label>Neighborhood (Bairro)</label>
    <input wire:model.live="neighborhood" placeholder="Bairro">
</div>
@endif

<!-- Dynamic label for postal code -->
<label>{{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}</label>
<input
    wire:model.live="postal_code"
    placeholder="{{ config('app.country') === 'BR' ? '00000-000' : '12345' }}"
>
```

### Updated View Files

```
resources/views/livewire/
├── project/
│   ├── project-create.blade.php  (address form + autocomplete)
│   ├── project-edit.blade.php    (address form + autocomplete)
│   └── project-show.blade.php    (address display + job site form)
└── job-site/
    └── job-site-show.blade.php   (address display)
```

## Switching Countries

### Steps to Change Country

1. Update `.env` file:
   ```env
   APP_COUNTRY=BR  # or US
   ```

2. Clear configuration cache:
   ```bash
   php artisan config:clear
   ```

3. All forms and displays automatically adapt to the new country format

### What Changes

| Aspect | US | BR |
|--------|-----|-----|
| Neighborhood field | Hidden | Visible |
| Postal code label | "Postal Code" | "CEP" |
| Postal code placeholder | "12345" | "00000-000" |
| Autocomplete restriction | United States | Brazil |
| Full address format | Street, City, State, ZIP | Street, Neighborhood, City, State, CEP |

## Files Modified

### Configuration
- `.env.example` - Added `APP_COUNTRY` and `GOOGLE_MAPS_API_KEY`
- `config/app.php` - Added `country` configuration
- `config/services.php` - Added `google.maps_api_key`

### Database
- `database/migrations/2026_01_19_101917_add_address_fields_to_projects_and_job_sites_tables.php`
- `database/migrations/2026_01_19_104711_add_coordinates_to_projects_and_job_sites_tables.php`

### Models
- `app/Models/Project.php` - Added address fields to fillable, updated accessor
- `app/Models/JobSite.php` - Added address fields to fillable, updated accessor

### Livewire Components
- `app/Livewire/Project/ProjectCreate.php`
- `app/Livewire/Project/ProjectEdit.php`
- `app/Livewire/Project/ProjectShow.php`

### Views
- `resources/views/components/layouts/inc/head.blade.php` - Google Maps API script
- `resources/views/livewire/project/project-create.blade.php`
- `resources/views/livewire/project/project-edit.blade.php`
- `resources/views/livewire/project/project-show.blade.php`
- `resources/views/livewire/job-site/job-site-show.blade.php`

## Best Practices

1. **Always use model attributes** - Access address fields through the model, not raw database queries
2. **Use the accessor** - Use `$model->full_address` for formatted display
3. **Validate coordinates** - Latitude must be between -90 and 90, longitude between -180 and 180
4. **Handle empty values** - All address fields are nullable, handle empty states in views
5. **Test both countries** - When making changes, test with both `APP_COUNTRY=US` and `APP_COUNTRY=BR`

## Troubleshooting

### Autocomplete Not Working

1. Check that `GOOGLE_MAPS_API_KEY` is set in `.env`
2. Verify the API key has Places API, Geocoding API, and Maps JavaScript API enabled
3. Check browser console for errors
4. Ensure the API key has no domain restrictions blocking your development URL

### Neighborhood Not Showing

1. Verify `APP_COUNTRY=BR` in `.env`
2. Run `php artisan config:clear`
3. Refresh the page

### Coordinates Not Saving

1. Ensure the Google Places autocomplete is selecting an address (not just typing)
2. Check that `latitude` and `longitude` are in the model's `$fillable` array
3. Verify the hidden inputs are present in the form

## Future Considerations

- Add support for additional countries with specific address formats
- Implement address validation service
- Add map preview when coordinates are available
- Consider storing formatted address for display optimization
