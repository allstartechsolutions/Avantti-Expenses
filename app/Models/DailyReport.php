<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DailyReport extends Model
{
    protected static function boot()
    {
        parent::boot();

        // Everything the day's report owns goes with it: its tasks, its
        // manpower logs, its weather and its photographs. The images own their
        // files, so they are deleted through Eloquent rather than in bulk.
        static::deleting(function (self $report) {
            $report->images->each->delete();
            $report->tasks()->delete();
            $report->manpowerLogs()->delete();
            $report->weatherObservations()->delete();
            $report->weather()->delete();
        });
    }

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

    /**
     * Whether the report can be destroyed outright.
     *
     * A daily report is the site's record of what happened on a given day, so
     * the bar is the lock rather than the age: once it is **locked** it has
     * been signed off and read, and deleting it removes a day from the
     * project's history. An unlocked one is still the author's working copy.
     *
     * `daily-reports.delete` has been in the catalogue since the module's
     * permission pass and enforced nothing until now.
     */
    public function canBeDeleted(): bool
    {
        return $this->locked_at === null;
    }
}
