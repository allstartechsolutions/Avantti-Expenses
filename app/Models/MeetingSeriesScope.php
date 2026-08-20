<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project or job site a series normally covers. A new meeting starts with
 * these on the agenda, which is how the open items of those projects appear
 * without anybody asking for them.
 */
class MeetingSeriesScope extends Model
{
    protected $fillable = [
        'meeting_series_id',
        'project_id',
        'job_site_id',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function isProjectLevel(): bool
    {
        return $this->job_site_id === null;
    }

    public function label(): string
    {
        return $this->job_site_id !== null
            ? $this->project?->project_name.' › '.$this->jobSite?->job_site_name
            : (string) $this->project?->project_name;
    }
}
