<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DailyReport extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'report_date',
        'prepared_by',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Check if this is a project-level daily report (not assigned to a job site)
     */
    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DailyReportTask::class)->orderBy('order');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(DailyReportImage::class, 'imageable');
    }

    public function weather(): HasOne
    {
        return $this->hasOne(DailyReportWeather::class);
    }

    public function weatherObservations(): HasMany
    {
        return $this->hasMany(DailyReportWeatherObservation::class)->ordered();
    }

    public function manpowerLogs(): HasMany
    {
        return $this->hasMany(DailyReportManpower::class)->ordered();
    }

    public function isEditable(): bool
    {
        if ($this->locked_at) {
            return false;
        }

        $sevenDaysAgo = now()->subDays(7);
        return $this->report_date->greaterThanOrEqualTo($sevenDaysAgo);
    }

    public function canBeEditedByAdmin(): bool
    {
        return $this->locked_at !== null;
    }
}
