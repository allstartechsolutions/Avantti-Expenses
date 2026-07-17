<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        // Coalesce: config values are null when the env var is unset, which
        // would fatal on the typed properties before isConfigured() can run.
        $this->apiKey = config('services.visual_crossing.api_key') ?? '';
        $this->baseUrl = config('services.visual_crossing.base_url')
            ?? 'https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline';
    }

    /**
     * Check if the service is configured with an API key.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch weather data for a specific location and date.
     *
     * @param float $latitude
     * @param float $longitude
     * @param string $date Format: Y-m-d
     * @return array|null Returns normalized weather data or null on failure
     */
    public function fetchWeatherForDate(float $latitude, float $longitude, string $date): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Visual Crossing API key not configured');
            return null;
        }

        try {
            $reportDate = Carbon::parse($date);
            $today = Carbon::today();

            // Determine date range for precipitation accumulation
            $startDate = $reportDate->copy()->subDays(3)->format('Y-m-d');
            $endDate = $date;

            $url = "{$this->baseUrl}/{$latitude},{$longitude}/{$startDate}/{$endDate}";

            $response = Http::get($url, [
                'key' => $this->apiKey,
                'unitGroup' => 'us', // Fahrenheit, mph, inches
                'include' => 'hours,days',
                'elements' => 'datetime,tempmax,tempmin,temp,humidity,dew,precip,windspeed,windgust,conditions,icon',
            ]);

            if (!$response->successful()) {
                Log::error('Visual Crossing API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            return $this->normalizeWeatherData($data, $date);
        } catch (\Exception $e) {
            Log::error('Weather fetch failed', [
                'message' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'date' => $date,
            ]);
            return null;
        }
    }

    /**
     * Normalize the API response into our standard format.
     */
    protected function normalizeWeatherData(array $data, string $targetDate): array
    {
        $days = $data['days'] ?? [];
        $targetDay = null;
        $precipAccumulation = [
            'midnight' => 0,
            '2_days' => 0,
            '3_days' => 0,
        ];

        // Find target day and calculate precipitation accumulation
        foreach ($days as $index => $day) {
            if ($day['datetime'] === $targetDate) {
                $targetDay = $day;
                $precipAccumulation['midnight'] = $day['precip'] ?? 0;
            }

            // Calculate 2-day and 3-day precipitation
            $dayDate = Carbon::parse($day['datetime']);
            $target = Carbon::parse($targetDate);
            $diffDays = $target->diffInDays($dayDate);

            if ($diffDays <= 2) {
                $precipAccumulation['2_days'] += $day['precip'] ?? 0;
            }
            if ($diffDays <= 3) {
                $precipAccumulation['3_days'] += $day['precip'] ?? 0;
            }
        }

        if (!$targetDay) {
            return [];
        }

        // Extract hourly data for snapshots (6AM, 9AM, 12PM, 3PM, 6PM, 9PM)
        $snapshots = $this->extractHourlySnapshots($targetDay['hours'] ?? []);

        // Calculate humidity stats from hourly data
        $humidityStats = $this->calculateHumidityStats($targetDay['hours'] ?? []);

        return [
            'temp_low' => $targetDay['tempmin'] ?? null,
            'temp_high' => $targetDay['tempmax'] ?? null,
            'temp_avg' => $targetDay['temp'] ?? null,
            'precip_midnight' => round($precipAccumulation['midnight'], 2),
            'precip_2_days' => round($precipAccumulation['2_days'], 2),
            'precip_3_days' => round($precipAccumulation['3_days'], 2),
            'humidity_low' => $humidityStats['low'],
            'humidity_avg' => $humidityStats['avg'],
            'humidity_high' => $humidityStats['high'],
            'dew_point' => $targetDay['dew'] ?? null,
            'wind_avg' => $targetDay['windspeed'] ?? null,
            'wind_max' => $targetDay['windspeed'] ?? null, // API doesn't provide separate max
            'wind_gust' => $targetDay['windgust'] ?? null,
            'snapshots' => $snapshots,
            'conditions' => $targetDay['conditions'] ?? null,
            'icon' => $targetDay['icon'] ?? null,
        ];
    }

    /**
     * Extract hourly snapshots for specific times.
     */
    protected function extractHourlySnapshots(array $hours): array
    {
        $targetHours = [6, 9, 12, 15, 18, 21]; // 6AM, 9AM, 12PM, 3PM, 6PM, 9PM
        $snapshots = [];

        foreach ($hours as $hour) {
            $hourTime = Carbon::parse($hour['datetime']);
            $hourNum = (int) $hourTime->format('G');

            if (in_array($hourNum, $targetHours)) {
                $snapshots[] = [
                    'time' => $hourTime->format('g:i A'),
                    'hour' => $hourNum,
                    'condition' => $hour['conditions'] ?? 'Unknown',
                    'icon' => $hour['icon'] ?? 'cloudy',
                    'temp' => round($hour['temp'] ?? 0),
                ];
            }
        }

        // Sort by hour
        usort($snapshots, fn($a, $b) => $a['hour'] <=> $b['hour']);

        return $snapshots;
    }

    /**
     * Calculate humidity statistics from hourly data.
     */
    protected function calculateHumidityStats(array $hours): array
    {
        if (empty($hours)) {
            return ['low' => null, 'avg' => null, 'high' => null];
        }

        $humidities = array_filter(array_column($hours, 'humidity'));

        if (empty($humidities)) {
            return ['low' => null, 'avg' => null, 'high' => null];
        }

        return [
            'low' => (int) min($humidities),
            'avg' => (int) round(array_sum($humidities) / count($humidities)),
            'high' => (int) max($humidities),
        ];
    }

    /**
     * Convert Fahrenheit to Celsius.
     */
    public static function fahrenheitToCelsius(?float $fahrenheit): ?float
    {
        if ($fahrenheit === null) {
            return null;
        }

        return round(($fahrenheit - 32) * 5 / 9, 1);
    }

    /**
     * Convert inches to millimeters.
     */
    public static function inchesToMillimeters(?float $inches): ?float
    {
        if ($inches === null) {
            return null;
        }

        return round($inches * 25.4, 1);
    }

    /**
     * Convert mph to km/h.
     */
    public static function mphToKmh(?float $mph): ?float
    {
        if ($mph === null) {
            return null;
        }

        return round($mph * 1.60934, 1);
    }

    /**
     * Get the temperature unit based on country.
     */
    public static function getTemperatureUnit(): string
    {
        return config('app.country') === 'BR' ? 'C' : 'F';
    }

    /**
     * Get the precipitation unit based on country.
     */
    public static function getPrecipitationUnit(): string
    {
        return config('app.country') === 'BR' ? 'mm' : 'in';
    }

    /**
     * Get the wind speed unit based on country.
     */
    public static function getWindSpeedUnit(): string
    {
        return config('app.country') === 'BR' ? 'km/h' : 'mph';
    }

    /**
     * Format temperature for display based on country.
     */
    public static function formatTemperature(?float $fahrenheit): string
    {
        if ($fahrenheit === null) {
            return '-';
        }

        $unit = self::getTemperatureUnit();
        $temp = $unit === 'C' ? self::fahrenheitToCelsius($fahrenheit) : $fahrenheit;

        return round($temp) . '°' . $unit;
    }

    /**
     * Format precipitation for display based on country.
     */
    public static function formatPrecipitation(?float $inches): string
    {
        if ($inches === null) {
            return '-';
        }

        $unit = self::getPrecipitationUnit();
        $value = $unit === 'mm' ? self::inchesToMillimeters($inches) : $inches;

        return $value . ' ' . $unit;
    }

    /**
     * Format wind speed for display based on country.
     */
    public static function formatWindSpeed(?float $mph): string
    {
        if ($mph === null) {
            return '-';
        }

        $unit = self::getWindSpeedUnit();
        $value = $unit === 'km/h' ? self::mphToKmh($mph) : $mph;

        return $value . ' ' . $unit;
    }
}
