# Weather System

## Overview

The application integrates weather data into Daily Reports using the Visual Crossing Weather API. The system provides:

- **API Weather Data**: Automatic fetching of historical or forecast weather based on report date
- **Manual Observations**: Field workers can record on-site weather observations throughout the day
- **Multi-Country Support**: Temperature displays in Fahrenheit (US) or Celsius (BR) based on `APP_COUNTRY`

## Configuration

### Environment Variables

```env
# Visual Crossing Weather API Configuration
# Required for weather data in daily reports (historical and forecast)
# Get your API key at: https://www.visualcrossing.com/
VISUAL_CROSSING_API_KEY=your_api_key_here
```

### Configuration File

The API configuration is stored in `config/services.php`:

```php
'visual_crossing' => [
    'api_key' => env('VISUAL_CROSSING_API_KEY'),
    'base_url' => 'https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline',
],
```

### Getting an API Key

1. Visit [Visual Crossing](https://www.visualcrossing.com/)
2. Create a free account
3. Navigate to your account dashboard
4. Copy your API key
5. Add it to your `.env` file

**Note**: The free tier allows approximately 1,000 requests per day.

## Database Schema

### daily_report_weather Table

Stores weather data fetched from the API.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `daily_report_id` | foreignId | FK to daily_reports (cascade) |
| `temp_low` | decimal(5,2) | Low temperature (Fahrenheit) |
| `temp_high` | decimal(5,2) | High temperature (Fahrenheit) |
| `temp_avg` | decimal(5,2) | Average temperature (Fahrenheit) |
| `precip_midnight` | decimal(6,2) | Precipitation since midnight (inches) |
| `precip_2_days` | decimal(6,2) | Precipitation last 2 days (inches) |
| `precip_3_days` | decimal(6,2) | Precipitation last 3 days (inches) |
| `humidity_low` | tinyint | Low humidity percentage |
| `humidity_avg` | tinyint | Average humidity percentage |
| `humidity_high` | tinyint | High humidity percentage |
| `dew_point` | decimal(5,2) | Dew point temperature (Fahrenheit) |
| `wind_avg` | decimal(5,2) | Average wind speed (mph) |
| `wind_max` | decimal(5,2) | Maximum wind speed (mph) |
| `wind_gust` | decimal(5,2) | Wind gust speed (mph) |
| `snapshots` | json | Hourly snapshots (6 time periods) |
| `conditions` | string | General weather conditions |
| `icon` | string | Weather icon identifier |
| `fetched_at` | timestamp | When data was fetched |

### daily_report_weather_observations Table

Stores manual weather observations entered by field workers.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `daily_report_id` | foreignId | FK to daily_reports (cascade) |
| `observed_at` | time | Time of observation |
| `weather_delay` | boolean | Was work delayed due to weather? |
| `sky_condition` | string | Sky condition (dropdown value) |
| `temperature` | decimal(5,2) | Observed temperature (Fahrenheit) |
| `wind_condition` | string | Wind condition (dropdown value) |
| `precipitation` | string | Precipitation type (dropdown value) |
| `notes` | text | Additional notes |
| `order` | int | Display order |

## Models

### DailyReportWeather

**Location**: `app/Models/DailyReportWeather.php`

**Relationships**:
- `dailyReport()`: BelongsTo DailyReport

**Key Accessors**:
- `formatted_temp_low`: Temperature in correct unit (°F or °C)
- `formatted_temp_high`: Temperature in correct unit
- `formatted_temp_avg`: Temperature in correct unit
- `formatted_dew_point`: Dew point in correct unit
- `formatted_precip_midnight`: Precipitation in correct unit (in or mm)
- `formatted_wind_avg`: Wind speed in correct unit (mph or km/h)
- `formatted_snapshots`: Snapshots with formatted temperatures

### DailyReportWeatherObservation

**Location**: `app/Models/DailyReportWeatherObservation.php`

**Relationships**:
- `dailyReport()`: BelongsTo DailyReport

**Constants**:
```php
// Sky Conditions
const SKY_CONDITIONS = [
    'clear' => 'Clear',
    'partly_cloudy' => 'Partly Cloudy',
    'cloudy' => 'Cloudy',
    'overcast' => 'Overcast',
    'foggy' => 'Foggy',
];

// Wind Conditions
const WIND_CONDITIONS = [
    'calm' => 'Calm',
    'light' => 'Light',
    'moderate' => 'Moderate',
    'strong' => 'Strong',
    'very_strong' => 'Very Strong',
];

// Precipitation Types
const PRECIPITATION_TYPES = [
    'none' => 'None',
    'light_rain' => 'Light Rain',
    'rain' => 'Rain',
    'heavy_rain' => 'Heavy Rain',
    'drizzle' => 'Drizzle',
    'snow' => 'Snow',
    'sleet' => 'Sleet',
    'hail' => 'Hail',
];
```

### DailyReport (Updated)

**New Relationships**:
- `weather()`: HasOne DailyReportWeather
- `weatherObservations()`: HasMany DailyReportWeatherObservation

## Weather Service

**Location**: `app/Services/WeatherService.php`

### Key Methods

```php
// Check if API is configured
$service->isConfigured(): bool

// Fetch weather for a specific date and location
$service->fetchWeatherForDate(float $lat, float $lng, string $date): ?array

// Static formatting helpers
WeatherService::formatTemperature(?float $fahrenheit): string  // "72°F" or "22°C"
WeatherService::formatPrecipitation(?float $inches): string    // "0.5 in" or "12.7 mm"
WeatherService::formatWindSpeed(?float $mph): string           // "10 mph" or "16.1 km/h"

// Unit getters
WeatherService::getTemperatureUnit(): string  // "F" or "C"
WeatherService::getPrecipitationUnit(): string // "in" or "mm"
WeatherService::getWindSpeedUnit(): string    // "mph" or "km/h"

// Conversion helpers
WeatherService::fahrenheitToCelsius(?float $f): ?float
WeatherService::inchesToMillimeters(?float $in): ?float
WeatherService::mphToKmh(?float $mph): ?float
```

### How Weather Data is Fetched

1. User clicks "Fetch Weather" on the daily report form
2. System checks if job site has latitude/longitude coordinates
3. System calls Visual Crossing Timeline API with:
   - Location: `{latitude},{longitude}`
   - Date range: 3 days before report date to report date (for precipitation accumulation)
   - Units: US (Fahrenheit, mph, inches)
4. Response is normalized and stored in the component
5. When report is saved, weather data is persisted to `daily_report_weather` table

### Historical vs Forecast Data

- **Past dates**: API returns historical weather data
- **Today**: API returns forecast/current data
- **Future dates**: API returns forecast data

The system handles all cases transparently using the same API endpoint.

## Temperature Unit Display

The system automatically displays temperatures in the appropriate unit based on `APP_COUNTRY`:

| Country | Temperature | Precipitation | Wind Speed |
|---------|-------------|---------------|------------|
| US | Fahrenheit (°F) | Inches (in) | Miles per hour (mph) |
| BR | Celsius (°C) | Millimeters (mm) | Kilometers per hour (km/h) |

**Important**: All values are stored in US units (Fahrenheit, inches, mph) and converted at display time.

### Blade Template Example

```blade
{{-- Temperature display --}}
{{ $this->formatTemperature($weather['temp_high']) }}
{{-- Output: "72°F" (US) or "22°C" (BR) --}}

{{-- Manual unit check --}}
@php
    $unit = config('app.country') === 'BR' ? 'C' : 'F';
@endphp
Temperature: {{ $temp }}°{{ $unit }}
```

## UI Components

### Weather Report Section

Displays API weather data with four metric cards:
- **Temperature**: Low, High, Average
- **Precipitation Since**: Midnight, 2 Days, 3 Days
- **Humidity**: Low, Average, High, Dew Point
- **Wind Speed**: Average, Max, Gust

Plus a **Daily Snapshot** showing conditions at 6 time periods:
- 6:00 AM, 9:00 AM, 12:00 PM, 3:00 PM, 6:00 PM, 9:00 PM

### Observed Weather Conditions Section

A table for manual weather observations with columns:
- Time
- Weather Delay (Yes/No badge)
- Sky Condition
- Temperature
- Wind Condition
- Precipitation
- Notes
- Actions (Edit/Delete)

### Modal for Adding/Editing Observations

Form fields:
- Time (time picker)
- Weather Delay (checkbox)
- Sky Condition (dropdown)
- Temperature (number input)
- Wind Condition (dropdown)
- Precipitation (dropdown)
- Notes (textarea)

## Usage

### Fetching Weather Data

1. Navigate to create/edit a daily report
2. Ensure the job site has an address with geocoding (latitude/longitude)
3. Select the report date
4. Click "Fetch Weather" button
5. Weather data will display in the Weather Report section
6. Save the report to persist the weather data

### Adding Manual Observations

1. In the "Observed Weather Conditions" section, click "Add Observation"
2. Fill in the observation details:
   - Time of observation
   - Whether work was delayed due to weather
   - Sky, wind, and precipitation conditions
   - Temperature (optional)
   - Notes (optional)
3. Click "Add Observation" to save
4. Repeat for additional observations throughout the day
5. Save the report to persist all observations

## Files Modified/Created

### New Files
- `database/migrations/2026_01_19_150000_create_daily_report_weather_table.php`
- `database/migrations/2026_01_19_150001_create_daily_report_weather_observations_table.php`
- `app/Models/DailyReportWeather.php`
- `app/Models/DailyReportWeatherObservation.php`
- `app/Services/WeatherService.php`
- `docs/weather-system.md`

### Modified Files
- `.env.example` - Added `VISUAL_CROSSING_API_KEY`
- `config/services.php` - Added `visual_crossing` configuration
- `app/Models/DailyReport.php` - Added weather relationships
- `app/Livewire/DailyReport/DailyReportForm.php` - Added weather logic
- `resources/views/livewire/daily-report/daily-report-form.blade.php` - Added weather UI

## Troubleshooting

### Weather Not Fetching

1. **Check API key**: Ensure `VISUAL_CROSSING_API_KEY` is set in `.env`
2. **Clear config cache**: Run `php artisan config:clear`
3. **Check job site coordinates**: Job site must have latitude/longitude from address geocoding
4. **Check API quota**: Free tier has ~1,000 requests/day limit

### Wrong Temperature Unit

1. Verify `APP_COUNTRY` is set correctly in `.env` (US or BR)
2. Run `php artisan config:clear`
3. Refresh the page

### Observations Not Saving

1. Ensure all required fields are filled (Time, Sky, Wind, Precipitation)
2. Check for validation errors in the modal
3. Save the report after adding observations

## Future Considerations

- Add support for additional countries/unit systems
- Implement weather data caching to reduce API calls
- Add weather alerts/warnings from API
- Export weather data to PDF reports
- Add weather trend charts/visualizations
