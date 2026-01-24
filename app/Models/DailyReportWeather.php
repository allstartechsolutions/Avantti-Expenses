<?php

namespace App\Models;

use App\Services\WeatherService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportWeather extends Model
{
    use HasFactory;

    protected $table = 'daily_report_weather';

    protected $fillable = [
        'daily_report_id',
        'temp_low',
        'temp_high',
        'temp_avg',
        'precip_midnight',
        'precip_2_days',
        'precip_3_days',
        'humidity_low',
        'humidity_avg',
        'humidity_high',
        'dew_point',
        'wind_avg',
        'wind_max',
        'wind_gust',
        'snapshots',
        'conditions',
        'icon',
        'fetched_at',
    ];

    protected $casts = [
        'temp_low' => 'decimal:2',
        'temp_high' => 'decimal:2',
        'temp_avg' => 'decimal:2',
        'precip_midnight' => 'decimal:2',
        'precip_2_days' => 'decimal:2',
        'precip_3_days' => 'decimal:2',
        'humidity_low' => 'integer',
        'humidity_avg' => 'integer',
        'humidity_high' => 'integer',
        'dew_point' => 'decimal:2',
        'wind_avg' => 'decimal:2',
        'wind_max' => 'decimal:2',
        'wind_gust' => 'decimal:2',
        'snapshots' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get the daily report that owns this weather data.
     */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Get formatted temperature (low) based on country setting.
     */
    public function getFormattedTempLowAttribute(): string
    {
        return WeatherService::formatTemperature($this->temp_low);
    }

    /**
     * Get formatted temperature (high) based on country setting.
     */
    public function getFormattedTempHighAttribute(): string
    {
        return WeatherService::formatTemperature($this->temp_high);
    }

    /**
     * Get formatted temperature (avg) based on country setting.
     */
    public function getFormattedTempAvgAttribute(): string
    {
        return WeatherService::formatTemperature($this->temp_avg);
    }

    /**
     * Get formatted dew point based on country setting.
     */
    public function getFormattedDewPointAttribute(): string
    {
        return WeatherService::formatTemperature($this->dew_point);
    }

    /**
     * Get formatted precipitation (midnight) based on country setting.
     */
    public function getFormattedPrecipMidnightAttribute(): string
    {
        return WeatherService::formatPrecipitation($this->precip_midnight);
    }

    /**
     * Get formatted precipitation (2 days) based on country setting.
     */
    public function getFormattedPrecip2DaysAttribute(): string
    {
        return WeatherService::formatPrecipitation($this->precip_2_days);
    }

    /**
     * Get formatted precipitation (3 days) based on country setting.
     */
    public function getFormattedPrecip3DaysAttribute(): string
    {
        return WeatherService::formatPrecipitation($this->precip_3_days);
    }

    /**
     * Get formatted wind average based on country setting.
     */
    public function getFormattedWindAvgAttribute(): string
    {
        return WeatherService::formatWindSpeed($this->wind_avg);
    }

    /**
     * Get formatted wind max based on country setting.
     */
    public function getFormattedWindMaxAttribute(): string
    {
        return WeatherService::formatWindSpeed($this->wind_max);
    }

    /**
     * Get formatted wind gust based on country setting.
     */
    public function getFormattedWindGustAttribute(): string
    {
        return WeatherService::formatWindSpeed($this->wind_gust);
    }

    /**
     * Get snapshots with formatted temperatures.
     */
    public function getFormattedSnapshotsAttribute(): array
    {
        if (empty($this->snapshots)) {
            return [];
        }

        return array_map(function ($snapshot) {
            $snapshot['formatted_temp'] = WeatherService::formatTemperature($snapshot['temp'] ?? null);
            return $snapshot;
        }, $this->snapshots);
    }
}
