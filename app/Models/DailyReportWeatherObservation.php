<?php

namespace App\Models;

use App\Services\WeatherService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportWeatherObservation extends Model
{
    use HasFactory;

    protected $table = 'daily_report_weather_observations';

    protected $fillable = [
        'daily_report_id',
        'observed_at',
        'weather_delay',
        'sky_condition',
        'temperature',
        'wind_condition',
        'precipitation',
        'notes',
        'order',
    ];

    protected $casts = [
        'observed_at' => 'datetime:H:i',
        'weather_delay' => 'boolean',
        'temperature' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Sky condition options.
     */
    public const SKY_CONDITIONS = [
        'clear' => 'Clear',
        'partly_cloudy' => 'Partly Cloudy',
        'cloudy' => 'Cloudy',
        'overcast' => 'Overcast',
        'foggy' => 'Foggy',
    ];

    /**
     * Wind condition options.
     */
    public const WIND_CONDITIONS = [
        'calm' => 'Calm',
        'light' => 'Light',
        'moderate' => 'Moderate',
        'strong' => 'Strong',
        'very_strong' => 'Very Strong',
    ];

    /**
     * Precipitation type options.
     */
    public const PRECIPITATION_TYPES = [
        'none' => 'None',
        'light_rain' => 'Light Rain',
        'rain' => 'Rain',
        'heavy_rain' => 'Heavy Rain',
        'drizzle' => 'Drizzle',
        'snow' => 'Snow',
        'sleet' => 'Sleet',
        'hail' => 'Hail',
    ];

    /**
     * Get the daily report that owns this observation.
     */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Get the sky condition label.
     */
    public function getSkyConditionLabelAttribute(): string
    {
        return self::SKY_CONDITIONS[$this->sky_condition] ?? $this->sky_condition ?? '-';
    }

    /**
     * Get the wind condition label.
     */
    public function getWindConditionLabelAttribute(): string
    {
        return self::WIND_CONDITIONS[$this->wind_condition] ?? $this->wind_condition ?? '-';
    }

    /**
     * Get the precipitation label.
     */
    public function getPrecipitationLabelAttribute(): string
    {
        return self::PRECIPITATION_TYPES[$this->precipitation] ?? $this->precipitation ?? '-';
    }

    /**
     * Get formatted temperature based on country setting.
     */
    public function getFormattedTemperatureAttribute(): string
    {
        return WeatherService::formatTemperature($this->temperature);
    }

    /**
     * Get formatted observation time.
     */
    public function getFormattedTimeAttribute(): string
    {
        if (!$this->observed_at) {
            return '-';
        }

        return $this->observed_at->format('g:i A');
    }

    /**
     * Scope to order observations by time.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('observed_at')->orderBy('order');
    }
}
